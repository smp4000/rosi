<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aenderungshistorie fuer Artikel (CSV-Import-Log).
 */
class ArticleChange extends \Illuminate\Database\Eloquent\Model
{
    /**
     * Nur created_at, kein updated_at.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'article_id',
        'article_import_id',
        'change_type',
        'field_name',
        'old_value',
        'new_value',
    ];

    // ── Relationships ──

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function articleImport(): BelongsTo
    {
        return $this->belongsTo(ArticleImport::class);
    }
}
