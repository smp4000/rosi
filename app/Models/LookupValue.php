<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generische Lookup-Tabelle fuer Dropdowns mit "Neu anlegen"-Funktion.
 * Wird verwendet fuer: Waschanlage-Marken, Waschanlage-Typen etc.
 */
class LookupValue extends Model
{
    protected $fillable = [
        'category',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope: Nur Werte einer bestimmten Kategorie.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderBy('sort_order')->orderBy('value');
    }
}
