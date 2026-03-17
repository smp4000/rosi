<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumentvorlagen-Verwaltung im Partner-Panel.
 * Globale System-Vorlagen (nur lesen) + mandantenspezifische Vorlagen (CRUD).
 */
class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Dokumente';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Vorlage';

    protected static ?string $pluralModelLabel = 'Vorlagen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('partner.templates.list') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        // Globale Templates sind nicht editierbar
        if ($record->isGlobal()) {
            return false;
        }

        return auth()->user()?->can('partner.templates.edit') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        if ($record->isGlobal()) {
            return false;
        }

        return auth()->user()?->can('partner.templates.delete') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Vorlage')
                ->tabs([
                    Tab::make(__('partner.template.fields.name'))
                        ->icon('heroicon-o-document')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('partner.template.fields.name'))
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->label(__('partner.template.fields.type'))
                                ->options(__('partner.document.types'))
                                ->required(),
                            Select::make('category')
                                ->label(__('partner.template.fields.category'))
                                ->options(__('partner.template.categories'))
                                ->default('hr'),
                            TextInput::make('description')
                                ->label(__('partner.template.fields.description'))
                                ->maxLength(500),
                            Toggle::make('is_active')
                                ->label(__('partner.template.fields.is_active'))
                                ->default(true),
                            Toggle::make('is_default')
                                ->label(__('partner.template.fields.is_default')),
                        ])
                        ->columns(2),

                    Tab::make(__('partner.template.fields.content'))
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            RichEditor::make('content')
                                ->label(__('partner.template.fields.content'))
                                ->required()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'blockquote', 'codeBlock',
                                    'link', 'undo', 'redo',
                                ])
                                ->helperText('Verfuegbare Platzhalter: {{mitarbeiter_name}}, {{mitarbeiter_adresse}}, {{arbeitgeber_name}}, {{eintrittsdatum}}, {{gehalt}}, {{wochenstunden}}, {{urlaubstage}}, {{datum_heute}}, {{ort_datum}}')
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Kopf/Fuss')
                        ->icon('heroicon-o-bars-3')
                        ->schema([
                            Textarea::make('header_html')
                                ->label(__('partner.template.fields.header'))
                                ->rows(4)
                                ->helperText('HTML fuer den Briefkopf'),
                            Textarea::make('footer_html')
                                ->label(__('partner.template.fields.footer'))
                                ->rows(4)
                                ->helperText('HTML fuer die Fusszeile'),
                        ]),

                    Tab::make(__('partner.template.fields.variables'))
                        ->icon('heroicon-o-variable')
                        ->schema([
                            KeyValue::make('variables')
                                ->label(__('partner.template.fields.variables'))
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
                    ->label(__('partner.template.fields.name'))
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('type')
                    ->label(__('partner.template.fields.type'))
                    ->formatStateUsing(fn (string $state) => __("partner.document.types.{$state}") ?? $state)
                    ->color('primary'),

                TextColumn::make('scope')
                    ->label('Herkunft')
                    ->badge()
                    ->getStateUsing(fn (DocumentTemplate $record) => $record->isGlobal() ? 'system' : 'tenant')
                    ->formatStateUsing(fn (string $state) => $state === 'system'
                        ? __('partner.template.global_badge')
                        : __('partner.template.tenant_badge'))
                    ->color(fn (string $state) => $state === 'system' ? 'info' : 'success'),

                BooleanColumn::make('is_active')
                    ->label(__('partner.template.fields.is_active')),

                BooleanColumn::make('is_default')
                    ->label(__('partner.template.fields.is_default')),

                TextColumn::make('documents_count')
                    ->label(__('partner.document.plural'))
                    ->counts('documents')
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (DocumentTemplate $record) => ! $record->isGlobal()),
                Actions\ViewAction::make(),
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
