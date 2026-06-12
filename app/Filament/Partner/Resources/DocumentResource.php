<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Components\SignaturePad;
use App\Filament\Partner\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumenten-Verwaltung im Partner-Panel.
 * CRUD, PDF-Generierung, Signatur, Versand.
 */
class DocumentResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.documents';

    protected static ?string $model = Document::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Dokument';

    protected static ?string $pluralModelLabel = 'Dokumente';

    protected static ?string $recordTitleAttribute = 'title';

    // Autorisierung laeuft komplett ueber HasCatalogPermissions (Rollen-Matrix)

    public static function form(Schema $schema): Schema
    {
        $tenantId = session('tenant_id');

        return $schema->components([
            Tabs::make('Dokument')
                ->tabs([
                    Tab::make(__('partner.document.label'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextInput::make('title')
                                ->label(__('partner.document.fields.title'))
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->label(__('partner.document.fields.type'))
                                ->options(__('partner.document.types'))
                                ->required(),
                            Select::make('user_id')
                                ->label(__('partner.document.fields.employee'))
                                ->options(fn () => User::where('tenant_id', $tenantId)
                                    ->where('type', 'employee')
                                    ->get()
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            Select::make('template_id')
                                ->label(__('partner.document.fields.template'))
                                ->options(fn () => DocumentTemplate::forCurrentTenant()
                                    ->active()
                                    ->pluck('name', 'id'))
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $template = DocumentTemplate::find($state);
                                        if ($template) {
                                            $set('content', $template->content);
                                            $set('type', $template->type);
                                        }
                                    }
                                }),
                            Toggle::make('requires_signature')
                                ->label(__('partner.document.fields.requires_signature'))
                                ->default(true)
                                ->columnSpan(2),
                            RichEditor::make('content')
                                ->label(__('partner.document.fields.content'))
                                ->required()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'blockquote',
                                    'link', 'undo', 'redo',
                                ])
                                ->helperText('Platzhalter werden beim Erstellen automatisch ersetzt.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tab::make('Signatur')
                        ->icon('heroicon-o-pencil')
                        ->schema([
                            // --- Mitarbeiter-Unterschrift ---
                            Placeholder::make('signature_info')
                                ->label(__('partner.document.fields.signed_at'))
                                ->content(fn ($record) => $record?->signed_at
                                    ? $record->signed_at->format('d.m.Y H:i') . ' von ' . ($record->signed_by_name ?? '-')
                                    : 'Noch nicht unterschrieben'),

                            // Mitarbeiter-Signatur anzeigen (wenn vorhanden)
                            Placeholder::make('signature_preview')
                                ->label('Unterschrift Mitarbeiter')
                                ->content(fn ($record) => $record?->signature_data
                                    ? new \Illuminate\Support\HtmlString(
                                        '<img src="' . $record->signature_data . '" alt="Unterschrift" class="max-h-20 border rounded p-1 bg-white">'
                                    )
                                    : '-')
                                ->visible(fn ($record) => $record?->signature_data),

                            // --- Gegenzeichnung ---
                            Placeholder::make('counter_signature_info')
                                ->label(__('partner.document.fields.counter_signed_at'))
                                ->content(fn ($record) => $record?->counter_signed_at
                                    ? $record->counter_signed_at->format('d.m.Y H:i') . ' von ' . (auth()->user()?->name ?? '-')
                                    : 'Noch nicht gegengezeichnet'),

                            // Gegenzeichnung anzeigen (wenn vorhanden)
                            Placeholder::make('counter_signature_preview')
                                ->label('Gegenzeichnung')
                                ->content(fn ($record) => $record?->counter_signature_data
                                    ? new \Illuminate\Support\HtmlString(
                                        '<img src="' . $record->counter_signature_data . '" alt="Gegenzeichnung" class="max-h-20 border rounded p-1 bg-white">'
                                    )
                                    : '-')
                                ->visible(fn ($record) => $record?->counter_signature_data),

                            // Hinweis: Gegenzeichnen ueber den Header-Button "Gegenzeichnen" (Modal mit SignaturePad)
                        ]),

                    Tab::make('Versand')
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Placeholder::make('sent_info')
                                ->label(__('partner.document.fields.sent_at'))
                                ->content(fn ($record) => $record?->sent_at
                                    ? $record->sent_at->format('d.m.Y H:i') . ' an ' . ($record->sent_to_email ?? '-')
                                    : 'Noch nicht versendet'),
                            Placeholder::make('status_info')
                                ->label(__('partner.document.fields.status'))
                                ->content(fn ($record) => $record?->status
                                    ? __("partner.document.statuses.{$record->status}")
                                    : '-'),
                        ]),

                    Tab::make(__('partner.document.fields.notes'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Textarea::make('notes')
                                ->label(__('partner.document.fields.notes'))
                                ->rows(4)
                                ->columnSpanFull(),
                            Placeholder::make('version_info')
                                ->label(__('partner.document.fields.version'))
                                ->content(fn ($record) => $record?->version ?? 1),
                            Placeholder::make('created_by_info')
                                ->label(__('partner.document.fields.created_by'))
                                ->content(fn ($record) => $record?->createdByUser?->name ?? '-'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('partner.document.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('type')
                    ->label(__('partner.document.fields.type'))
                    ->formatStateUsing(fn (string $state) => __("partner.document.types.{$state}") ?? $state)
                    ->color('primary'),

                TextColumn::make('user.last_name')
                    ->label(__('partner.document.fields.employee'))
                    ->formatStateUsing(fn ($record) => $record->user?->name ?? '-')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label(__('partner.document.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("partner.document.statuses.{$state}") ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending_signature',
                        'info' => 'sent',
                        'success' => 'signed',
                        'primary' => 'counter_signed',
                        'secondary' => 'archived',
                        'danger' => 'revoked',
                    ]),

                TextColumn::make('signed_at')
                    ->label(__('partner.document.fields.signed_at'))
                    ->dateTime('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('partner.document.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('partner.document.fields.status'))
                    ->options(__('partner.document.statuses')),
                SelectFilter::make('type')
                    ->label(__('partner.document.fields.type'))
                    ->options(__('partner.document.types')),
            ])
            ->actions([
                // PDF generieren
                Actions\Action::make('generate_pdf')
                    ->label(__('partner.document.actions.generate_pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (Document $record) {
                        app(DocumentService::class)->generatePdf($record);
                        Notification::make()->title('PDF wurde generiert.')->success()->send();
                    })
                    ->visible(fn (Document $record) => ! $record->file_path),

                // PDF herunterladen
                Actions\Action::make('download_pdf')
                    ->label(__('partner.document.actions.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Document $record) {
                        $path = Storage::disk('local')->path($record->file_path);
                        $filename = str($record->title)->slug() . '.pdf';

                        return response()->download($path, $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
                    })
                    ->visible(fn (Document $record) => (bool) $record->file_path),

                // Per E-Mail senden
                Actions\Action::make('send')
                    ->label(__('partner.document.actions.send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('email')
                            ->label('E-Mail-Adresse')
                            ->email()
                            ->required()
                            ->default(fn (Document $record) => $record->user?->email),
                    ])
                    ->action(function (Document $record, array $data) {
                        app(DocumentService::class)->sendDocument($record, $data['email']);
                        Notification::make()->title('Dokument wurde versendet.')->success()->send();
                    })
                    ->visible(fn (Document $record) => auth()->user()?->can('partner.documents.send')),

                // Gegenzeichnen
                Actions\Action::make('counter_sign')
                    ->label(__('partner.document.actions.counter_sign'))
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->form([
                        SignaturePad::make('signature')
                            ->label('Ihre Unterschrift')
                            ->confirmationText('Ich bestaettige hiermit die Gegenzeichnung dieses Dokuments.')
                            ->required(),
                    ])
                    ->action(function (Document $record, array $data) {
                        app(DocumentService::class)->counterSignDocument(
                            $record,
                            $data['signature'],
                            auth()->user(),
                        );
                        // PDF mit Signatur neu generieren
                        app(DocumentService::class)->generatePdf($record->fresh());
                        Notification::make()->title('Dokument wurde gegengezeichnet.')->success()->send();
                    })
                    ->visible(fn (Document $record) => $record->isSigned() && ! $record->isCounterSigned()
                        && auth()->user()?->can('partner.documents.sign')),

                // Widerrufen
                Actions\Action::make('revoke')
                    ->label(__('partner.document.actions.revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Document $record) {
                        app(DocumentService::class)->revokeDocument($record);
                        Notification::make()->title('Dokument wurde widerrufen.')->warning()->send();
                    })
                    ->visible(fn (Document $record) => ! in_array($record->status, ['revoked', 'archived'])),

                // Loeschen mit doppelter Sicherheitsabfrage
                Actions\Action::make('delete_document')
                    ->label('Loeschen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Dokument unwiderruflich loeschen')
                    ->modalDescription(fn (Document $record) => "Sie sind dabei, das Dokument \"{$record->title}\" endgueltig zu loeschen. Diese Aktion kann NICHT rueckgaengig gemacht werden.")
                    ->modalSubmitActionLabel('Endgueltig loeschen')
                    ->form([
                        TextInput::make('_confirm_delete')
                            ->label('Zur Bestaetigung "LOESCHEN" eingeben')
                            ->required()
                            ->rules(['in:LOESCHEN'])
                            ->validationMessages([
                                'in' => 'Bitte geben Sie exakt "LOESCHEN" ein.',
                            ]),
                    ])
                    ->action(function (Document $record) {
                        // PDF-Datei loeschen
                        if ($record->file_path && Storage::disk('local')->exists($record->file_path)) {
                            Storage::disk('local')->delete($record->file_path);
                        }

                        $title = $record->title;
                        $record->delete();

                        Notification::make()
                            ->title('Dokument geloescht')
                            ->body("\"{$title}\" wurde endgueltig geloescht.")
                            ->success()
                            ->send();
                    }),

                // Zurueck zum Personalblatt
                Actions\Action::make('go_to_employee')
                    ->label('Personalblatt')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->url(fn (Document $record) => $record->user_id
                        ? \App\Filament\Partner\Resources\EmployeeResource::getUrl('edit', ['record' => $record->user_id])
                        : null)
                    ->visible(fn (Document $record) => (bool) $record->user_id),

                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', session('tenant_id'))
            ->with(['user', 'template', 'createdByUser']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
            'view' => Pages\ViewDocument::route('/{record}'),
        ];
    }
}
