<?php

namespace App\Support\Mail;

use App\Modules\Core\Services\IntegrationManager;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing e-mail without editing .env:
 *  1. the company's own SMTP server (Settings → Email / SMTP), used for its customers' messages;
 *  2. the platform SMTP server (SaaS → Website & settings), used for system e-mails and companies without their own;
 *  3. otherwise the MAIL_* settings of .env.
 */
class MailService
{
    public const FIELDS = ['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'];

    public const PLATFORM_KEY = 'platform_smtp';

    /** SMTP of the current company (integration "smtp"), when configured and enabled. */
    public static function companyConfig(): ?array
    {
        if (! tenant()->id()) {
            return null;
        }
        $c = IntegrationManager::active('smtp')?->credentials;

        return self::valid($c) ? $c : null;
    }

    /** Platform SMTP saved by the super admin (encrypted in platform_settings). */
    protected static ?array $platformCache = null;

    public static function platformConfig(): ?array
    {
        $cache = &self::$platformCache;
        if ($cache !== null) {
            return $cache ?: null;
        }
        try {
            $raw = Schema::hasTable('platform_settings') ? DB::table('platform_settings')->where('key', self::PLATFORM_KEY)->value('value') : null;
            $c = $raw ? json_decode(Crypt::decryptString($raw), true) : null;
        } catch (\Throwable) {
            $c = null;
        }
        $cache = self::valid($c) ? $c : [];

        return $cache ?: null;
    }

    public static function savePlatformConfig(?array $c): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => self::PLATFORM_KEY],
            ['value' => $c ? Crypt::encryptString(json_encode($c)) : null, 'updated_at' => now(), 'created_at' => now()]);
        self::$platformCache = null;
    }

    /** Use the platform SMTP as the application's default mailer (called once at boot). */
    public static function applyPlatformDefault(): void
    {
        if (app()->runningUnitTests() || ! ($c = self::platformConfig())) {
            return;
        }
        config([
            'mail.default' => 'platform_smtp',
            'mail.mailers.platform_smtp' => self::mailerConfig($c),
            'mail.from.address' => $c['from_address'],
            'mail.from.name' => $c['from_name'] ?: config('app.name'),
        ]);
    }

    public static function mailerConfig(array $c): array
    {
        $enc = $c['encryption'] ?? 'tls';

        return [
            'transport' => 'smtp', 'host' => $c['host'], 'port' => (int) ($c['port'] ?: ($enc === 'ssl' ? 465 : 587)),
            'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp', 'username' => $c['username'] ?: null, 'password' => $c['password'] ?: null,
            'timeout' => 20, 'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ];
    }

    public static function mailer(array $c): Mailer
    {
        return Mail::build(self::mailerConfig($c));
    }

    /** Can e-mail be sent at all (company, platform or a real .env mailer)? */
    public static function available(): bool
    {
        if (self::companyConfig() || self::platformConfig()) {
            return true;
        }
        $default = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');

        return $default !== '' && ! in_array($default, ['array', 'log'], true)
            && ($default !== 'smtp' || ($host !== '' && ! str_contains($host, 'example')));
    }

    /** Send a plain-text e-mail with the best available server. */
    public static function send(string $to, string $subject, string $body, ?array $config = null): void
    {
        $c = $config ?? self::companyConfig();
        if ($c) {
            self::mailer($c)->raw($body, function ($m) use ($to, $subject, $c) {
                $m->to($to)->subject($subject)->from($c['from_address'], $c['from_name'] ?: (tenant()->company()?->displayName() ?? config('app.name')));
            });

            return;
        }
        Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
    }

    /** Try a configuration by sending a test message. @return array{ok: bool, message: string} */
    public static function test(array $c, string $to): array
    {
        try {
            self::send($to, __('Test e-mail from :app', ['app' => config('app.name')]),
                __('This is a test message. Your e-mail (SMTP) settings work.')."\n\n".now()->format('Y-m-d H:i'), $c);

            return ['ok' => true, 'message' => __('Test e-mail sent to :e. Check the inbox (and the spam folder).', ['e' => $to])];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => __('Sending failed: :m', ['m' => \Illuminate\Support\Str::limit($e->getMessage(), 300)])];
        }
    }

    public static function valid(mixed $c): bool
    {
        return is_array($c) && filled($c['host'] ?? null) && filter_var($c['from_address'] ?? null, FILTER_VALIDATE_EMAIL);
    }

    /** Validation rules shared by the company and platform forms (password optional: kept when left empty). */
    public static function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
