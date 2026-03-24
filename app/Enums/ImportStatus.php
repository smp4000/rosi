<?php

namespace App\Enums;

/**
 * Import-Status (Ergebnis des PDF-Imports).
 */
enum ImportStatus: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case DUPLICATE = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Erfolgreich',
            self::ERROR => 'Fehler',
            self::DUPLICATE => 'Duplikat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::ERROR => 'danger',
            self::DUPLICATE => 'warning',
        };
    }
}
