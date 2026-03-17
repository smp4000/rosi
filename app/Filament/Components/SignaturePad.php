<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Field;

/**
 * Custom Filament-Komponente fuer digitale Unterschriften.
 * Nutzt ein Alpine.js Canvas-Widget fuer Touch/Maus-Unterschriften.
 * Speichert die Unterschrift als Base64 PNG-Data-URI.
 */
class SignaturePad extends Field
{
    protected string $view = 'components.signature-pad';

    protected string $confirmationText = '';

    protected int $height = 150;

    /**
     * Bestaetigungstext ueber dem Canvas.
     */
    public function confirmationText(string $text): static
    {
        $this->confirmationText = $text;

        return $this;
    }

    public function getConfirmationText(): string
    {
        return $this->confirmationText;
    }

    /**
     * Hoehe des Canvas in Pixeln.
     */
    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
