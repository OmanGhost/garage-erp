<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Plan;
use App\Modules\Saas\Models\TrialRequest;
use App\Modules\Saas\Services\TrialInspector;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrialVerificationTest extends TestCase
{
    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $this->super->is_super_admin = true;
        $this->super->is_active = true;
        $this->super->save();
    }

    protected function tearDown(): void
    {
        TrialInspector::$mxResolver = null;
        parent::tearDown();
    }

    protected function post_trial(array $over = [], string $ip = '5.36.10.20', string $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua, 'Accept-Language' => 'ar-OM,ar;q=0.9'])
            ->post('/trial', $over + [
                'name' => 'Salim Al Harthy', 'company_name' => 'Harthy Auto Care', 'email' => 'salim@harthy.om', 'phone' => '92001122',
                'business_type' => 'workshop', 'branches' => 2, 'plan_id' => Plan::query()->where('code', 'professional')->value('id'),
                'consent' => '1', 'locale' => 'ar', 'website' => '', '_ft' => Crypt::encryptString((string) now()->subSeconds(95)->timestamp),
                'utm_source' => 'google', 'utm_campaign' => 'launch',
                '_client' => ['tz' => 'Asia/Muscat', 'langs' => 'ar-OM, en', 'screen' => '390x844@3', 'platform' => 'iOS', 'touch' => '1',
                    'ref' => 'https://www.google.com/', 'landing' => '/?utm_source=google'],
            ]);
    }

    public function test_request_stores_browser_data_and_network_checks(): void
    {
        config(['services.ip_lookup.enabled' => true]);
        TrialInspector::$mxResolver = fn () => true;
        Http::fake(['ip-api.com/*' => Http::response([
            'status' => 'success', 'country' => 'Oman', 'countryCode' => 'OM', 'regionName' => 'Muscat', 'city' => 'Seeb',
            'timezone' => 'Asia/Muscat', 'isp' => 'Omantel', 'org' => 'Omantel', 'as' => 'AS8529 Omantel', 'mobile' => true, 'proxy' => false, 'hosting' => false,
        ])]);

        $this->post_trial()->assertRedirect(route('site.thanks'));

        $t = TrialRequest::query()->firstOrFail();
        $this->assertSame('OM', $t->country_code);
        $this->assertNotNull($t->checked_at);
        $this->assertSame('Asia/Muscat', $t->meta['client']['timezone']);
        $this->assertSame('https://www.google.com/', $t->meta['client']['referrer']);
        $this->assertSame('Omantel', $t->meta['ip']['isp']);
        $this->assertSame('mobile', $t->meta['request']['device']['device']);
        $this->assertStringStartsWith('Safari', $t->meta['request']['device']['browser']);
        $this->assertGreaterThanOrEqual(90, $t->meta['request']['fill_seconds']);
        $this->assertTrue($t->meta['email']['mx']);
        $this->assertSame('google', $t->source);

        $a = app(TrialInspector::class)->assess($t, $t->duplicates());
        $this->assertSame('low', $a['risk']);

        // admin list and detail page
        $this->actingAs($this->super);
        $this->get('/admin/trials')->assertOk()->assertSee('Harthy Auto Care')->assertSee('مخاطرة منخفضة');
        $this->get('/admin/trials/'.$t->id)->assertOk()
            ->assertSee('المعلومات التقنية')->assertSee('5.36.10.20')->assertSee('Omantel')->assertSee('Asia/Muscat')
            ->assertSee('390x844@3')->assertSee('https://www.google.com/')->assertSee('utm_source', false);
        $this->get('/admin/trials?country=OM')->assertOk()->assertSee('Harthy Auto Care');
        $this->get('/admin/trials?country=AE')->assertOk()->assertDontSee('Harthy Auto Care');
    }

    public function test_suspicious_request_is_flagged_high_risk(): void
    {
        config(['services.ip_lookup.enabled' => true]);
        TrialInspector::$mxResolver = fn () => false;
        Http::fake(['ip-api.com/*' => Http::response([
            'status' => 'success', 'country' => 'Netherlands', 'countryCode' => 'NL', 'timezone' => 'Europe/Amsterdam',
            'isp' => 'DigitalOcean', 'mobile' => false, 'proxy' => true, 'hosting' => true,
        ])]);

        $this->post_trial(['email' => 'x@mailinator.com', '_client' => []], '159.89.1.2', 'python-requests/2.31')->assertRedirect();
        $t = TrialRequest::query()->firstOrFail();
        $a = app(TrialInspector::class)->assess($t, $t->duplicates());

        $this->assertSame('high', $a['risk']);
        $levels = collect($a['checks'])->pluck('level', 'key');
        $this->assertSame('bad', $levels['email']);
        $this->assertSame('warn', $levels['country']);   // Omani phone, Dutch connection
        $this->assertSame('warn', $levels['network']);   // VPN / hosting
        $this->assertSame('warn', $levels['browser']);   // no browser data
        $this->assertSame('bot', $t->meta['request']['device']['device']);

        $this->actingAs($this->super)->get('/admin/trials/'.$t->id)->assertOk()->assertSee('مخاطرة عالية')->assertSee('تم اكتشاف VPN أو بروكسي');
    }

    public function test_recheck_and_lookup_failure_are_handled(): void
    {
        $this->post_trial()->assertRedirect(); // network checks disabled in tests: no lookup yet
        $t = TrialRequest::query()->firstOrFail();
        $this->assertNull($t->country_code);
        $this->assertArrayNotHasKey('ip', $t->meta);

        config(['services.ip_lookup.enabled' => true]);
        TrialInspector::$mxResolver = fn () => true;
        Http::fake(['ip-api.com/*' => Http::sequence()
            ->push(['status' => 'fail', 'message' => 'reserved range'])
            ->push(['status' => 'success', 'countryCode' => 'AE', 'country' => 'UAE', 'isp' => 'du'])]);
        $this->actingAs($this->super)->post('/admin/trials/'.$t->id.'/check')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('reserved range', $t->fresh()->meta['ip']['error']);
        $this->get('/admin/trials/'.$t->id)->assertOk()->assertSee('reserved range');

        $this->post('/admin/trials/'.$t->id.'/check');
        $this->assertSame('AE', $t->fresh()->country_code);

        // non super admins cannot see or re-check requests
        $this->makeTenant(); // signs in a normal company admin
        tenant()->forget();
        $this->get('/admin/trials/'.$t->id)->assertForbidden();
        $this->post('/admin/trials/'.$t->id.'/check')->assertForbidden();
    }

    public function test_phone_country_and_user_agent_parsing(): void
    {
        $this->assertSame('OM', TrialInspector::phoneCountry('+96892001122'));
        $this->assertSame('OM', TrialInspector::phoneCountry('92001122'));
        $this->assertSame('AE', TrialInspector::phoneCountry('00971501234567'));
        $this->assertSame('SA', TrialInspector::phoneCountry('+966 55 123 4567'));
        $ua = TrialInspector::parseUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0');
        $this->assertSame(['browser' => 'Edge 131', 'os' => 'Windows 10/11', 'device' => 'desktop'], $ua);
        $this->assertSame('tablet', TrialInspector::parseUserAgent('Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/131.0 Safari/537.36')['device']);
    }
}
