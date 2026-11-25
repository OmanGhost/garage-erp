<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Plan;
use App\Modules\Saas\Models\TrialRequest;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $u = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $u->is_super_admin = true;
        $u->is_active = true;
        $u->save();
    }

    protected function validTrial(array $over = []): array
    {
        return $over + [
            'name' => 'Salim Al Harthy', 'company_name' => 'Harthy Auto Care', 'email' => 'salim@harthy.om', 'phone' => '92001122',
            'business_type' => 'workshop', 'branches' => 2, 'plan_id' => Plan::query()->where('code', 'professional')->value('id'),
            'consent' => '1', 'locale' => 'ar', 'website' => '', '_ft' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
        ];
    }

    public function test_home_pages_render_with_plans_and_seo_markup(): void
    {
        $ar = $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('og:image', false)
            ->assertSee('dir="rtl"', false);
        foreach (Plan::query()->where('is_active', true)->get() as $plan) {
            $ar->assertSee($plan->name_ar);
        }
        $this->assertNull($ar->headers->get('X-Robots-Tag'));

        $this->get('/en')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Professional')->assertSee('"priceCurrency":"OMR"', false);
        $this->get('/trial?plan=starter')->assertOk()->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_signed_in_users_are_sent_to_their_workspace(): void
    {
        $this->makeTenant();
        tenant()->forget();
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_private_pages_are_not_indexable(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/trial/thanks')->assertSee('noindex, nofollow', false);
    }

    public function test_trial_request_is_stored_and_admins_are_notified(): void
    {
        $this->post('/trial', $this->validTrial())->assertRedirect(route('site.thanks'));

        $t = TrialRequest::query()->firstOrFail();
        $this->assertSame('+96892001122', $t->phone);
        $this->assertSame('new', $t->status);
        $admin = User::query()->where('is_super_admin', true)->first();
        $this->assertSame(1, $admin->notifications()->count());

        // a second open request with the same email is not duplicated
        $this->post('/trial', $this->validTrial())->assertRedirect();
        $this->assertSame(1, TrialRequest::query()->count());
    }

    public function test_trial_form_validation_and_spam_protection(): void
    {
        $this->post('/trial', $this->validTrial(['email' => 'not-an-email', 'consent' => null]))->assertSessionHasErrors(['email', 'consent']);
        // bots: honeypot filled, or form submitted instantly
        $this->post('/trial', $this->validTrial(['website' => 'http://spam.example']))->assertRedirect(route('site.thanks'));
        $this->post('/trial', $this->validTrial(['_ft' => Crypt::encryptString((string) now()->timestamp)]))->assertRedirect(route('site.thanks'));
        $this->post('/trial', $this->validTrial(['_ft' => 'forged']))->assertRedirect(route('site.thanks'));
        $this->assertSame(0, TrialRequest::query()->count());
    }

    public function test_super_admin_provisions_a_trial_company(): void
    {
        $this->post('/trial', $this->validTrial());
        $trial = TrialRequest::query()->firstOrFail();
        $super = User::query()->where('is_super_admin', true)->first();
        $this->actingAs($super);

        $this->get('/admin/trials')->assertOk()->assertSee('Harthy Auto Care');
        $this->post('/admin/trials/'.$trial->id.'/provision', ['plan_id' => $trial->plan_id])->assertRedirect()->assertSessionHas('success');

        $trial->refresh();
        $this->assertSame('provisioned', $trial->status);
        $company = Company::query()->findOrFail($trial->company_id);
        $this->assertSame('trial', $company->subscription_status);
        $this->assertTrue(User::query()->where('email', 'salim@harthy.om')->where('company_id', $company->id)->exists());

        // cannot be provisioned twice
        $this->post('/admin/trials/'.$trial->id.'/provision', ['plan_id' => $trial->plan_id])->assertSessionHas('warning');
        $this->assertSame(1, Company::query()->where('id', $company->id)->count());
    }

    public function test_only_super_admin_manages_trials_and_seo(): void
    {
        $this->makeTenant();
        tenant()->forget();
        $this->get('/admin/trials')->assertStatus(403);
        $this->get('/admin/site')->assertStatus(403);
    }

    public function test_seo_settings_drive_meta_tags_robots_and_sitemap(): void
    {
        $this->actingAs(User::query()->where('is_super_admin', true)->first());
        $s = SiteSettings::all();
        $this->put('/admin/site', array_merge($s, [
            'seo_title_ar' => 'عنوان مخصص للاختبار', 'google_site_verification' => 'abc123XYZ', 'google_analytics_id' => 'G-TEST1234',
            'indexing_enabled' => '1',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/')->assertSee('<title>عنوان مخصص للاختبار</title>', false)
            ->assertSee('name="google-site-verification" content="abc123XYZ"', false)
            ->assertSee('gtag/js?id=G-TEST1234', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('site.sitemap'))->assertSee('Disallow: /dashboard');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('site.trial.en').'</loc>', false);

        // staging: indexing off blocks everything
        SiteSettings::set(['indexing_enabled' => '0']);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
        $this->get('/')->assertSee('noindex, nofollow', false)->assertDontSee('gtag/js', false);
    }
}
