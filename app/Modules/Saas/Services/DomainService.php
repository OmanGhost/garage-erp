<?php

namespace App\Modules\Saas\Services;

use App\Modules\Core\Models\Company;
use Illuminate\Support\Str;

/**
 * Custom domains: normalisation, DNS verification and the host → company lookup.
 *
 * A domain is "pending" until its DNS points to the platform: a CNAME to the platform host, an A record to the
 * platform IP, or a TXT record "garage-erp-verify=<token>". The super admin can also activate it manually.
 */
class DomainService
{
    /** Platform host name (from APP_URL), e.g. garage.1ms2.com. */
    public static function platformHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public static function normalize(?string $domain): ?string
    {
        $d = strtolower(trim((string) $domain));
        $d = preg_replace('#^https?://#', '', $d);
        $d = rtrim(explode('/', $d)[0], '.');

        return $d === '' ? null : $d;
    }

    public static function isValid(string $domain): bool
    {
        return (bool) preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain)
            && $domain !== self::platformHost();
    }

    public function ensureToken(Company $c): string
    {
        if (! $c->domain_token) {
            $c->forceFill(['domain_token' => Str::lower(Str::random(32))])->save();
        }

        return $c->domain_token;
    }

    /** DNS records the client must add. */
    public function instructions(Company $c): array
    {
        $host = self::platformHost();
        $ip = $host ? gethostbyname($host) : null;

        return array_values(array_filter([
            ['type' => 'CNAME', 'name' => $c->custom_domain, 'value' => $host],
            $ip && $ip !== $host ? ['type' => 'A', 'name' => $c->custom_domain, 'value' => $ip] : null,
            ['type' => 'TXT', 'name' => '_garage-erp.'.$c->custom_domain, 'value' => 'garage-erp-verify='.$this->ensureToken($c)],
        ]));
    }

    /**
     * Check the DNS of the company's domain. Activates it when it points to the platform.
     *
     * @return array{ok: bool, found: array<int, string>, message: string}
     */
    public function verify(Company $c, ?\Closure $resolver = null): array
    {
        $domain = $c->custom_domain;
        if (! $domain) {
            return ['ok' => false, 'found' => [], 'message' => __('No custom domain is set.')];
        }
        $resolver ??= fn (string $name, int $type) => @dns_get_record($name, $type) ?: [];
        $host = self::platformHost();
        $platformIps = $host ? (array) @gethostbynamel($host) : [];
        $token = $this->ensureToken($c);
        $found = [];
        $ok = false;
        foreach ($resolver($domain, DNS_CNAME) as $r) {
            $found[] = 'CNAME '.($r['target'] ?? '');
            $ok = $ok || rtrim(strtolower($r['target'] ?? ''), '.') === $host;
        }
        foreach ($resolver($domain, DNS_A) as $r) {
            $found[] = 'A '.($r['ip'] ?? '');
            $ok = $ok || in_array($r['ip'] ?? '', $platformIps, true);
        }
        foreach ($resolver('_garage-erp.'.$domain, DNS_TXT) as $r) {
            $txt = $r['txt'] ?? '';
            $found[] = 'TXT '.$txt;
            $ok = $ok || $txt === 'garage-erp-verify='.$token;
        }
        if ($ok) {
            $c->forceFill(['domain_status' => 'active', 'domain_verified_at' => now()])->save();
        }

        return ['ok' => $ok, 'found' => $found, 'message' => $ok
            ? __('The domain :d points to the platform and is now active.', ['d' => $domain])
            : __('The domain :d does not point to the platform yet. DNS changes can take up to 24 hours.', ['d' => $domain])];
    }

    /** Company served on this host (active custom domain), if any. */
    public static function companyForHost(string $host): ?Company
    {
        $host = strtolower($host);
        if ($host === '' || $host === self::platformHost() || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        return Company::query()->where('custom_domain', $host)->where('domain_status', 'active')->first();
    }
}
