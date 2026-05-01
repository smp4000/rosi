<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer das DSGVO Audit-Log (nur lesend).
 * Verschluesselte Werte nur mit Level-3-Berechtigung sichtbar.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'DSGVO & Audit';

    protected static ?string $modelLabel = 'Audit-Eintrag';

    protected static ?string $pluralModelLabel = 'Audit-Log';

    protected static ?int $navigationSort = 1;

    // --- Autorisierung: Nur lesen ---

    public static function canAccess(): bool
    {
        return auth()->user()->isSuperAdmin() || auth()->user()->can('admin.audit-logs.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    // --- Infolist fuer View-Seite ---

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')
                ->label(__('admin.audit_log.fields.created_at'))
                ->dateTime('d.m.Y H:i:s'),

            TextEntry::make('user.last_name')
                ->label(__('admin.audit_log.fields.user'))
                ->formatStateUsing(fn ($record) => $record->user?->name ?? 'System'),

            TextEntry::make('action')
                ->label(__('admin.audit_log.fields.action'))
                ->formatStateUsing(fn (string $state) => __("admin.audit_log.actions.{$state}", [], 'de') !== "admin.audit_log.actions.{$state}"
                    ? __("admin.audit_log.actions.{$state}")
                    : $state)
                ->badge(),

            TextEntry::make('auditable_type')
                ->label(__('admin.audit_log.fields.auditable_type'))
                ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),

            TextEntry::make('auditable_id')
                ->label(__('admin.audit_log.fields.auditable_id'))
                ->placeholder('-'),

            TextEntry::make('ip_address')
                ->label(__('admin.audit_log.fields.ip_address'))
                ->placeholder('-'),

            TextEntry::make('user_agent')
                ->label(__('admin.audit_log.fields.user_agent'))
                ->placeholder('-')
                ->limit(80),

            TextEntry::make('reason')
                ->label(__('admin.audit_log.fields.reason'))
                ->placeholder('-'),

            // Verschluesselte Werte nur fuer Level 3
            TextEntry::make('old_values')
                ->label(__('admin.audit_log.fields.old_values'))
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                ->visible(fn () => auth()->user()->can('admin.audit-logs.view-values'))
                ->copyable()
                ->prose(),

            TextEntry::make('new_values')
                ->label(__('admin.audit_log.fields.new_values'))
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                ->visible(fn () => auth()->user()->can('admin.audit-logs.view-values'))
                ->copyable()
                ->prose(),

            // Hinweis fuer Level 1/2
            Placeholder::make('restricted_notice')
                ->content(__('admin.audit_log.restricted_notice'))
                ->visible(fn () => ! auth()->user()->can('admin.audit-logs.view-values')),
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.audit_log.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('user.last_name')
                    ->label(__('admin.audit_log.fields.user'))
                    ->formatStateUsing(fn ($record) => $record->user?->name ?? 'System')
                    ->searchable(['user_id'])
                    ->placeholder('System'),

                BadgeColumn::make('action')
                    ->label(__('admin.audit_log.fields.action'))
                    ->formatStateUsing(fn (string $state) => __("admin.audit_log.actions.{$state}", [], 'de') !== "admin.audit_log.actions.{$state}"
                        ? __("admin.audit_log.actions.{$state}")
                        : $state)
                    ->colors([
                        'success' => 'create',
                        'warning' => 'update',
                        'danger' => 'delete',
                        'info' => fn (string $state) => in_array($state, ['read', 'login', 'logout']),
                        'gray' => fn (string $state) => ! in_array($state, ['create', 'update', 'delete', 'read', 'login', 'logout']),
                    ])
                    ->sortable(),

                TextColumn::make('auditable_type')
                    ->label(__('admin.audit_log.fields.auditable_type'))
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-')
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label(__('admin.audit_log.fields.ip_address'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('reason')
                    ->label(__('admin.audit_log.fields.reason'))
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason)
                    ->toggleable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label(__('admin.audit_log.fields.action'))
                    ->options(__('admin.audit_log.actions')),

                SelectFilter::make('user_type')
                    ->label(__('admin.user.fields.type'))
                    ->options(__('admin.user.types')),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Von'),
                        DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
