<?php

namespace App\Enums;

/**
 * Druck-Status.
 */
enum PrintStatus: string
{
    case PENDING = 'pending';
    case PRINTED = 'printed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ausstehend',
            self::PRINTED => 'Gedruckt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PRINTED => 'success',
        };
    }
}
