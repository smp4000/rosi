<?php

namespace App\Enums;

/**
 * E-Mail-Versandstatus.
 */
enum EmailStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ausstehend',
            self::SENT => 'Versendet',
            self::FAILED => 'Fehlgeschlagen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }
}
