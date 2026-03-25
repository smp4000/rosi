<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\TenantSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * Versendet E-Mails ueber den passenden SMTP-Server.
 *
 * Reihenfolge:
 * 1. Tenant-SMTP (aus tenant_settings, Gruppe: mail)
 * 2. System-SMTP (aus system_settings, Gruppe: mail)
 * 3. Fallback: Standard-Mailer aus config/mail.php (.env)
 */
class TenantMailerService
{
    /**
     * Mail versenden (Tenant → System → .env Fallback).
     */
    public static function send(Mailable $mailable, string $to, ?string $tenantId = null): void
    {
        $config = static::getSmtpConfig($tenantId);

        if ($config) {
            static::sendViaDynamicSmtp($mailable, $to, $config, 'tenant_dynamic');
            return;
        }

        // Fallback: System-SMTP
        $systemConfig = static::getSystemSmtpConfig();
        if ($systemConfig) {
            static::sendViaDynamicSmtp($mailable, $to, $systemConfig, 'system_dynamic');
            return;
        }

        // Letzter Fallback: .env Mailer
        Mail::to($to)->send($mailable);
    }

    /**
     * Mail explizit ueber System-SMTP versenden (fuer Admin-Bereich).
     */
    public static function sendAsSystem(Mailable $mailable, string $to): void
    {
        $config = static::getSystemSmtpConfig();

        if ($config) {
            static::sendViaDynamicSmtp($mailable, $to, $config, 'system_dynamic');
            return;
        }

        Mail::to($to)->send($mailable);
    }

    /**
     * Teste SMTP-Verbindung.
     */
    public static function testConnection(?string $tenantId = null): true|string
    {
        $config = static::getSmtpConfig($tenantId);

        if (! $config) {
            return 'Keine SMTP-Konfiguration gefunden.';
        }

        return static::testSmtpConfig($config);
    }

    /**
     * Teste eine beliebige SMTP-Config.
     */
    public static function testSmtpConfig(array $config): true|string
    {
        try {
            $factory = new EsmtpTransportFactory();
            $scheme = match ($config['encryption']) {
                'ssl', 'SSL/TLS' => 'smtps',
                'tls', 'STARTTLS' => 'smtp',
                default => 'smtp',
            };

            $dsn = new Dsn(
                $scheme,
                $config['host'],
                $config['username'],
                $config['password'],
                (int) $config['port'],
                ['verify_peer' => '0'],
            );

            $transport = $factory->create($dsn);
            $transport->start();
            $transport->stop();

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Tenant-SMTP-Config lesen.
     */
    public static function getSmtpConfig(?string $tenantId = null): ?array
    {
        $host = TenantSetting::get('smtp_host', null, $tenantId, 'mail');

        if (! $host) {
            return null;
        }

        return [
            'host' => $host,
            'port' => TenantSetting::get('smtp_port', '587', $tenantId, 'mail'),
            'username' => TenantSetting::get('smtp_username', '', $tenantId, 'mail'),
            'password' => TenantSetting::get('smtp_password', '', $tenantId, 'mail'),
            'encryption' => TenantSetting::get('smtp_encryption', 'tls', $tenantId, 'mail'),
            'from_address' => TenantSetting::get('mail_from_address', '', $tenantId, 'mail'),
            'from_name' => TenantSetting::get('mail_from_name', '', $tenantId, 'mail'),
        ];
    }

    /**
     * System-SMTP-Config lesen (Admin).
     */
    public static function getSystemSmtpConfig(): ?array
    {
        $host = SystemSetting::get('smtp_host', null, 'mail');

        if (! $host) {
            return null;
        }

        return [
            'host' => $host,
            'port' => SystemSetting::get('smtp_port', '587', 'mail'),
            'username' => SystemSetting::get('smtp_username', '', 'mail'),
            'password' => SystemSetting::get('smtp_password', '', 'mail'),
            'encryption' => SystemSetting::get('smtp_encryption', 'tls', 'mail'),
            'from_address' => SystemSetting::get('mail_from_address', '', 'mail'),
            'from_name' => SystemSetting::get('mail_from_name', '', 'mail'),
        ];
    }

    /**
     * Mail ueber dynamischen SMTP versenden.
     */
    private static function sendViaDynamicSmtp(Mailable $mailable, string $to, array $config, string $mailerName): void
    {
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $config['host'],
                'port' => (int) $config['port'],
                'username' => $config['username'],
                'password' => $config['password'],
                'encryption' => in_array($config['encryption'], ['ssl', 'SSL/TLS']) ? 'ssl' : 'tls',
                'timeout' => null,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'stream' => [
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ],
            ],
        ]);

        if ($config['from_address']) {
            $mailable->from($config['from_address'], $config['from_name'] ?: null);
        }

        Mail::mailer($mailerName)->to($to)->send($mailable);
    }
}
