<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\Saas\Models\ContactMessage;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Public website pages besides the landing page: features (with a page per feature), solutions, customers,
 * contact, terms, privacy and the documentation centre. Arabic lives at "/x", English at "/en/x".
 */
class SitePagesController extends Controller
{
    protected function useLocale(string $locale): void
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /** URL of a site page in a locale: site_url('features', 'en', 'job-cards'). */
    public static function url(string $page, ?string $locale = null, ?string $param = null): string
    {
        $locale ??= app()->getLocale();
        $name = 'site.'.$page.($locale === 'en' ? '.en' : '');

        return $param !== null ? route($name, $param) : route($name);
    }

    protected function alternates(string $page, ?string $param = null): array
    {
        return ['ar' => self::url($page, 'ar', $param), 'en' => self::url($page, 'en', $param)];
    }

    /** Plans (name list) that include a module. */
    protected function plansWith(string $module)
    {
        return Plan::query()->where('is_active', true)->orderBy('sort')->get()
            ->filter(fn ($p) => is_array($p->modules) && in_array($module, $p->modules, true))->values();
    }

    public function features(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return view('site.features', ['page' => 'features', 'alternates' => $this->alternates('features')]);
    }

    public function feature(string $slug, string $locale = 'ar')
    {
        $this->useLocale($locale);
        $items = __('site_features.items');
        abort_unless(is_array($items) && isset($items[$slug]), 404);
        $f = $items[$slug];
        $related = collect($items)->except($slug)->filter(fn ($i) => $i['group'] === $f['group'])->take(3)->all();

        return view('site.feature', ['page' => 'features', 'slug' => $slug, 'f' => $f, 'related' => $related,
            'plans' => $this->plansWith($f['module']), 'alternates' => $this->alternates('feature', $slug)]);
    }

    public function solutions(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return view('site.solutions', ['page' => 'solutions', 'alternates' => $this->alternates('solutions')]);
    }

    public function customers(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return view('site.customers', ['page' => 'customers', 'clients' => SiteSettings::clients(), 'alternates' => $this->alternates('customers')]);
    }

    public function contact(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return view('site.contact', ['page' => 'contact', 'alternates' => $this->alternates('contact'), 'sent' => session('contact_sent')]);
    }

    public function storeContact(Request $request)
    {
        $locale = $request->input('locale') === 'en' ? 'en' : 'ar';
        $this->useLocale($locale);
        // bots fill the hidden field
        if (filled($request->input('website'))) {
            return redirect(self::url('contact', $locale))->with('contact_sent', true);
        }
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'], 'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+()\-]{6,30}$/'],
            'topic' => ['required', 'in:sales,demo,support,partnership,other'], 'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $msg = ContactMessage::create($d + ['locale' => $locale, 'status' => 'new', 'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, '')]);
        $admins = User::query()->where('is_super_admin', true)->where('is_active', true)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SystemMessage(__('New website message from :n', ['n' => $msg->name]),
                Str::limit($msg->message, 120), route('admin.messages.show', $msg), 'info'));
        }
        // same email alert as trial requests (queued; a mail failure never blocks the visitor)
        if ($to = SiteSettings::get('trial_notify_email')) {
            $body = "New website message\n\nName: {$msg->name}\nCompany: {$msg->company}\nEmail: {$msg->email}\nPhone: {$msg->phone}\nTopic: {$msg->topic}\n\n{$msg->message}\n\n"
                .route('admin.messages.show', $msg);
            dispatch(fn () => \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->replyTo($msg->email, $msg->name)->subject('Website message: '.$msg->name)));
        }

        return redirect(self::url('contact', $locale))->with('contact_sent', true);
    }

    public function legal(string $doc, string $locale = 'ar')
    {
        $this->useLocale($locale);
        abort_unless(in_array($doc, ['terms', 'privacy'], true), 404);
        $custom = trim((string) SiteSettings::get($doc.'_'.$locale));
        $md = $custom !== '' ? $custom : __('site_pages.legal.'.$doc);

        return view('site.legal', ['page' => $doc, 'doc' => $doc, 'html' => Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'updated' => SiteSettings::get($doc.'_updated_at'), 'alternates' => $this->alternates($doc)]);
    }

    /* ---------------------------------------------------------------- docs */

    /** @return array<string, array{group: string, icon: string, title: string, summary: string}> */
    public static function docIndex(string $locale): array
    {
        $out = [];
        foreach (require resource_path('docs/index.php') as $slug => $meta) {
            $file = resource_path("docs/$locale/$slug.md");
            if (! is_file($file)) {
                continue;
            }
            $lines = preg_split('/\R/u', (string) file_get_contents($file));
            $title = ltrim((string) ($lines[0] ?? $slug), '# ');
            $summary = '';
            foreach (array_slice($lines, 1) as $l) {
                if (trim($l) !== '') {
                    $summary = trim($l);
                    break;
                }
            }
            $out[$slug] = $meta + ['title' => $title, 'summary' => $summary];
        }

        return $out;
    }

    public function docs(string $locale = 'ar')
    {
        $this->useLocale($locale);

        return view('site.docs', ['page' => 'docs', 'docs' => self::docIndex($locale), 'alternates' => $this->alternates('docs')]);
    }

    public function doc(string $slug, string $locale = 'ar')
    {
        $this->useLocale($locale);
        $index = self::docIndex($locale);
        abort_unless(isset($index[$slug]) && preg_match('/^[a-z0-9-]+$/', $slug), 404);
        $md = (string) file_get_contents(resource_path("docs/$locale/$slug.md"));
        $body = preg_replace('/^# .*\R/u', '', $md, 1);
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        // anchors for the table of contents
        $toc = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/u', function ($m) use (&$toc) {
            $id = 's'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'text' => strip_tags($m[1])];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html);
        $slugs = array_keys($index);
        $pos = array_search($slug, $slugs, true);

        return view('site.doc', ['page' => 'docs', 'slug' => $slug, 'doc' => $index[$slug], 'docs' => $index, 'html' => $html, 'toc' => $toc,
            'prev' => $pos > 0 ? $slugs[$pos - 1] : null, 'next' => $slugs[$pos + 1] ?? null,
            'updated' => Carbon::createFromTimestamp(filemtime(resource_path("docs/$locale/$slug.md"))), 'alternates' => $this->alternates('doc', $slug)]);
    }
}
