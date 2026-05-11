<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gutschein.
 *
 * Jeder Gutschein hat eine eindeutige Nummer (z.B. "4567.023").
 * Die voucher_group ("4567") fasst einen Druckauftrag zusammen.
 *
 * Status-Workflow: active → partially_redeemed → redeemed
 *                  active → expired (automatisch nach valid_until)
 *                  active → cancelled (manuell)
 */
class Voucher extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, Auditable;

    // Status-Konstanten
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PARTIALLY_REDEEMED = 'partially_redeemed';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'station_id',
        'voucher_group',
        'voucher_number',
        'amount',
        'remaining_amount',
        'issued_at',
        'valid_until',
        'status',
        'employee_id',
        'employee_name',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'issued_at' => 'date',
            'valid_until' => 'date',
        ];
    }

    // -- Relationships --

    public function station(): BelongsTo
    {
        return $this->belongsTo(GasStation::class, 'station_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    // -- Scopes --

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRedeemable($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_REDEEMED])
                     ->where('valid_until', '>=', now()->toDateString());
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('voucher_group', $group);
    }

    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<', now()->toDateString())
                     ->where('status', '!=', self::STATUS_REDEEMED);
    }

    // -- Business Logic --

    /**
     * Pruefen ob der Gutschein einloesbar ist.
     */
    public function isRedeemable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_REDEEMED])
            && $this->valid_until >= now()->toDateString()
            && $this->remaining_amount > 0;
    }

    /**
     * Gutschein (teil-)einloesen.
     *
     * @param float  $redeemAmount  Einzuloesender Betrag
     * @param string $stationId     Tankstelle an der eingeloest wird
     * @param string|null $employeeId  Mitarbeiter-ID
     * @param string|null $employeeName Mitarbeiter-Name
     * @param string|null $notes       Notizen
     * @return VoucherRedemption
     *
     * @throws \InvalidArgumentException wenn Betrag ungueltig
     * @throws \LogicException wenn Gutschein nicht einloesbar
     */
    public function redeem(
        float $redeemAmount,
        string $stationId,
        ?string $employeeId = null,
        ?string $employeeName = null,
        ?string $notes = null
    ): VoucherRedemption {
        if (! $this->isRedeemable()) {
            throw new \LogicException("Gutschein {$this->voucher_number} ist nicht einloesbar (Status: {$this->status}, gueltig bis: {$this->valid_until}).");
        }

        if ($redeemAmount <= 0) {
            throw new \InvalidArgumentException('Einloesungsbetrag muss groesser als 0 sein.');
        }

        if ($redeemAmount > $this->remaining_amount) {
            throw new \InvalidArgumentException(
                "Einloesungsbetrag ({$redeemAmount}) uebersteigt Restguthaben ({$this->remaining_amount})."
            );
        }

        $remainingAfter = round($this->remaining_amount - $redeemAmount, 2);

        // Einloesung anlegen
        $redemption = $this->redemptions()->create([
            'station_id' => $stationId,
            'amount' => $redeemAmount,
            'remaining_after' => $remainingAfter,
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'redeemed_at' => now(),
            'notes' => $notes,
        ]);

        // Gutschein aktualisieren
        $this->remaining_amount = $remainingAfter;
        $this->status = $remainingAfter <= 0
            ? self::STATUS_REDEEMED
            : self::STATUS_PARTIALLY_REDEEMED;
        $this->save();

        return $redemption;
    }

    /**
     * Prueft ob eine Gutschein-Gruppe bereits existiert.
     *
     * @param string $group      Basisnummer z.B. "4567"
     * @param int    $quantity   Anzahl Gutscheine
     * @param string|null $tenantId  Tenant-ID
     * @return array|null  Null wenn frei, sonst Array mit kollidierenden Nummern
     */
    public static function checkGroupConflict(string $group, int $quantity, ?string $tenantId = null): ?array
    {
        $numbers = [];
        for ($i = 0; $i < $quantity; $i++) {
            $numbers[] = sprintf('%s.%03d', $group, $i);
        }

        $query = static::withoutTenantScope()
            ->whereIn('voucher_number', $numbers);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $existing = $query->pluck('voucher_number')->toArray();

        return count($existing) > 0 ? $existing : null;
    }

    /**
     * Generiert eine Gruppe von Gutscheinen.
     *
     * @param string $group       Basisnummer z.B. "4567"
     * @param int    $quantity    Anzahl z.B. 50
     * @param float  $amount      Betrag pro Gutschein z.B. 50.00
     * @param string|null $stationId   Tankstelle (optional)
     * @param string $tenantId    Tenant
     * @param string|null $employeeId
     * @param string|null $employeeName
     * @return \Illuminate\Support\Collection  Collection von Voucher-Instanzen
     */
    public static function generateGroup(
        string $group,
        int $quantity,
        float $amount,
        ?string $stationId,
        string $tenantId,
        ?string $employeeId = null,
        ?string $employeeName = null
    ): \Illuminate\Support\Collection {
        $issuedAt = now()->toDateString();
        $validUntil = now()->addYears(3)->toDateString();

        $vouchers = collect();

        for ($i = 0; $i < $quantity; $i++) {
            $voucher = static::create([
                'tenant_id' => $tenantId,
                'station_id' => $stationId,
                'voucher_group' => $group,
                'voucher_number' => sprintf('%s.%03d', $group, $i),
                'amount' => $amount,
                'remaining_amount' => $amount,
                'issued_at' => $issuedAt,
                'valid_until' => $validUntil,
                'status' => self::STATUS_ACTIVE,
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
            ]);

            $vouchers->push($voucher);
        }

        return $vouchers;
    }

    /**
     * Betrag als deutsches Format "50,00 €".
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    /**
     * Restguthaben als deutsches Format.
     */
    public function getFormattedRemainingAttribute(): string
    {
        return number_format($this->remaining_amount, 2, ',', '.') . ' €';
    }

    /**
     * Status-Label fuer die Anzeige.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Aktiv',
            self::STATUS_PARTIALLY_REDEEMED => 'Teileingelöst',
            self::STATUS_REDEEMED => 'Eingelöst',
            self::STATUS_EXPIRED => 'Abgelaufen',
            self::STATUS_CANCELLED => 'Storniert',
            default => $this->status,
        };
    }

    /**
     * Betrag in Worten (deutsch) fuer DYMO-Label.
     * Unterstuetzt 0–999.999 Euro + Cent.
     */
    public static function amountToWords(float $amount): string
    {
        $ones = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
                 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
                 'siebzehn', 'achtzehn', 'neunzehn'];
        $tens = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

        $convertBelow1000 = function (int $n) use ($ones, $tens): string {
            if ($n === 0) return '';
            if ($n < 20) return $ones[$n];
            if ($n < 100) {
                $t = intdiv($n, 10);
                $o = $n % 10;
                return $o > 0 ? $ones[$o] . 'und' . $tens[$t] : $tens[$t];
            }
            $h = intdiv($n, 100);
            $rest = $n % 100;
            $result = $ones[$h] . 'hundert';
            if ($rest > 0) {
                if ($rest < 20) {
                    $result .= $ones[$rest];
                } else {
                    $t = intdiv($rest, 10);
                    $o = $rest % 10;
                    $result .= $o > 0 ? $ones[$o] . 'und' . $tens[$t] : $tens[$t];
                }
            }
            return $result;
        };

        $euro = (int) floor($amount);
        $cent = (int) round(($amount - $euro) * 100);

        if ($euro === 0 && $cent === 0) return 'null Euro';

        $parts = [];

        if ($euro > 0) {
            if ($euro >= 1000) {
                $thousands = intdiv($euro, 1000);
                $remainder = $euro % 1000;
                $tWord = $thousands === 1 ? 'ein' : $convertBelow1000($thousands);
                $parts[] = $tWord . 'tausend' . $convertBelow1000($remainder);
            } else {
                $parts[] = $convertBelow1000($euro);
            }
            $parts[] = 'Euro';
        }

        if ($cent > 0) {
            $parts[] = $convertBelow1000($cent);
            $parts[] = 'Cent';
        }

        return implode(' ', $parts);
    }
}
