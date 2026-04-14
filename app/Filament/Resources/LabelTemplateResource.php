<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabelTemplateResource\Pages;
use App\Models\LabelTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Admin-Resource: Druckvorlagen verwalten.
 * Nur fuer Super-Admins — Stationen koennen nur auswaehlen, nicht bearbeiten.
 */
class LabelTemplateResource extends Resource
{
    protected static ?string $model = LabelTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Druckvorlage';

    protected static ?string $pluralModelLabel = 'Druckvorlagen';

    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Vorlage')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Kennung')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('orientation')
                    ->label('Ausrichtung')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Portrait' ? 'info' : 'warning'),

                TextColumn::make('placeholders')
                    ->label('Platzhalter')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '–';
                        $items = is_string($state) ? json_decode($state, true) : $state;
                        if (!is_array($items)) return '–';
                        return collect($items)->map(fn ($p) => '{{' . ($p['key'] ?? '?') . '}}')->join(', ');
                    })
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('category')
            ->actions([
                EditAction::make()
                    ->form(self::getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $modelType = $data['model_type'] ?? null;
                        unset($data['model_type']);
                        if ($modelType && $modelType !== 'custom') {
                            $data['placeholders'] = LabelTemplate::getPlaceholdersForModel($modelType);
                        }
                        return $data;
                    }),

                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Neue Vorlage')
                    ->form(self::getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $modelType = $data['model_type'] ?? null;
                        unset($data['model_type']);
                        if ($modelType && $modelType !== 'custom') {
                            $data['placeholders'] = LabelTemplate::getPlaceholdersForModel($modelType);
                        }
                        return $data;
                    }),
            ]);
    }

    private static function getFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),

            Select::make('model_type')
                ->label('Modell')
                ->options(LabelTemplate::getModelOptions())
                ->default('custom')
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state && $state !== 'custom') {
                        $set('placeholders', LabelTemplate::getPlaceholdersForModel($state));
                        $set('category', $state);
                        $set('slug', $state . '-neu');
                    }
                })
                ->helperText('Waehle ein Modell um Platzhalter und Kategorie automatisch zu setzen'),

            TextInput::make('slug')
                ->label('Kennung (eindeutig)')
                ->required()
                ->maxLength(50)
                ->regex('/^[a-z0-9\-]+$/')
                ->helperText('z.B. "tankbetrug-standard", "tankbetrug-qr"'),

            TextInput::make('category')
                ->label('Kategorie')
                ->required()
                ->maxLength(50)
                ->regex('/^[a-z0-9\-]+$/')
                ->helperText('Gruppierung: z.B. "tankbetrug", "testdruck", "adresse"'),

            Select::make('orientation')
                ->label('Ausrichtung')
                ->options([
                    'Portrait' => 'Hochformat (Portrait)',
                    'Landscape' => 'Querformat (Landscape)',
                ])
                ->default('Portrait')
                ->required(),

            TextInput::make('width')
                ->label('Breite (Zoll)')
                ->numeric()
                ->default(3.98)
                ->step(0.01),

            TextInput::make('height')
                ->label('Hoehe (Zoll)')
                ->numeric()
                ->default(2.13)
                ->step(0.01),

            Placeholder::make('placeholders_info')
                ->label('Verfuegbare Platzhalter')
                ->content(function ($record, callable $get) {
                    $modelType = $get('model_type');
                    if ($modelType && $modelType !== 'custom') {
                        $items = LabelTemplate::getPlaceholdersForModel($modelType);
                    } elseif ($record && !empty($record->placeholders)) {
                        $items = is_string($record->placeholders) ? json_decode($record->placeholders, true) : $record->placeholders;
                    } else {
                        return 'Waehle ein Modell oben, oder definiere eigene Platzhalter im XML.';
                    }
                    if (!is_array($items) || empty($items)) return 'Keine Platzhalter definiert.';
                    return collect($items)->map(fn ($p) => '{{' . $p['key'] . '}}  —  ' . $p['label'] . '  (z.B. ' . ($p['example'] ?? '') . ')')->join("\n");
                })
                ->columnSpanFull(),

            Textarea::make('xml_template')
                ->label('XML-Vorlage')
                ->required()
                ->rows(15)
                ->helperText('DYMO Label-XML. Verwende die Platzhalter oben, z.B. {{kennzeichen}} oder {{datum}}.')
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Aktiv')
                ->default(true),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelTemplates::route('/'),
        ];
    }
}
