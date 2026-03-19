<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use App\Models\PlaceholderSetting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Dokumentvorlagen-Verwaltung im Partner-Panel.
 * System-Vorlagen koennen dupliziert werden, eigene Vorlagen sind voll editierbar.
 */
class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Vorlage';

    protected static ?string $pluralModelLabel = 'Vorlagen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Vorlage')
                ->tabs([
                    Tab::make('Allgemein')
                        ->icon('heroicon-o-document')
                        ->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->label('Typ')
                                ->options([
                                    'employment_contract' => 'Arbeitsvertrag',
                                    'termination' => 'Kuendigung',
                                    'warning' => 'Abmahnung',
                                    'work_certificate' => 'Arbeitszeugnis',
                                    'interim_certificate' => 'Zwischenzeugnis',
                                    'amendment' => 'Aenderungsvertrag',
                                    'instruction' => 'Belehrung / Unterweisung',
                                    'vacation_request' => 'Urlaubsantrag',
                                    'sick_note' => 'Krankmeldung',
                                    'salary_adjustment' => 'Gehaltsanpassung',
                                    'other' => 'Sonstiges',
                                ])
                                ->required()
                                ->searchable(),
                            Select::make('category')
                                ->label('Kategorie')
                                ->options([
                                    'hr' => 'Personalwesen',
                                    'onboarding' => 'Onboarding / Einstellung',
                                    'legal' => 'Rechtliches',
                                    'other' => 'Sonstiges',
                                ])
                                ->default('hr'),
                            TextInput::make('description')
                                ->label('Beschreibung')
                                ->maxLength(500),
                            Toggle::make('is_active')
                                ->label('Aktiv')
                                ->default(true),
                            Toggle::make('is_default')
                                ->label('Standard-Vorlage'),
                            Toggle::make('is_onboarding')
                                ->label('Pflichtformular bei Einstellung')
                                ->helperText('Wird automatisch im Onboarding-Paket mit generiert'),
                        ])
                        ->columns(2),

                    Tab::make('Inhalt')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            // Platzhalter-Hilfe
                            Placeholder::make('_placeholder_help')
                                ->hiddenLabel()
                                ->content(function () {
                                    $systemPlaceholders = [
                                        '{{mitarbeiter_name}}', '{{mitarbeiter_vorname}}', '{{mitarbeiter_nachname}}',
                                        '{{mitarbeiter_email}}', '{{mitarbeiter_adresse}}', '{{mitarbeiter_geburtsdatum}}',
                                        '{{eintrittsdatum}}', '{{wochenstunden}}', '{{gehalt}}', '{{urlaubstage}}',
                                        '{{arbeitgeber_name}}', '{{arbeitgeber_adresse}}',
                                        '{{tankstelle_name}}', '{{tankstelle_adresse}}',
                                        '{{datum_heute}}', '{{ort_datum}}',
                                    ];

                                    // Benutzerdefinierte Platzhalter laden
                                    $custom = PlaceholderSetting::where('tenant_id', session('tenant_id'))
                                        ->whereNotNull('value')
                                        ->get()
                                        ->map(fn ($p) => '{{' . $p->key . '}}')
                                        ->toArray();

                                    $all = array_merge($systemPlaceholders, $custom);

                                    $badges = collect($all)->map(fn ($p) =>
                                        '<span style="display:inline-block;padding:2px 8px;margin:2px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:4px;font-family:monospace;font-size:12px;cursor:pointer;" title="Klicken zum Kopieren">' . e($p) . '</span>'
                                    )->join('');

                                    return new HtmlString(
                                        '<details style="margin-bottom:8px;">'
                                        . '<summary style="cursor:pointer;font-weight:600;color:#4f46e5;">Verfuegbare Platzhalter anzeigen</summary>'
                                        . '<div style="margin-top:8px;">' . $badges . '</div>'
                                        . '</details>'
                                    );
                                })
                                ->columnSpanFull(),

                            RichEditor::make('content')
                                ->label('Dokumentinhalt')
                                ->required()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'blockquote', 'link',
                                    'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Kopf / Fuss')
                        ->icon('heroicon-o-bars-3')
                        ->schema([
                            Textarea::make('header_html')
                                ->label('Briefkopf (HTML)')
                                ->rows(4)
                                ->helperText('HTML fuer den Briefkopf — wird ueber dem Dokumentinhalt angezeigt'),
                            Textarea::make('footer_html')
                                ->label('Fusszeile (HTML)')
                                ->rows(4)
                                ->helperText('HTML fuer die Fusszeile — wird unter dem Dokumentinhalt angezeigt'),
                        ]),

                    Tab::make('Variablen')
                        ->icon('heroicon-o-variable')
                        ->schema([
                            KeyValue::make('variables')
                                ->label('Verwendete Platzhalter')
                                ->keyLabel('Platzhalter')
                                ->valueLabel('Beschreibung')
                                ->addActionLabel('Platzhalter hinzufuegen')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'employment_contract' => 'Arbeitsvertrag',
                        'termination' => 'Kuendigung',
                        'warning' => 'Abmahnung',
                        'work_certificate' => 'Arbeitszeugnis',
                        'interim_certificate' => 'Zwischenzeugnis',
                        'amendment' => 'Aenderungsvertrag',
                        'instruction' => 'Belehrung',
                        'vacation_request' => 'Urlaubsantrag',
                        'salary_adjustment' => 'Gehaltsanpassung',
                        default => $state,
                    })
                    ->color('primary'),

                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'hr' => 'Personal',
                        'onboarding' => 'Onboarding',
                        'legal' => 'Rechtlich',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'onboarding' => 'success',
                        'legal' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('scope')
                    ->label('Herkunft')
                    ->badge()
                    ->getStateUsing(fn (DocumentTemplate $record) => $record->isGlobal() ? 'system' : 'eigene')
                    ->formatStateUsing(fn (string $state) => $state === 'system' ? 'System' : 'Eigene')
                    ->color(fn (string $state) => $state === 'system' ? 'info' : 'success'),

                BooleanColumn::make('is_active')
                    ->label('Aktiv'),

                BooleanColumn::make('is_onboarding')
                    ->label('Onboarding')
                    ->trueIcon('heroicon-o-clipboard-document-check')
                    ->falseIcon('heroicon-o-minus'),

                TextColumn::make('documents_count')
                    ->label('Dokumente')
                    ->counts('documents')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                // PDF-Vorschau
                Actions\Action::make('preview_pdf')
                    ->label('PDF Vorschau')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (DocumentTemplate $record) => route('template.preview', $record->id))
                    ->openUrlInNewTab(),
                Actions\EditAction::make()
                    ->visible(fn (DocumentTemplate $record) => ! $record->isGlobal()),
                Actions\ViewAction::make(),
                // Duplizieren — System-Vorlagen als eigene Kopie
                Actions\Action::make('duplicate')
                    ->label('Duplizieren')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Vorlage duplizieren')
                    ->modalDescription('Erstellt eine bearbeitbare Kopie dieser Vorlage fuer Ihren Mandanten.')
                    ->action(function (DocumentTemplate $record) {
                        $copy = $record->replicate();
                        $copy->tenant_id = session('tenant_id');
                        $copy->name = $record->name . ' (Kopie)';
                        $copy->is_default = false;
                        $copy->is_system = false;
                        $copy->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Vorlage dupliziert')
                            ->body("\"{$copy->name}\" wurde als eigene Vorlage erstellt.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return DocumentTemplate::forCurrentTenant();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
