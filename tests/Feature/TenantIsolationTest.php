<?php

namespace Tests\Feature;

use App\Models\CoolingUnit;
use App\Models\Device;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ──────────────────────────────────────────────────────────────────────────
 *  W-2 (Sicherheits-Audit 07/2026): Mandanten-Isolation
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Die wichtigste Test-Klasse des Projekts: "Mandant A sieht NIE Daten von
 * Mandant B." Sie sichert die T-1/T-2-Umbauten dauerhaft ab:
 *   - TenantScope filtert ueber den zentralen TenantContext (API/Jobs)
 *   - TenantScope filtert ueber die Session (Filament-Web)
 *   - Die API-Middleware SetApiTenantContext setzt den Kontext aus dem
 *     device_token — Ende-zu-Ende ueber einen echten HTTP-Request getestet
 *   - Der schnelle Geraete-Token-Lookup (A-4) findet nur das richtige Geraet
 *
 * Laufen mit:  php artisan test --filter=TenantIsolationTest
 * (braucht die MySQL-Testdatenbank rosi_test, siehe phpunit.xml)
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private string $stationA;
    private string $stationB;

    /**
     * Testdaten: zwei Mandanten mit je einer (fiktiven) Station und einem
     * Kuehlmoebel. CoolingUnit ist bewusst gewaehlt: tenant-gebunden, aber
     * ohne weitere Pflicht-Beziehungen — minimale, stabile Fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A', 'slug' => 'tenant-a', 'email' => 'a@test.de',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'email' => 'b@test.de',
        ]);

        // devices.station_id hat einen echten Fremdschluessel auf gas_stations
        // -> wir brauchen echte (minimale) Stationen je Mandant.
        $this->stationA = \App\Models\GasStation::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Station A',
        ])->id;
        $this->stationB = \App\Models\GasStation::create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Station B',
        ])->id;

        CoolingUnit::create([
            'tenant_id' => $this->tenantA->id, 'station_id' => $this->stationA,
            'name' => 'Kuehltheke A', 'device_id' => 'AAAA00000001',
        ]);
        CoolingUnit::create([
            'tenant_id' => $this->tenantB->id, 'station_id' => $this->stationB,
            'name' => 'Kuehltheke B', 'device_id' => 'BBBB00000001',
        ]);
    }

    /** Hilfsfunktion: registriertes, freigegebenes Geraet mit Klartext-Token. */
    private function createDevice(Tenant $tenant, string $stationId, string $plainToken): Device
    {
        $device = new Device([
            'tenant_id' => $tenant->id,
            'station_id' => $stationId,
            'device_type' => 'mde',
            'is_active' => true,
            'approval_status' => Device::APPROVAL_ACTIVE,
        ]);
        $device->setPlainToken($plainToken); // bcrypt-Hash + HMAC-Lookup (A-4)
        $device->save();

        return $device;
    }

    // ── T-1: Scope ueber den zentralen Kontext ────────────────────────────

    public function test_tenant_scope_filtert_ueber_den_kontext(): void
    {
        $context = app(TenantContext::class);

        // Kontext = Mandant A -> nur dessen Kuehlmoebel sichtbar
        $context->set($this->tenantA->id);
        $this->assertSame(1, CoolingUnit::count());
        $this->assertSame('Kuehltheke A', CoolingUnit::first()->name);

        // Kontext = Mandant B -> nur B
        $context->set($this->tenantB->id);
        $this->assertSame(1, CoolingUnit::count());
        $this->assertSame('Kuehltheke B', CoolingUnit::first()->name);

        // Kein Kontext -> kein Filter (Admin-/CLI-Verhalten)
        $context->set(null);
        $this->assertSame(2, CoolingUnit::count());
    }

    public function test_tenant_scope_filtert_ueber_die_session(): void
    {
        // Klassischer Filament-Web-Fall: tenant_id liegt in der Session
        session(['tenant_id' => $this->tenantA->id]);

        $this->assertSame(1, CoolingUnit::count());
        $this->assertSame('Kuehltheke A', CoolingUnit::first()->name);

        session()->forget('tenant_id');
        $this->assertSame(2, CoolingUnit::count());
    }

    public function test_neue_datensaetze_erben_den_kontext_mandanten(): void
    {
        app(TenantContext::class)->set($this->tenantA->id);

        // tenant_id NICHT mitgeben -> Trait muss sie aus dem Kontext setzen
        $unit = CoolingUnit::create([
            'station_id' => $this->stationA,
            'name' => 'Neu ohne tenant_id', 'device_id' => 'AAAA00000002',
        ]);

        $this->assertSame($this->tenantA->id, $unit->tenant_id);
    }

    // ── API Ende-zu-Ende: device_token -> Kontext -> Isolation ───────────

    public function test_api_liefert_nur_daten_des_eigenen_mandanten(): void
    {
        $this->createDevice($this->tenantA, $this->stationA, 'token-tenant-a');

        // Echter HTTP-Request wie von der POS-App
        $response = $this->getJson('/api/v1/temperatures/units?device_token=token-tenant-a');

        $response->assertOk();
        $names = collect($response->json('data.units'))->pluck('name');

        $this->assertTrue($names->contains('Kuehltheke A'), 'Eigenes Kuehlmoebel fehlt');
        $this->assertFalse($names->contains('Kuehltheke B'), 'CROSS-TENANT-LECK: Moebel von Mandant B sichtbar!');
    }

    public function test_api_mit_fremdem_token_sieht_nichts_von_anderen(): void
    {
        $this->createDevice($this->tenantB, $this->stationB, 'token-tenant-b');

        $response = $this->getJson('/api/v1/temperatures/units?device_token=token-tenant-b');

        $response->assertOk();
        $names = collect($response->json('data.units'))->pluck('name');
        $this->assertFalse($names->contains('Kuehltheke A'), 'CROSS-TENANT-LECK: Moebel von Mandant A sichtbar!');
    }

    public function test_api_ohne_gueltigen_token_wird_abgelehnt(): void
    {
        $this->getJson('/api/v1/temperatures/units?device_token=voellig-falsch')
            ->assertStatus(401);
    }

    // ── A-4: Geraete-Token-Lookup ─────────────────────────────────────────

    public function test_geraete_token_findet_genau_das_richtige_geraet(): void
    {
        $deviceA = $this->createDevice($this->tenantA, $this->stationA, 'token-a');
        $this->createDevice($this->tenantB, $this->stationB, 'token-b');

        $found = Device::findByPlainToken('token-a');

        $this->assertNotNull($found);
        $this->assertSame($deviceA->id, $found->id);
        $this->assertSame($this->tenantA->id, $found->tenant_id);

        // Muell-Token darf nichts liefern
        $this->assertNull(Device::findByPlainToken('gibt-es-nicht'));
    }
}
