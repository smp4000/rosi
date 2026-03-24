<?php

namespace App\Enums;

/**
 * Rechnungsstatus (Gesamtstatus der Rechnung).
 */
enum InvoiceStatus: string
{
    case UPLOADED = 'uploaded';
    case PROCESSED = 'processed';
    case SENT = 'sent';
    case PRINTED = 'printed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'Hochgeladen',
            self::PROCESSED => 'Verarbeitet',
            self::SENT => 'Versendet',
            self::PRINTED => 'Gedruckt',
            self::FAILED => 'Fehlgeschlagen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UPLOADED => 'gray',
            self::PROCESSED => 'info',
            self::SENT => 'success',
            self::PRINTED => 'success',
            self::FAILED => 'danger',
        };
    }
}
