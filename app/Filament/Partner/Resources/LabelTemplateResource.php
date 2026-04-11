<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\LabelTemplateResource\Pages;
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
use Illuminate\Database\Eloquent\Builder;

class LabelTemplateResource extends Resource
{
    protected static ?string $model = LabelTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?string $modelLabel = 'Druckvorlage';

    protected static ?string $pluralModelLabel = 'Druckvorlagen';

    protected static ?int $navigationSort = 93;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->forTenant(session('tenant_id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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

                IconColumn::make('tenant_id')
                    ->label('Typ')
                    ->icon(fn ($state) => $state ? 'heroicon-o-user' : 'heroicon-o-globe-alt')
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->tooltip(fn ($state) => $state ? 'Eigene Vorlage' : 'Standard-Vorlage'),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make()
                    ->form(self::getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        // Slug nicht aenderbar bei globalen Templates
                        return $data;
                    }),

                DeleteAction::make()
                    ->visible(fn (LabelTemplate $record) => $record->tenant_id !== null),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Neue Vorlage')
                    ->form(self::getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
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

            TextInput::make('slug')
                ->label('Kennung')
                ->required()
                ->maxLength(50)
                ->regex('/^[a-z0-9\-]+$/')
                ->helperText('Kleinbuchstaben, Zahlen und Bindestriche. z.B. "tankbetrug"'),

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
                ->content(function ($record) {
                    if (!$record || empty($record->placeholders)) return 'Keine Platzhalter definiert.';
                    $items = is_string($record->placeholders) ? json_decode($record->placeholders, true) : $record->placeholders;
                    if (!is_array($items)) return 'Keine Platzhalter definiert.';
                    return collect($items)->map(fn ($p) => '{{' . $p['key'] . '}} — ' . $p['label'] . ' (z.B. ' . ($p['example'] ?? '') . ')')->join("\n");
                })
                ->columnSpanFull(),

            Textarea::make('xml_template')
                ->label('XML-Vorlage')
                ->required()
                ->rows(15)
                ->helperText('DYMO Label-XML mit Platzhaltern wie {{datum}}, {{kennzeichen}} etc.')
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
