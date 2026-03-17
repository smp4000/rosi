<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chat-Konversation zwischen zwei Benutzern (z.B. Partner ↔ Mitarbeiter).
 * UUID als PK, mandantenfaehig.
 */
class ChatConversation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'participant_one_id',
        'participant_two_id',
        'last_message_at',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    // --- Beziehungen ---

    /**
     * Erster Teilnehmer (Partner oder Mitarbeiter).
     */
    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_one_id');
    }

    /**
     * Zweiter Teilnehmer (Partner oder Mitarbeiter).
     */
    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_two_id');
    }

    /**
     * Alle Nachrichten in dieser Konversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    /**
     * Letzte Nachricht der Konversation.
     */
    public function latestMessage(): HasMany
    {
        return $this->messages()->latest('created_at')->limit(1);
    }

    // --- Hilfsmethoden ---

    /**
     * Gegenpartei eines Teilnehmers ermitteln.
     */
    public function getOtherParticipant(User $user): ?User
    {
        if ($this->participant_one_id === $user->id) {
            return $this->participantTwo;
        }

        if ($this->participant_two_id === $user->id) {
            return $this->participantOne;
        }

        return null;
    }

    /**
     * Pruefen ob ein User Teilnehmer ist.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participant_one_id === $user->id
            || $this->participant_two_id === $user->id;
    }

    /**
     * Anzahl ungelesener Nachrichten fuer einen User.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Konversation fuer zwei Teilnehmer finden (Reihenfolge egal).
     */
    public static function findBetween(string $userIdA, string $userIdB): ?self
    {
        return static::where(function ($q) use ($userIdA, $userIdB) {
            $q->where('participant_one_id', $userIdA)
              ->where('participant_two_id', $userIdB);
        })->orWhere(function ($q) use ($userIdA, $userIdB) {
            $q->where('participant_one_id', $userIdB)
              ->where('participant_two_id', $userIdA);
        })->first();
    }
}
