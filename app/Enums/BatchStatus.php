<?php

namespace App\Enums;

/**
 * Import-Batch-Status.
 */
enum BatchStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Wartend',
            self::PROCESSING => 'Verarbeitung',
            self::COMPLETED => 'Abgeschlossen',
            self::FAILED => 'Fehlgeschlagen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
        };
    }
}
