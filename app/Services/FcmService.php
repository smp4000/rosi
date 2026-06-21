<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sendet Push-Benachrichtigungen ueber Firebase Cloud Messaging (HTTP v1).
 * Eigenstaendig: erzeugt das OAuth2-Access-Token aus der Service-Account-JSON
 * (JWT/RS256) — kein zusaetzliches Composer-Paket noetig.
 *
 * Konfiguration (config/services.php -> fcm):
 *   credentials = absoluter Pfad zur Service-Account-JSON
 *   project_id  = optional (sonst aus der JSON)
 */
class FcmService
{
    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');
        return ! empty($path) && is_file($path);
    }

    /**
     * Sendet an mehrere Tokens. Liefert Anzahl erfolgreich gesendeter Nachrichten.
     *
     * @param  string[]  $tokens
     * @param  array<string,string>  $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens) || ! $this->isConfigured()) {
            return 0;
        }

        $sa = json_decode((string) file_get_contents(config('services.fcm.credentials')), true);
        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            Log::warning('FCM: Service-Account-JSON ungueltig');
            return 0;
        }

        $projectId = config('services.fcm.project_id') ?: ($sa['project_id'] ?? null);
        $accessToken = $this->accessToken($sa);
        if (! $accessToken || ! $projectId) {
            return 0;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $sent = 0;

        foreach ($tokens as $token) {
            try {
                $resp = Http::withToken($accessToken)
                    ->withoutVerifying()
                    ->timeout(15)
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => array_map('strval', $data),
                            'android' => ['priority' => 'high'],
                        ],
                    ]);
                if ($resp->successful()) {
                    $sent++;
                } else {
                    Log::info('FCM Versand fehlgeschlagen', ['status' => $resp->status(), 'body' => $resp->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM Fehler', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** OAuth2-Access-Token via Service-Account-JWT holen (60 Min gecacht). */
    private function accessToken(array $sa): ?string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(50), function () use ($sa) {
            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signingInput = $header . '.' . $claim;
            $signature = '';
            openssl_sign($signingInput, $signature, $sa['private_key'], 'sha256WithRSAEncryption');
            $jwt = $signingInput . '.' . $this->b64($signature);

            try {
                $resp = Http::asForm()->withoutVerifying()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
                if ($resp->successful()) {
                    return $resp->json('access_token');
                }
                Log::warning('FCM OAuth fehlgeschlagen', ['body' => $resp->body()]);
            } catch (\Throwable $e) {
                Log::warning('FCM OAuth Fehler', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
