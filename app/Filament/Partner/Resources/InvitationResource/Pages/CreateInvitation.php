<?php

namespace App\Filament\Partner\Resources\InvitationResource\Pages;

use App\Filament\Partner\Resources\InvitationResource;
use App\Models\Invitation;
use App\Services\OnboardingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInvitation extends CreateRecord
{
    protected static string $resource = InvitationResource::class;

    /**
     * Vor dem Erstellen: Token generieren, invited_by setzen.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Invitation::generateToken();
        $data['invited_by'] = auth()->id();
        $data['tenant_id'] = session('tenant_id');

        return $data;
    }

    /**
     * Nach dem Erstellen: Einladungs-E-Mail versenden.
     */
    protected function afterCreate(): void
    {
        // E-Mail versenden
        try {
            app(OnboardingService::class)->resendInvitation($this->record);

            Notification::make()
                ->title(__('partner.invitation.messages.sent'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Einladung erstellt, aber E-Mail konnte nicht versendet werden.')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
