<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use App\Services\BenzinpreisService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * Wizard-basierte Erstellung einer Tankstelle.
 * Step 1: PLZ-Suche und optionaler Import von benzinpreis-aktuell.de
 * Step 2: Komplettes Formular (7 Tabs, ggf. vorausgefuellt)
 */
class CreateGasStation extends CreateRecord
{
    use HasWizard;

    protected static string $resource = GasStationResource::class;

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getSteps(): array
    {
        return [
            // ========== STEP 1: STATIONSSUCHE ==========
            Step::make('import')
                ->label('Stationssuche')
                ->icon('heroicon-o-magnifying-glass')
                ->description('Bestehende Tankstelle per PLZ suchen und importieren')
                ->schema([
                    Placeholder::make('import_info')
                        ->content('Geben Sie eine PLZ ein, um Tankstellen in der Naehe zu finden. Waehlen Sie eine Station aus, um deren Daten zu uebernehmen. Sie koennen diesen Schritt auch ueberspringen.')
                        ->columnSpanFull(),

                    TextInput::make('search_plz')
                        ->label('PLZ')
                        ->placeholder('z.B. 36039')
                        ->maxLength(5)
                        ->minLength(5)
                        ->live(debounce: 800)
                        ->dehydrated(false)
                        ->helperText('5-stellige PLZ eingeben — Suche startet automatisch'),

                    Select::make('import_station')
                        ->label('Gefundene Tankstellen')
                        ->options(function (Get $get): array {
                            $plz = $get('search_plz');
                            if (! $plz || strlen($plz) !== 5 || ! ctype_digit($plz)) {
                                return [];
                            }

                            $stations = app(BenzinpreisService::class)->searchByPlz($plz);

                            $options = [];
                            foreach ($stations as $s) {
                                $key = "{$s['hash']}|{$s['slug']}";
                                // Format: "BRAND Tankstelle · Straße, Stadt"
                                $label = $s['name'];
                                $addressParts = array_filter([
                                    $s['street'] ?? '',
                                    $s['city'] ?? '',
                                ]);
                                if ($addressParts) {
                                    $label .= ' · ' . implode(', ', $addressParts);
                                }
                                $options[$key] = $label;
                            }

                            return $options;
                        })
                        ->placeholder('Station auswaehlen oder ueberspringen...')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set, $livewire): void {
                            if (! $state) {
                                return;
                            }

                            [$hash, $slug] = explode('|', $state, 2);
                            $details = app(BenzinpreisService::class)->fetchStationDetails($hash, $slug);

                            if (! $details) {
                                Notification::make()
                                    ->title('Import fehlgeschlagen')
                                    ->body('Die Stationsdaten konnten nicht abgerufen werden.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Name ohne Strassen-Suffix
                            $name = $details['name'] ?? '';
                            if (str_contains($name, '·')) {
                                $name = trim(explode('·', $name)[0]);
                            }

                            // Import-Daten zusammenstellen
                            $importData = [];
                            if ($name) {
                                $importData['name'] = $name;
                            }
                            $importData['street'] = $details['street'] ?? '';
                            $importData['house_number'] = $details['house_number'] ?? '';
                            $importData['zip'] = $details['zip'] ?? '';
                            $importData['city'] = $details['city'] ?? '';
                            $importData['latitude'] = $details['lat'];
                            $importData['longitude'] = $details['lng'];

                            // Marke zuordnen
                            $brandName = '';
                            if ($details['brand']) {
                                $brand = \App\Models\Brand::firstOrCreate(
                                    ['name' => $details['brand']],
                                );
                                $importData['brand_id'] = $brand->id;
                                $brandName = $details['brand'];
                            }

                            // Oeffnungszeiten uebernehmen
                            if (! empty($details['opening_hours'])) {
                                foreach ($details['opening_hours'] as $day => $times) {
                                    data_set($importData, "opening_hours.{$day}.open", $times['open'] ?? '');
                                    data_set($importData, "opening_hours.{$day}.close", $times['close'] ?? '');
                                }
                            }

                            // Daten direkt im Livewire-Component setzen (zuverlaessig ueber Wizard-Steps)
                            $livewire->data = array_replace_recursive($livewire->data, $importData);

                            // Persistente Notification mit allen importierten Daten
                            $dayNames = [
                                'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
                                'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So',
                            ];

                            $lines = [];
                            $lines[] = '<strong>Name:</strong> ' . e($name ?: '–');
                            $lines[] = '<strong>Marke:</strong> ' . e($brandName ?: '–');
                            $lines[] = '<strong>Straße:</strong> ' . e(trim(($details['street'] ?? '') . ' ' . ($details['house_number'] ?? '')) ?: '–');
                            $lines[] = '<strong>PLZ/Stadt:</strong> ' . e(trim(($details['zip'] ?? '') . ' ' . ($details['city'] ?? '')) ?: '–');
                            $lines[] = '<strong>Koordinaten:</strong> ' . ($details['lat'] ? e("{$details['lat']}, {$details['lng']}") : '–');

                            if (! empty($details['opening_hours'])) {
                                $ohParts = [];
                                foreach ($details['opening_hours'] as $day => $times) {
                                    $label = $dayNames[$day] ?? $day;
                                    $ohParts[] = "{$label} {$times['open']}–{$times['close']}";
                                }
                                $lines[] = '<strong>Öffnungszeiten:</strong> ' . e(implode(', ', $ohParts));
                            } else {
                                $lines[] = '<strong>Öffnungszeiten:</strong> –';
                            }

                            Notification::make()
                                ->title('Stationsdaten importiert')
                                ->body(new HtmlString(implode('<br>', $lines)))
                                ->persistent()
                                ->success()
                                ->send();
                        })
                        ->dehydrated(false)
                        ->helperText('Daten werden automatisch in das Formular uebernommen')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ========== STEP 2: STATIONSDATEN ==========
            Step::make('data')
                ->label('Stationsdaten')
                ->icon('heroicon-o-building-storefront')
                ->description('Alle Daten der Tankstelle erfassen oder pruefen')
                ->schema(GasStationResource::getFormSchema()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
