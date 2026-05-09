<?php

namespace App\Models\Kiosk;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleIssue extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kiosk_article_issues';

    public $timestamps = false;

    protected $fillable = ['article_id', 'ausgabe', 'ean_addon', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
