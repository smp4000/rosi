<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

/**
 * User-Model mit UUID, Mandantenzuordnung und Rollen.
 * Benutzertypen: super_admin, partner, employee, customer
 * Auditable: Alle Aenderungen werden im Audit-Log protokolliert.
 */
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasFactory, HasUuids, HasApiTokens, Notifiable, SoftDeletes, HasRoles, Auditable;

    /**
     * Felder, die vom Audit-Logging ausgeschlossen werden.
     */
    protected array $auditExclude = ['last_login_at', 'remember_token'];

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'type',
        'avatar',
        'phone',
        'is_active',
        'locale',
        'last_login_at',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'pin_hash',
        'scan_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
        ];
    }

    // --- Accessors ---

    /**
     * Vollstaendiger Name (automatisch zusammengesetzt).
     * Kein DB-Feld, wird berechnet aus first_name + last_name.
     */
    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . $this->last_name);
    }

    // --- Filament ---

    /**
     * Filament-Zugriff: Nur Super-Admins duerfen ins Admin-Panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->isSuperAdmin();
        }

        if ($panel->getId() === 'partner') {
            return $this->isPartner() || $this->isEmployee();
        }

        return false;
    }

    // --- Beziehungen ---

    /**
     * Mandant des Benutzers (nullable fuer Super-Admin).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mandant dessen Inhaber dieser User ist.
     */
    public function ownedTenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'owner_id');
    }

    /**
     * Personalbogen des Mitarbeiters.
     */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * Zugewiesene Tankstellen (Many-to-Many).
     */
    public function gasStations(): BelongsToMany
    {
        return $this->belongsToMany(GasStation::class, 'gas_station_user')
            ->withPivot(['station_role', 'is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Haupttankstelle des Mitarbeiters.
     */
    public function primaryGasStation(): BelongsToMany
    {
        return $this->gasStations()->wherePivot('is_primary', true);
    }

    /**
     * Stations-Rollen-Zuweisungen (Rolle pro Tankstelle, NULL = ganzer Betrieb).
     */
    public function stationRoles(): HasMany
    {
        return $this->hasMany(EmployeeStationRole::class);
    }

    /**
     * Alle Rollen, die fuer eine bestimmte Tankstelle gelten:
     * stationsgebundene Rollen + betriebsweite Zuweisungen + direkte Spatie-Rollen
     * (z.B. partner/buero fuer Dashboard-Nutzer).
     */
    public function rolesForStation(?string $stationId): \Illuminate\Support\Collection
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->tenant_id);

        $zuweisungen = $this->stationRoles()
            ->where(function ($q) use ($stationId) {
                $q->whereNull('gas_station_id');
                if ($stationId) {
                    $q->orWhere('gas_station_id', $stationId);
                }
            })
            ->with('role.permissions')
            ->get()
            ->pluck('role')
            ->filter();

        return $zuweisungen->merge($this->roles)->unique('id')->values();
    }

    /**
     * Effektive Permission-Namen fuer eine Tankstelle (fuer Login-Response
     * der POS-App und die API-Middleware).
     */
    public function permissionsForStation(?string $stationId): array
    {
        return $this->rolesForStation($stationId)
            ->flatMap(fn ($rolle) => $rolle->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Hat der Mitarbeiter eine bestimmte Permission an dieser Station?
     */
    public function hasStationPermission(string $permission, ?string $stationId): bool
    {
        return in_array($permission, $this->permissionsForStation($stationId), true);
    }

    /**
     * Bankkonten des Mitarbeiters.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class)->orderBy('sort_order');
    }

    /**
     * Dokumente die diesem User zugeordnet sind.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Erstelle Dokumente.
     */
    public function createdDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    /**
     * KI-Chat-Nachrichten.
     */
    public function aiChatMessages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class);
    }

    /**
     * Versendete Einladungen.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    /**
     * DSGVO: Einwilligungs-Protokolle.
     */
    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }

    /**
     * DSGVO: Audit-Log-Eintraege (eigene Aktionen).
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * DSGVO: Loeschantraege.
     */
    public function dataDeletionRequests(): HasMany
    {
        return $this->hasMany(DataDeletionRequest::class);
    }

    /**
     * Telegram-Verknuepfung des Benutzers.
     */
    public function telegramLink(): HasOne
    {
        return $this->hasOne(TelegramLink::class);
    }

    /**
     * Chat-Konversationen als Teilnehmer 1.
     */
    public function conversationsAsOne(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'participant_one_id');
    }

    /**
     * Chat-Konversationen als Teilnehmer 2.
     */
    public function conversationsAsTwo(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'participant_two_id');
    }

    /**
     * Gesendete Chat-Nachrichten.
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    // --- Messaging ---

    /**
     * Telegram Chat-ID fuer Notifications (Laravel Notification Channel).
     */
    public function routeNotificationForTelegram(?Notification $notification = null): ?int
    {
        $link = $this->telegramLink;

        if ($link && $link->isLinked() && $link->is_active) {
            return $link->telegram_chat_id;
        }

        return null;
    }

    /**
     * Bevorzugter Kommunikationskanal des Benutzers.
     * Fallback: E-Mail.
     */
    public function preferredChannel(): string
    {
        return $this->employeeProfile?->preferred_channel ?? 'email';
    }

    /**
     * Hat der Benutzer Telegram verknuepft?
     */
    public function hasTelegram(): bool
    {
        return $this->telegramLink?->isLinked() ?? false;
    }

    // --- Typ-Pruefer ---

    /**
     * Ist der Benutzer ein Super-Admin?
     */
    public function isSuperAdmin(): bool
    {
        return $this->type === 'super_admin';
    }

    /**
     * Ist der Benutzer ein Tankstellenpartner?
     */
    public function isPartner(): bool
    {
        return $this->type === 'partner';
    }

    /**
     * Ist der Benutzer ein Mitarbeiter?
     */
    public function isEmployee(): bool
    {
        return $this->type === 'employee';
    }

    /**
     * Ist der Benutzer ein Kunde?
     */
    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }

    /**
     * Hat der Benutzer Zugang? (Aktiv + Mandant hat Zugang)
     */
    public function hasAccess(): bool
    {
        // Super-Admin hat immer Zugang
        if ($this->isSuperAdmin()) {
            return $this->is_active;
        }

        // Andere: Aktiv + Mandant hat Zugang
        return $this->is_active
            && $this->tenant
            && $this->tenant->hasAccess();
    }
}
