<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide settings for the public website (SEO, contact details, analytics).
 * Stored in `platform_settings`, cached for one hour, editable by the super admin.
 */
class SiteSettings
{
    public const CACHE_KEY = 'platform_settings';

    /** Editable keys with their defaults. */
    public static function defaults(): array
    {
        return [
            'site_name_ar' => 'نظام إدارة الورش وقطع الغيار',
            'site_name_en' => 'Garage ERP',
            'seo_title_ar' => 'نظام إدارة ورش السيارات وقطع الغيار في عُمان | فواتير ضريبية، مخزون، محاسبة',
            'seo_title_en' => 'Garage & Spare Parts ERP for Oman | Workshop, Inventory, VAT Invoicing',
            'seo_description_ar' => 'نظام سحابي متكامل لإدارة ورش صيانة السيارات ومحلات قطع الغيار الجديدة والمستعملة: استقبال المركبات، بطاقات العمل، المخزون، نقاط البيع، الفواتير الضريبية 5%، المحاسبة والرواتب. جرّبه مجاناً.',
            'seo_description_en' => 'All-in-one cloud ERP for car workshops and new & used spare parts shops in Oman: vehicle reception, job cards, inventory, POS, 5% VAT invoices, accounting and payroll. Start a free trial.',
            'seo_keywords_ar' => 'نظام ورش, برنامج ورشة سيارات, نظام قطع غيار, برنامج محاسبة ورش, فواتير ضريبية عمان, نظام نقاط بيع قطع غيار, بطاقة عمل ورشة',
            'seo_keywords_en' => 'garage management software, workshop ERP Oman, auto repair software, spare parts inventory, car service POS, VAT invoice Oman',
            'og_image' => '/images/og-default.png',
            'contact_email' => 'sales@example.om',
            'contact_phone' => '+968 2400 0000',
            'contact_whatsapp' => '96890000000',
            'contact_address_ar' => 'مسقط، سلطنة عُمان',
            'contact_address_en' => 'Muscat, Sultanate of Oman',
            'social_x' => '',
            'social_instagram' => '',
            'social_linkedin' => '',
            'social_youtube' => '',
            'google_site_verification' => '',
            'bing_site_verification' => '',
            'google_analytics_id' => '',
            'indexing_enabled' => '1',
            'trial_notify_email' => '',
            // website pages: customers (one per line: name | sector | city | logo URL | website | testimonial | person),
            // terms / privacy in Markdown (empty = the built-in default text)
            'clients' => '',
            'terms_ar' => '', 'terms_en' => '', 'privacy_ar' => '', 'privacy_en' => '',
            'terms_updated_at' => '', 'privacy_updated_at' => '',
            // platform billing (header of the subscription invoices)
            'billing_name' => '',
            'billing_vat_number' => '',
            'billing_cr_number' => '',
            'billing_address' => '',
            'billing_email' => '',
            'billing_phone' => '',
            'billing_tax_rate' => '5',
            'billing_due_days' => '7',
            'billing_bank_details' => '',
            'billing_terms' => '',
        ];
    }

    public static function all(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 3600, function () {
            if (! Schema::hasTable('platform_settings')) {
                return [];
            }

            return DB::table('platform_settings')->pluck('value', 'key')->all();
        });

        return array_merge(self::defaults(), array_intersect_key($stored, self::defaults()));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Localised value: get('seo_title') -> seo_title_ar / seo_title_en for the current locale. */
    public static function local(string $key): string
    {
        $all = self::all();

        return (string) ($all[$key.'_'.app()->getLocale()] ?? $all[$key.'_en'] ?? '');
    }

    /**
     * Customers shown on the website, parsed from the "clients" setting.
     *
     * @return array<int, array{name: string, sector: string, city: string, logo: string, website: string, quote: string, person: string}>
     */
    public static function clients(): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) self::get('clients')) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $url = fn ($v) => preg_match('#^(https?://|/)#i', (string) $v) ? (string) $v : '';
            $out[] = ['name' => mb_substr($p[0], 0, 120), 'sector' => $p[1] ?? '', 'city' => $p[2] ?? '', 'logo' => $url($p[3] ?? ''),
                'website' => preg_match('#^https?://#i', $p[4] ?? '') ? $p[4] : '', 'quote' => $p[5] ?? '', 'person' => $p[6] ?? ''];
        }

        return $out;
    }

    public static function set(array $values): void
    {
        foreach (array_intersect_key($values, self::defaults()) as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
