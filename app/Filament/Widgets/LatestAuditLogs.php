<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dashboard-Widget: Letzte Aktivitaeten im Audit-Log.
 */
class LatestAuditLogs extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string|null
    {
        return __('admin.dashboard.latest_activity');
    }

    public static function canView(): bool
    {
        return auth()->user()->can('admin.audit-logs.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()
                    ->with('user')
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.audit_log.fields.created_at'))
                    ->dateTime('d.m.Y H:i'),

                TextColumn::make('user.last_name')
                    ->label(__('admin.audit_log.fields.user'))
                    ->formatStateUsing(fn ($record) => $record->user?->name ?? 'System')
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
                    ]),

                TextColumn::make('auditable_type')
                    ->label(__('admin.audit_log.fields.auditable_type'))
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
            ])
            ->paginated([5])
            ->defaultSort('created_at', 'desc');
    }
}
