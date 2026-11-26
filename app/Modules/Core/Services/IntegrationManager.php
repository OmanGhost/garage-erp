<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Optional third-party integrations configured per company from the UI.
 * Credentials are stored encrypted (encrypted:array cast). Nothing here is
 * mandatory: every integration is disabled until configured.
 */
class IntegrationManager
{
    public const PROVIDERS = [
        'sms' => ['label' => 'SMS Gateway (HTTP)', 'fields' => ['url', 'method', 'username', 'password', 'sender', 'to_param', 'message_param', 'extra_params']],
        'whatsapp' => ['label' => 'WhatsApp Business Cloud API', 'fields' => ['phone_number_id', 'access_token', 'api_version', 'template_language']],
        'payment' => ['label' => 'Payment Gateway', 'fields' => ['driver', 'secret_key', 'publishable_key', 'base_url', 'webhook_secret']],
        'vin' => ['label' => 'VIN Decoder (NHTSA vPIC - free)', 'fields' => ['base_url']],
        'attendance' => ['label' => 'Attendance devices (push API)', 'fields' => ['token']],
        'siem' => ['label' => 'SIEM / Syslog forwarding', 'fields' => ['host', 'port']],
        'accounting_export' => ['label' => 'External accounting export', 'fields' => ['format', 'webhook_url']],
    ];

    public static function active(string $provider): ?Integration
    {
        return Integration::query()->where('provider', $provider)->where('is_active', true)->first();
    }

    public static function credential(string $provider, string $key, mixed $default = null): mixed
    {
        return self::active($provider)?->credentials[$key] ?? $default;
    }

    /** E-mail through the company SMTP, else the platform SMTP, else .env (see MailService). */
    public static function sendMail(string $to, string $subject, string $body): void
    {
        \App\Support\Mail\MailService::send($to, $subject, $body);
    }

    /** Generic HTTP SMS gateway (works with most Omani/GCC providers). */
    public static function sendSms(string $to, string $message): array
    {
        $c = self::active('sms')?->credentials ?? [];
        if (empty($c['url'])) {
            throw new \RuntimeException('SMS gateway not configured');
        }
        $params = array_filter([
            'username' => $c['username'] ?? null, 'password' => $c['password'] ?? null, 'sender' => $c['sender'] ?? null,
            ($c['to_param'] ?? 'to') => ltrim($to, '+'), ($c['message_param'] ?? 'message') => $message,
        ]);
        parse_str((string) ($c['extra_params'] ?? ''), $extra);
        $params = array_merge($params, $extra);
        $res = strtoupper($c['method'] ?? 'POST') === 'GET'
            ? Http::timeout(20)->get($c['url'], $params)
            : Http::timeout(20)->asForm()->post($c['url'], $params);

        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 1000)];
    }

    /** WhatsApp Business Cloud API text message. */
    public static function sendWhatsApp(string $to, string $message): array
    {
        $c = self::active('whatsapp')?->credentials ?? [];
        if (empty($c['phone_number_id']) || empty($c['access_token'])) {
            throw new \RuntimeException('WhatsApp not configured');
        }
        $version = $c['api_version'] ?? 'v19.0';
        $res = Http::timeout(20)->withToken($c['access_token'])
            ->post("https://graph.facebook.com/{$version}/{$c['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($to, '+'),
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $message],
            ]);

        return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 1000)];
    }

    /** Decode a VIN with the free NHTSA vPIC API (only when enabled). */
    public static function decodeVin(string $vin): ?array
    {
        $vin = strtoupper(preg_replace('/[^A-HJ-NPR-Z0-9]/i', '', $vin));
        if (strlen($vin) !== 17) {
            return null;
        }
        $integration = self::active('vin');
        if (! $integration) {
            return null;
        }
        $base = rtrim($integration->credentials['base_url'] ?? 'https://vpic.nhtsa.dot.gov/api', '/');
        $res = Http::timeout(15)->get("{$base}/vehicles/DecodeVinValues/{$vin}", ['format' => 'json']);
        $r = $res->json('Results.0');
        if (! $res->successful() || ! $r) {
            return null;
        }

        return [
            'make' => $r['Make'] ?? null, 'model' => $r['Model'] ?? null, 'year' => $r['ModelYear'] ?? null,
            'engine' => trim(($r['DisplacementL'] ?? '').'L '.($r['EngineConfiguration'] ?? '').' '.($r['EngineCylinders'] ?? '')),
            'fuel_type' => $r['FuelTypePrimary'] ?? null, 'transmission' => $r['TransmissionStyle'] ?? null,
            'body' => $r['BodyClass'] ?? null, 'trim' => $r['Trim'] ?? null,
        ];
    }
}
