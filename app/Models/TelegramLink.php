<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Telegram-Verknuepfung zwischen ROSI-User und Telegram-Account.
 * Token-basiert: Mitarbeiter klickt /start {token} im Bot.
 */
class TelegramLink extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_first_name',
        'link_token',
        'link_token_expires_at',
        'linked_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'link_token_expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // --- Beziehungen ---

    /**
     * Zugehoeriger ROSI-Benutzer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Hilfsmethoden ---

    /**
     * Ist der Telegram-Account erfolgreich verknuepft?
     */
    public function isLinked(): bool
    {
        return $this->linked_at !== null && $this->telegram_chat_id !== null;
    }

    /**
     * Ist der Link-Token noch gueltig?
     */
    public function isTokenValid(): bool
    {
        return $this->link_token_expires_at && $this->link_token_expires_at->isFuture();
    }

    /**
     * Neuen Link-Token generieren (z.B. fuer erneute Verknuepfung).
     */
    public function regenerateToken(int $expiresInHours = 48): self
    {
        $this->update([
            'link_token' => Str::random(64),
            'link_token_expires_at' => now()->addHours($expiresInHours),
        ]);

        return $this;
    }

    /**
     * Telegram-Account verknuepfen (nach /start im Bot).
     */
    public function linkTelegram(int $chatId, ?string $username, ?string $firstName): self
    {
        $this->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
            'telegram_first_name' => $firstName,
            'linked_at' => now(),
            'is_active' => true,
        ]);

        return $this;
    }

    /**
     * Telegram-Verknuepfung aufheben.
     */
    public function unlinkTelegram(): self
    {
        $this->update([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_first_name' => null,
            'linked_at' => null,
            'is_active' => false,
        ]);

        return $this;
    }

    /**
     * TelegramLink anhand eines gueltigen Tokens finden.
     */
    public static function findByValidToken(string $token): ?self
    {
        return static::where('link_token', $token)
            ->where('link_token_expires_at', '>', now())
            ->first();
    }

    /**
     * Neuen TelegramLink fuer einen User erstellen.
     */
    public static function createForUser(User $user, int $expiresInHours = 48): self
    {
        return static::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'link_token' => Str::random(64),
            'link_token_expires_at' => now()->addHours($expiresInHours),
        ]);
    }
}
