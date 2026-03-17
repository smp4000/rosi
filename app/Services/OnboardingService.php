<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\EmployeeProfile;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

/**
 * Service fuer den Mitarbeiter-Onboarding-Prozess.
 * Verwaltet Einladungen, Token-Validierung und Account-Erstellung.
 */
class OnboardingService
{
    public function __construct(
        protected ConsentService $consentService,
        protected AuditService $auditService,
    ) {}

    /**
     * Neue Einladung erstellen und per E-Mail versenden.
     */
    public function createInvitation(User $invitedBy, array $data): Invitation
    {
        $invitation = Invitation::create([
            'tenant_id' => $invitedBy->tenant_id,
            'email' => $data['email'],
            'token' => Invitation::generateToken(),
            'type' => $data['type'] ?? 'employee',
            'role_id' => $data['role_id'] ?? null,
            'gas_station_ids' => $data['gas_station_ids'] ?? [],
            'invited_by' => $invitedBy->id,
            'message' => $data['message'] ?? null,
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
        ]);

        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Token validieren und Einladung zurueckgeben.
     */
    public function validateToken(string $token): ?Invitation
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Onboarding abschliessen: User + Profil erstellen, Rolle zuweisen.
     */
    public function completeOnboarding(Invitation $invitation, array $profileData, string $password): User
    {
        return DB::transaction(function () use ($invitation, $profileData, $password) {
            // 1. User erstellen
            $user = User::create([
                'tenant_id' => $invitation->tenant_id,
                'first_name' => $profileData['first_name'] ?? null,
                'last_name' => $profileData['last_name'],
                'email' => $invitation->email,
                'password' => $password,
                'type' => 'employee',
                'is_active' => true,
                'email_verified_at' => now(), // Einladung gilt als Verifizierung
            ]);

            // 2. EmployeeProfile erstellen
            $profile = EmployeeProfile::create(array_merge(
                $profileData,
                [
                    'user_id' => $user->id,
                    'tenant_id' => $invitation->tenant_id,
                    'status' => 'complete',
                    'completed_at' => now(),
                ]
            ));

            // 3. Rolle zuweisen (Team-Scope setzen)
            app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->tenant_id);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            if ($invitation->role_id) {
                $role = \Spatie\Permission\Models\Role::find($invitation->role_id);
                if ($role) {
                    $user->assignRole($role);
                }
            } else {
                // Standard: Mitarbeiter-Rolle
                $user->assignRole('mitarbeiter');
            }

            // 4. Tankstellen zuweisen
            if (! empty($invitation->gas_station_ids)) {
                $stationData = [];
                foreach ($invitation->gas_station_ids as $index => $stationId) {
                    $stationData[$stationId] = [
                        'station_role' => $invitation->type === 'manager' ? 'manager' : 'employee',
                        'is_primary' => $index === 0,
                        'assigned_at' => now(),
                    ];
                }
                $user->gasStations()->attach($stationData);
            }

            // 5. Einladung als angenommen markieren
            $invitation->markAsAccepted();

            // 6. DSGVO-Einwilligungen protokollieren
            $this->consentService->recordRegistrationConsents($user);

            // 7. Audit-Log
            $this->auditService->log(
                action: 'create',
                auditable: $user,
                newValues: ['type' => 'employee', 'email' => $user->email],
                reason: 'Mitarbeiter-Onboarding via Einladung abgeschlossen',
            );

            return $user;
        });
    }

    /**
     * Einladungs-E-Mail (erneut) versenden.
     */
    public function resendInvitation(Invitation $invitation): void
    {
        if (! $invitation->isValid()) {
            throw new \RuntimeException('Einladung ist nicht mehr gueltig.');
        }

        $this->sendInvitationEmail($invitation);
    }

    /**
     * E-Mail mit Einladungslink versenden.
     */
    protected function sendInvitationEmail(Invitation $invitation): void
    {
        Mail::to($invitation->email)->send(new InvitationMail($invitation));
    }
}
