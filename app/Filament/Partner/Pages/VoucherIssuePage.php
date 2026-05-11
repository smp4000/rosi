<?php

namespace App\Filament\Partner\Pages;

use App\Models\LabelTemplate;
use App\Models\Voucher;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Gutschein-Ausgabe-Page.
 *
 * Ablauf:
 * 1. Basisnummer eingeben (z.B. "4567")
 * 2. Anzahl eingeben (z.B. 50)
 * 3. Betrag pro Gutschein eingeben (z.B. 50.00)
 * 4. System prueft ob Nummern schon existieren
 * 5. Gutscheine werden in DB angelegt
 * 6. DYMO-Etiketten werden gedruckt
 */
class VoucherIssuePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Gutscheine ausgeben';

    protected static ?string $title = 'Gutscheine ausgeben';

    protected static ?string $slug = 'vouchers/issue';

    protected static string|\UnitEnum|null $navigationGroup = 'Gutscheine';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.partner.pages.voucher-issue';

    public ?array $data = [];

    // Ergebnis der letzten Generierung (fuer Anzeige im Blade)
    public ?array $lastResult = null;

    // Druckfortschritt
    public int $printedCount = 0;
    public int $totalToPrint = 0;
    public bool $isPrinting = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                Section::make('Gutschein-Daten')
                    ->icon('heroicon-o-gift')
                    ->description('Basisnummer aus dem WaWi eingeben. Die Gutscheinnummern werden automatisch generiert (z.B. 4567.000 bis 4567.049).')
                    ->schema([
                        TextInput::make('voucher_group')
                            ->label('Gutschein-Basisnummer')
                            ->placeholder('z.B. 4567')
                            ->required()
                            ->maxLength(20)
                            ->helperText('Die Nummer aus dem WaWi-System'),

                        TextInput::make('quantity')
                            ->label('Anzahl Gutscheine')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(500)
                            ->default(1)
                            ->helperText('Wie viele Gutscheine sollen generiert werden?'),

                        TextInput::make('amount')
                            ->label('Betrag pro Gutschein (EUR)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(9999.99)
                            ->step(0.01)
                            ->placeholder('z.B. 50.00')
                            ->helperText('Wert jedes einzelnen Gutscheins in Euro'),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Schritt 1: Pruefen ob Nummern frei sind.
     */
    public function checkAndGenerate(): void
    {
        $this->validate();

        $group = trim($this->data['voucher_group'] ?? '');
        $quantity = (int) ($this->data['quantity'] ?? 0);
        $amount = (float) ($this->data['amount'] ?? 0);

        if (empty($group) || $quantity < 1 || $amount <= 0) {
            Notification::make()
                ->title('Bitte alle Felder ausfuellen')
                ->danger()
                ->send();
            return;
        }

        $tenantId = auth()->user()->tenant_id;

        // Pruefen ob Nummern schon existieren
        $conflicts = Voucher::checkGroupConflict($group, $quantity, $tenantId);

        if ($conflicts) {
            $conflictList = implode(', ', array_slice($conflicts, 0, 10));
            $more = count($conflicts) > 10 ? ' (und ' . (count($conflicts) - 10) . ' weitere)' : '';

            Notification::make()
                ->title('Gutscheinnummern existieren bereits!')
                ->body("Folgende Nummern sind schon vergeben: {$conflictList}{$more}")
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        // Alles OK — Gutscheine generieren
        try {
            $user = auth()->user();
            $stationId = $tenantId; // Station = Tenant

            $vouchers = Voucher::generateGroup(
                group: $group,
                quantity: $quantity,
                amount: $amount,
                stationId: $stationId,
                tenantId: $tenantId,
                employeeId: $user->id,
                employeeName: $user->name,
            );

            $first = $vouchers->first();
            $last = $vouchers->last();

            $this->lastResult = [
                'count' => $vouchers->count(),
                'group' => $group,
                'first_number' => $first->voucher_number,
                'last_number' => $last->voucher_number,
                'amount' => number_format($amount, 2, ',', '.'),
                'total' => number_format($amount * $quantity, 2, ',', '.'),
                'valid_until' => $first->valid_until->format('d.m.Y'),
            ];

            Notification::make()
                ->title("{$quantity} Gutscheine generiert!")
                ->body("{$first->voucher_number} bis {$last->voucher_number} a {$this->lastResult['amount']} EUR")
                ->success()
                ->send();

            Log::info('Gutscheine generiert', [
                'group' => $group,
                'quantity' => $quantity,
                'amount' => $amount,
                'user' => $user->name,
            ]);

        } catch (\Throwable $e) {
            Log::error('Gutschein-Generierung fehlgeschlagen', [
                'error' => $e->getMessage(),
                'group' => $group,
            ]);
            Notification::make()
                ->title('Fehler beim Generieren')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Schritt 2: DYMO-Druck fuer die generierte Gruppe starten.
     */
    public function printVouchers(): void
    {
        if (! $this->lastResult) {
            Notification::make()->title('Erst Gutscheine generieren')->warning()->send();
            return;
        }

        $group = $this->lastResult['group'];
        $tenantId = auth()->user()->tenant_id;

        // Template laden
        $template = LabelTemplate::findForTenant('gutschein', $tenantId);
        if (! $template) {
            Notification::make()
                ->title('Gutschein-Druckvorlage nicht gefunden')
                ->body('Bitte unter Einstellungen > Label-Vorlagen eine Vorlage mit Kategorie "gutschein" anlegen.')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $vouchers = Voucher::where('tenant_id', $tenantId)
            ->where('voucher_group', $group)
            ->orderBy('voucher_number')
            ->get();

        if ($vouchers->isEmpty()) {
            Notification::make()->title('Keine Gutscheine gefunden')->danger()->send();
            return;
        }

        $this->totalToPrint = $vouchers->count();
        $this->printedCount = 0;
        $this->isPrinting = true;

        $printController = new \App\Http\Controllers\Api\V1\PrintController();
        $errors = [];

        foreach ($vouchers as $voucher) {
            try {
                $labelData = [
                    'betrag' => number_format($voucher->amount, 2, ',', '.') . ' €',
                    'betrag_worte' => Voucher::amountToWords($voucher->amount),
                    'datum' => $voucher->issued_at->format('d.m.Y'),
                    'gueltig_bis' => $voucher->valid_until->format('d.m.Y'),
                    'nummer' => $voucher->voucher_number,
                    'barcode' => 'www.aral-welle.de',
                ];

                $labelXml = $template->render($labelData);

                // Direkt ueber DYMO API drucken
                $result = $this->printViaDymo($labelXml);

                if (! $result['success']) {
                    $errors[] = "{$voucher->voucher_number}: {$result['message']}";
                }

                $this->printedCount++;

            } catch (\Throwable $e) {
                $errors[] = "{$voucher->voucher_number}: {$e->getMessage()}";
                Log::error('Gutschein-Druck fehlgeschlagen', [
                    'voucher' => $voucher->voucher_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->isPrinting = false;

        if (empty($errors)) {
            Notification::make()
                ->title("Alle {$this->totalToPrint} Gutscheine gedruckt!")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Druck teilweise fehlgeschlagen')
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * Druckt ein Label-XML ueber die DYMO WebApi.
     */
    private function printViaDymo(string $labelXml): array
    {
        $ports = [41951, 41952, 41953, 41954, 41955];
        $printer = 'DYMO LabelWriter Wireless';

        // Aktiven Port finden
        $activePort = null;
        foreach ($ports as $port) {
            $url = "https://localhost:{$port}/DYMO/DLS/Printing/StatusConnected";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 3,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result === 'true') {
                $activePort = $port;
                break;
            }
        }

        if (! $activePort) {
            return ['success' => false, 'message' => 'DYMO Service nicht erreichbar'];
        }

        // Label drucken
        $url = "https://localhost:{$activePort}/DYMO/DLS/Printing/PrintLabel2";
        $printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'printerName' => $printer,
                'labelXml' => $labelXml,
                'printParamsXml' => $printParams,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            return ['success' => false, 'message' => "DYMO-Fehler: {$error}"];
        }

        return $result === 'true'
            ? ['success' => true, 'message' => 'Gedruckt']
            : ['success' => false, 'message' => "DYMO-Antwort: {$result}"];
    }

    /**
     * Formular zuruecksetzen fuer neue Gutschein-Gruppe.
     */
    public function resetForm(): void
    {
        $this->form->fill();
        $this->lastResult = null;
        $this->printedCount = 0;
        $this->totalToPrint = 0;
        $this->isPrinting = false;
    }

    /**
     * KPIs fuer die Anzeige.
     */
    public function getKpisProperty(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $total = Voucher::where('tenant_id', $tenantId)->count();
        $active = Voucher::where('tenant_id', $tenantId)
            ->whereIn('status', [Voucher::STATUS_ACTIVE, Voucher::STATUS_PARTIALLY_REDEEMED])
            ->count();
        $redeemed = Voucher::where('tenant_id', $tenantId)
            ->where('status', Voucher::STATUS_REDEEMED)
            ->count();
        $totalValue = Voucher::where('tenant_id', $tenantId)->sum('amount');
        $openValue = Voucher::where('tenant_id', $tenantId)
            ->whereIn('status', [Voucher::STATUS_ACTIVE, Voucher::STATUS_PARTIALLY_REDEEMED])
            ->sum('remaining_amount');

        return [
            'total' => $total,
            'active' => $active,
            'redeemed' => $redeemed,
            'total_value' => number_format($totalValue, 2, ',', '.'),
            'open_value' => number_format($openValue, 2, ',', '.'),
        ];
    }

    /**
     * Letzte Gutschein-Gruppen.
     */
    public function getRecentGroupsProperty(): \Illuminate\Support\Collection
    {
        $tenantId = auth()->user()->tenant_id;

        return Voucher::where('tenant_id', $tenantId)
            ->selectRaw('voucher_group, MIN(voucher_number) as first_number, MAX(voucher_number) as last_number, COUNT(*) as count, amount, MIN(issued_at) as issued_at, MIN(valid_until) as valid_until')
            ->groupBy('voucher_group', 'amount')
            ->orderByDesc('issued_at')
            ->limit(10)
            ->get();
    }
}
