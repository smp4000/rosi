<?php

namespace App\Filament\Partner\Resources\DocumentResource\Pages;

use App\Filament\Components\SignaturePad;
use App\Filament\Partner\Resources\DocumentResource;
use App\Services\DocumentService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Gegenzeichnen-Button mit Modal + SignaturePad
            Actions\Action::make('counter_sign')
                ->label('Gegenzeichnen')
                ->icon('heroicon-o-pencil')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Dokument gegenzeichnen')
                ->modalDescription('Bitte unterschreiben Sie im Feld unten, um das Dokument zu gegenzeichnen.')
                ->modalSubmitActionLabel('Gegenzeichnen')
                ->modalWidth('2xl')
                ->form([
                    SignaturePad::make('signature')
                        ->label('Ihre Unterschrift')
                        ->confirmationText('Ich bestaetige hiermit die Gegenzeichnung dieses Dokuments.')
                        ->height(150)
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(DocumentService::class)->counterSignDocument(
                        $this->record,
                        $data['signature'],
                        auth()->user(),
                    );

                    // PDF mit Signatur neu generieren
                    app(DocumentService::class)->generatePdf($this->record->fresh());

                    Notification::make()
                        ->title('Dokument wurde gegengezeichnet.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isSigned() && ! $this->record->isCounterSigned()),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
