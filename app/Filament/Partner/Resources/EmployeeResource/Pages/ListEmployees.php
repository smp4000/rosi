<?php

namespace App\Filament\Partner\Resources\EmployeeResource\Pages;

use App\Filament\Partner\Resources\EmployeeResource;
use App\Filament\Partner\Resources\InvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Neu anlegen')
                ->icon('heroicon-o-user-plus')
                ->visible(fn () => auth()->user()?->can('partner.employees.create')),

            Actions\Action::make('invite')
                ->label(__('partner.employee.actions.invite'))
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->url(InvitationResource::getUrl('create'))
                ->visible(fn () => auth()->user()?->can('partner.invitations.create')),
        ];
    }
}
