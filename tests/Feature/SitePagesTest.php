<?php

namespace Tests\Feature;

use App\Http\Controllers\SitePagesController;
use App\Models\User;
use App\Modules\Saas\Models\ContactMessage;
use App\Support\SiteSettings;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $u = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $u->is_super_admin = true;
        $u->is_active = true;
        $u->save();
    }

    public function test_every_page_renders_in_both_languages(): void
    {
        $features = array_keys(trans('site_features.items', [], 'ar'));
        $this->assertSame($features, array_keys(trans('site_features.items', [], 'en')));     // same catalogue in both languages
        $docs = array_keys(SitePagesController::docIndex('ar'));
        $this->assertSame($docs, array_keys(SitePagesController::docIndex('en')));
        $this->assertCount(13, $docs);

        foreach (['', '/en'] as $p) {
            foreach (['/features', '/solutions', '/customers', '/contact', '/terms', '/privacy', '/docs'] as $page) {
                $this->get($p.$page)->assertOk()->assertSee('rel="alternate"', false);
            }
            foreach ($features as $slug) {
                $this->get($p.'/features/'.$slug)->assertOk();
            }
            foreach ($docs as $slug) {
                $this->get($p.'/docs/'.$slug)->assertOk()->assertSee('site-prose', false);
            }
            $this->get($p)->assertOk()->assertSee($p.'/features/job-cards', false);
        }
        $this->get('/features/job-cards')->assertSee('أوامر العمل والاستقبال')->assertSee('/en/features/job-cards', false);
        $this->get('/en/docs/sales-pos')->assertSee('Closing the session')->assertSee('id="s1"', false);
        $this->get('/features/nope')->assertNotFound();
        $this->get('/docs/nope')->assertNotFound();
        $this->get('/en/docs/..%2Fen')->assertNotFound();
    }

    public function test_contact_form_is_stored_and_reaches_the_admin(): void
    {
        $this->post('/contact', ['name' => 'Salim', 'email' => 'salim@harthy.om', 'phone' => '+968 9200 1122', 'topic' => 'demo',
            'message' => 'We have three branches and want a demo.', 'locale' => 'en'])->assertRedirect('/en/contact');
        $m = ContactMessage::query()->firstOrFail();
        $this->assertSame('demo', $m->topic);
        $this->assertSame('new', $m->status);
        $admin = User::query()->where('is_super_admin', true)->first();
        $this->assertSame(1, $admin->notifications()->count());
        $this->get('/en/contact')->assertSee(__('site_pages.contact.sent_title', [], 'en'));

        // honeypot and validation
        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@b.com', 'topic' => 'other', 'message' => 'spam spam spam', 'website' => 'http://x'])->assertRedirect();
        $this->post('/contact', ['name' => '', 'email' => 'bad', 'topic' => 'x', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
        $this->assertSame(1, ContactMessage::query()->count());

        $this->actingAs($admin);
        $this->get('/admin/messages')->assertOk()->assertSee('Salim');
        $this->get('/admin/messages/'.$m->id)->assertOk()->assertSee('three branches');
        $this->put('/admin/messages/'.$m->id, ['status' => 'replied', 'admin_notes' => 'Demo booked'])->assertSessionHas('success');
        $this->assertSame('replied', $m->fresh()->status);
    }

    public function test_customers_and_legal_texts_come_from_site_settings(): void
    {
        $this->get('/customers')->assertSee(__('site_pages.customers.empty_title', [], 'ar'));
        $this->get('/terms')->assertSee('القانون الواجب التطبيق');

        $admin = User::query()->where('is_super_admin', true)->first();
        $this->actingAs($admin)->put('/admin/site', array_merge(SiteSettings::all(), [
            'clients' => "Harthy Auto Care | Workshop | Muscat | javascript:alert(1) | https://harthy.om | Great system | Salim\n\n  | ignored",
            'terms_en' => "## Custom terms\n<script>alert(1)</script>Plain",
        ]))->assertSessionHasNoErrors();
        auth()->logout();

        $clients = SiteSettings::clients();
        $this->assertCount(1, $clients);
        $this->assertSame('', $clients[0]['logo']);                    // unsafe URL dropped
        $this->get('/en/customers')->assertSee('Harthy Auto Care')->assertSee('Great system');
        $this->get('/en/terms')->assertSee('Custom terms')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(now()->toDateString());
        $this->get('/terms')->assertSee('القانون الواجب التطبيق');     // Arabic still uses the built-in text
    }

    public function test_sitemap_and_robots_list_the_new_pages(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.route('site.feature.en', 'loss-prevention').'</loc>', false)
            ->assertSee('<loc>'.route('site.doc', 'getting-started').'</loc>', false)->assertSee('<loc>'.route('site.privacy').'</loc>', false);
        $this->get('/robots.txt')->assertSee('Allow: /docs');
    }
}
