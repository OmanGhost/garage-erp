<?php

namespace App\Modules\Saas\Services;

use App\Modules\Saas\Models\TrialRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Collects and evaluates verification data for website trial requests:
 *  - browser data sent by the form (time zone, languages, screen, platform, first referrer, landing page)
 *  - request data (IP, user agent, Accept-Language, time spent on the form, UTM tags)
 *  - network checks run after the response (IP country / ISP / VPN-proxy-hosting flags, email MX records)
 * and turns them into a list of checks with a risk level for the super admin.
 */
class TrialInspector
{
    /** DNS resolver used for the MX check (replaceable in tests). */
    public static ?Closure $mxResolver = null;

    public const FREE_EMAIL = ['gmail.com', 'googlemail.com', 'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'yahoo.com', 'ymail.com',
        'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'mail.com', 'yandex.com', 'zoho.com'];

    public const DISPOSABLE_EMAIL = ['mailinator.com', 'yopmail.com', 'guerrillamail.com', 'sharklasers.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'trashmail.com', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'mohmal.com', 'fakeinbox.com', 'throwawaymail.com',
        'emailondeck.com', 'mintemail.com', 'mytemp.email', 'tempail.com', 'tempr.email', 'discard.email', 'spamgourmet.com', 'moakt.com'];

    /** Calling codes → ISO country (longest prefix wins). */
    public const PHONE_PREFIXES = ['968' => 'OM', '971' => 'AE', '966' => 'SA', '973' => 'BH', '974' => 'QA', '965' => 'KW', '967' => 'YE',
        '962' => 'JO', '961' => 'LB', '963' => 'SY', '964' => 'IQ', '20' => 'EG', '212' => 'MA', '213' => 'DZ', '216' => 'TN', '218' => 'LY',
        '249' => 'SD', '970' => 'PS', '98' => 'IR', '90' => 'TR', '91' => 'IN', '92' => 'PK', '880' => 'BD', '94' => 'LK', '977' => 'NP',
        '63' => 'PH', '62' => 'ID', '60' => 'MY', '86' => 'CN', '44' => 'GB', '49' => 'DE', '33' => 'FR', '7' => 'RU', '1' => 'US'];

    /** Verification data available while handling the form POST. */
    public function fromRequest(Request $request, ?int $fillSeconds): array
    {
        $c = (array) $request->input('_client', []);
        $str = fn ($v, int $max = 190) => ($v = trim(strip_tags((string) $v))) === '' ? null : mb_substr($v, 0, $max);

        return array_filter([
            'client' => array_filter([
                'timezone' => $str($c['tz'] ?? null, 60),
                'languages' => $str($c['langs'] ?? null, 120),
                'screen' => $str($c['screen'] ?? null, 30),
                'platform' => $str($c['platform'] ?? null, 60),
                'touch' => isset($c['touch']) ? (bool) $c['touch'] : null,
                'referrer' => $str($c['ref'] ?? null, 300),
                'landing' => $str($c['landing'] ?? null, 300),
            ], fn ($v) => $v !== null),
            'request' => array_filter([
                'accept_language' => $str($request->header('Accept-Language'), 120),
                'fill_seconds' => $fillSeconds,
                'utm_source' => $str($request->input('utm_source'), 100),
                'utm_medium' => $str($request->input('utm_medium'), 100),
                'utm_campaign' => $str($request->input('utm_campaign'), 100),
                'device' => self::parseUserAgent((string) $request->userAgent()),
                'country_header' => $str($request->header('CF-IPCountry'), 2), // set when the site is behind Cloudflare
            ], fn ($v) => $v !== null && $v !== []),
        ]);
    }

    /** Network checks (IP intelligence + email MX). Safe to call again ("re-check"). */
    public function enrich(TrialRequest $trial): TrialRequest
    {
        $meta = $trial->meta ?? [];
        $enabled = (bool) config('services.ip_lookup.enabled', true);

        $domain = Str::after($trial->email, '@');
        $meta['email'] = [
            'domain' => $domain,
            'free' => in_array($domain, self::FREE_EMAIL, true),
            'disposable' => in_array($domain, self::DISPOSABLE_EMAIL, true),
            'mx' => $enabled || self::$mxResolver ? $this->hasMx($domain) : null,
        ];

        if ($enabled && $trial->ip_address && filter_var($trial->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            try {
                $res = Http::timeout((int) config('services.ip_lookup.timeout', 4))->acceptJson()
                    ->get(str_replace('{ip}', urlencode($trial->ip_address), (string) config('services.ip_lookup.url')));
                $j = $res->json() ?? [];
                if ($res->ok() && ($j['status'] ?? 'success') === 'success') {
                    $meta['ip'] = array_filter([
                        'country' => $j['country'] ?? null, 'country_code' => $j['countryCode'] ?? ($j['country_code'] ?? null),
                        'region' => $j['regionName'] ?? ($j['region'] ?? null), 'city' => $j['city'] ?? null,
                        'timezone' => is_array($j['timezone'] ?? null) ? ($j['timezone']['id'] ?? null) : ($j['timezone'] ?? null),
                        'isp' => $j['isp'] ?? ($j['connection']['isp'] ?? null), 'org' => $j['org'] ?? ($j['connection']['org'] ?? null),
                        'as' => $j['as'] ?? null, 'mobile' => $j['mobile'] ?? null, 'proxy' => $j['proxy'] ?? null, 'hosting' => $j['hosting'] ?? null,
                    ], fn ($v) => $v !== null && $v !== '');
                } else {
                    $meta['ip'] = ['error' => (string) ($j['message'] ?? 'HTTP '.$res->status())];
                }
            } catch (\Throwable $e) {
                $meta['ip'] = ['error' => Str::limit($e->getMessage(), 120)];
            }
        }

        $country = $meta['ip']['country_code'] ?? ($meta['request']['country_header'] ?? null);
        $trial->forceFill([
            'meta' => $meta,
            'country_code' => $country && preg_match('/^[A-Z]{2}$/', strtoupper($country)) && strtoupper($country) !== 'XX' ? strtoupper($country) : $trial->country_code,
            'checked_at' => now(),
        ])->save();

        return $trial;
    }

    protected function hasMx(string $domain): ?bool
    {
        if ($domain === '' || ! str_contains($domain, '.')) {
            return false;
        }
        try {
            $resolver = self::$mxResolver ?? fn (string $d) => checkdnsrr($d.'.', 'MX') || checkdnsrr($d.'.', 'A');

            return (bool) $resolver($domain);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Checks shown to the admin: list of [key, label, level ok|info|warn|bad, detail] plus an overall risk.
     *
     * @return array{checks: array<int, array{key:string,label:string,level:string,detail:?string}>, score:int, risk:string}
     */
    public function assess(TrialRequest $t, array $duplicates = []): array
    {
        $m = $t->meta ?? [];
        $checks = [];
        $add = function (string $key, string $label, string $level, ?string $detail = null) use (&$checks) {
            $checks[] = compact('key', 'label', 'level', 'detail');
        };

        // email
        $e = $m['email'] ?? null;
        if ($e) {
            if ($e['disposable'] ?? false) {
                $add('email', __('Email address'), 'bad', __('Temporary (disposable) email service'));
            } elseif (($e['mx'] ?? null) === false) {
                $add('email', __('Email address'), 'bad', __('The email domain cannot receive mail'));
            } elseif ($e['free'] ?? false) {
                $add('email', __('Email address'), 'info', __('Personal email (:d), not a company domain', ['d' => $e['domain']]));
            } else {
                $add('email', __('Email address'), 'ok', __('Company domain :d accepts mail', ['d' => $e['domain']]));
            }
        }

        // phone country vs IP country
        $phoneCountry = self::phoneCountry($t->phone);
        $ipCountry = $t->country_code;
        if ($phoneCountry && $ipCountry) {
            $add('country', __('Phone and location'), $phoneCountry === $ipCountry ? 'ok' : 'warn',
                $phoneCountry === $ipCountry ? __('Phone number and connection are from the same country')
                    : __('Phone from :a, connection from :b', ['a' => self::countryName($phoneCountry), 'b' => self::countryName($ipCountry)]));
        } elseif (! $ipCountry) {
            $add('country', __('Phone and location'), 'info', __('Connection country not determined yet'));
        }

        // VPN / proxy / data centre
        $ip = $m['ip'] ?? [];
        if (($ip['proxy'] ?? false) || ($ip['hosting'] ?? false)) {
            $add('network', __('Connection type'), 'warn', ($ip['proxy'] ?? false) ? __('VPN or proxy detected') : __('Data-centre / hosting network (possible VPN)'));
        } elseif (isset($ip['isp'])) {
            $add('network', __('Connection type'), 'ok', (($ip['mobile'] ?? false) ? __('Mobile network') : __('Home / office network')).' · '.$ip['isp']);
        }

        // browser time zone vs IP time zone
        $tzBrowser = $m['client']['timezone'] ?? null;
        $tzIp = $ip['timezone'] ?? null;
        if ($tzBrowser && $tzIp && $this->offset($tzBrowser) !== null && $this->offset($tzIp) !== null) {
            $same = $this->offset($tzBrowser) === $this->offset($tzIp);
            $add('timezone', __('Time zone'), $same ? 'ok' : 'warn', $same ? $tzBrowser : __('Device :a, connection :b', ['a' => $tzBrowser, 'b' => $tzIp]));
        }

        // browser data present (bots usually post without running scripts)
        if (empty($m['client'])) {
            $add('browser', __('Browser data'), 'warn', __('The form was sent without browser data (scripts disabled or automated)'));
        }

        // time spent on the form
        $secs = $m['request']['fill_seconds'] ?? null;
        if ($secs !== null) {
            $add('speed', __('Time to fill the form'), $secs < 10 ? 'warn' : 'ok', $this->duration((int) $secs));
        }

        // other requests with the same email / phone / IP
        $dup = array_filter($duplicates);
        if ($dup) {
            $parts = [];
            foreach ($dup as $k => $n) {
                $parts[] = __(':n with the same :f', ['n' => $n, 'f' => __(match ($k) { 'email' => 'email', 'phone' => 'phone', default => 'IP address' })]);
            }
            $add('duplicates', __('Previous requests'), ($dup['ip'] ?? 0) >= 3 ? 'warn' : 'info', implode(' · ', $parts));
        } else {
            $add('duplicates', __('Previous requests'), 'ok', __('First request from this person'));
        }

        $score = array_sum(array_map(fn ($c) => ['bad' => 3, 'warn' => 1][$c['level']] ?? 0, $checks));
        $risk = $score >= 3 ? 'high' : ($score >= 1 ? 'medium' : 'low');
        if (! $t->checked_at && $score === 0) {
            $risk = 'unknown';
        }

        return compact('checks', 'score', 'risk');
    }

    // ------------------------------------------------------------------ helpers

    public static function phoneCountry(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (! str_starts_with((string) $phone, '+') && strlen($d) === 8) {
            return 'OM'; // local Omani number
        }
        for ($len = 3; $len >= 1; $len--) {
            if (isset(self::PHONE_PREFIXES[substr($d, 0, $len)])) {
                return self::PHONE_PREFIXES[substr($d, 0, $len)];
            }
        }

        return null;
    }

    public static function countryName(?string $code, ?string $locale = null): string
    {
        if (! $code) {
            return '—';
        }
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$code, $locale ?? app()->getLocale());
            if ($name && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }

    /** Small user-agent parser: browser, OS and device type. */
    public static function parseUserAgent(string $ua): array
    {
        if ($ua === '') {
            return [];
        }
        $browser = match (true) {
            (bool) preg_match('#Edg(?:e|A|iOS)?/([\d]+)#', $ua, $m) => 'Edge '.$m[1],
            (bool) preg_match('#OPR/([\d]+)#', $ua, $m) => 'Opera '.$m[1],
            (bool) preg_match('#SamsungBrowser/([\d]+)#', $ua, $m) => 'Samsung Internet '.$m[1],
            (bool) preg_match('#(?:Chrome|CriOS)/([\d]+)#', $ua, $m) => 'Chrome '.$m[1],
            (bool) preg_match('#(?:Firefox|FxiOS)/([\d]+)#', $ua, $m) => 'Firefox '.$m[1],
            (bool) preg_match('#Version/([\d]+).*Safari#', $ua, $m) => 'Safari '.$m[1],
            (bool) preg_match('#(curl|wget|python|bot|spider|crawl|headless)#i', $ua, $m) => 'Bot / script ('.$m[1].')',
            default => null,
        };
        $os = match (true) {
            (bool) preg_match('#Windows NT 10#', $ua) => 'Windows 10/11',
            (bool) preg_match('#Windows NT ([\d.]+)#', $ua, $m) => 'Windows NT '.$m[1],
            (bool) preg_match('#(?:iPhone|iPad).*OS ([\d_]+)#', $ua, $m) => 'iOS '.str_replace('_', '.', $m[1]),
            (bool) preg_match('#Android ([\d.]+)#', $ua, $m) => 'Android '.$m[1],
            (bool) preg_match('#Mac OS X ([\d_]+)#', $ua, $m) => 'macOS '.str_replace('_', '.', $m[1]),
            (bool) preg_match('#CrOS#', $ua) => 'ChromeOS',
            (bool) preg_match('#Linux#', $ua) => 'Linux',
            default => null,
        };
        $device = match (true) {
            (bool) preg_match('#iPad|Tablet|(?:Android(?!.*Mobile))#', $ua) => 'tablet',
            (bool) preg_match('#Mobi|iPhone|Android#', $ua) => 'mobile',
            (bool) preg_match('#bot|spider|crawl|curl|wget|python|headless#i', $ua) => 'bot',
            default => 'desktop',
        };

        return array_filter(compact('browser', 'os', 'device'));
    }

    protected function offset(string $tz): ?int
    {
        try {
            return (new \DateTimeZone($tz))->getOffset(new \DateTimeImmutable('now'));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function duration(int $s): string
    {
        return $s < 60 ? __(':n seconds', ['n' => $s]) : __(':m min :s sec', ['m' => intdiv($s, 60), 's' => $s % 60]);
    }
}
