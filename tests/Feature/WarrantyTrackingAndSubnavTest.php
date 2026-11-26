<?php

namespace Tests\Feature;

use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Warranty\Models\Warranty;
use App\Modules\Warranty\Models\WarrantyClaim;
use Tests\TestCase;

class WarrantyTrackingAndSubnavTest extends TestCase
{
    protected \App\Modules\Core\Models\Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->makeTenant();
        $this->app->setLocale('ar');
    }

    protected function warranty(): Warranty
    {
        return tenant()->run($this->company->id, function () {
            $customer = $this->makeCustomer(['name' => 'Yousuf Al Mahrooqi']);
            $v = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '3030', 'plate_country' => 'OM', 'vehicle_type' => 'car']);

            return Warranty::create(['customer_id' => $customer->id, 'vehicle_id' => $v->id, 'description' => 'Alternator replacement',
                'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(11)->toDateString(), 'km_limit' => 20000,
                'serial_number' => 'ALT-9988', 'status' => 'active']);
        });
    }

    public function test_warranty_card_prints(): void
    {
        $w = $this->warranty();
        $this->get('/warranties/'.$w->id.'/print')->assertOk()->assertSee('بطاقة ضمان')->assertSee('Alternator replacement')
            ->assertSee('ALT-9988')->assertSee('Yousuf Al Mahrooqi')->assertSee('WR-'.str_pad((string) $w->id, 6, '0', STR_PAD_LEFT));
        $this->get('/warranties/'.$w->id.'/print/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/m/warranties')->assertOk()->assertSee(route('warranties.print', $w), false);
    }

    public function test_claim_tracking_notes_and_print(): void
    {
        $w = $this->warranty();
        $this->post('/warranty/claims', ['warranty_id' => $w->id, 'odometer' => 5000, 'description' => 'Battery light on again', '_idem' => 'wc-1'])->assertRedirect();
        $c = tenant()->run($this->company->id, fn () => WarrantyClaim::query()->firstOrFail());

        $this->post('/warranty/claims/'.$c->id.'/note', ['note' => 'Customer will bring the old part'])->assertSessionHas('success');
        $this->post('/warranty/claims/'.$c->id.'/reject', ['inspection_result' => 'Damage caused by accident', '_idem' => 'wc-2'])->assertSessionHas('success');

        $events = tenant()->run($this->company->id, fn () => $c->events()->pluck('event')->sort()->values()->all());
        $this->assertSame(['note', 'opened', 'status'], $events);

        $this->get('/warranty/claims/'.$c->id)->assertOk()->assertSee('Customer will bring the old part')->assertSee('Damage caused by accident')
            ->assertSee(route('warranty.claims.print', $c), false)->assertSee(route('warranties.print', $w), false);
        $this->get('/warranty/claims/'.$c->id.'/print')->assertOk()->assertSee('مطالبة ضمان')->assertSee($c->number)
            ->assertSee('Battery light on again')->assertSee('Damage caused by accident')->assertSee('Customer will bring the old part');
        $this->get('/m/warranty-claims')->assertOk()->assertSee(route('warranty.claims.print', $c), false);
    }

    public function test_section_bar_shows_sibling_pages(): void
    {
        $w = $this->warranty();
        // list page of the section
        $this->get('/m/warranties')->assertOk()->assertSee('app-subnav', false)
            ->assertSee(route('crud.index', 'warranty-claims'), false)->assertSee(route('crud.index', 'equipment'), false);
        // a detail page outside /m/ still shows its section, with its list highlighted
        $this->post('/warranty/claims', ['warranty_id' => $w->id, 'odometer' => 100, 'description' => 'x', '_idem' => 'sb-1']);
        $c = tenant()->run($this->company->id, fn () => WarrantyClaim::query()->firstOrFail());
        $html = $this->get('/warranty/claims/'.$c->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#app-subnav-link active"[^>]*href="[^"]*m/warranty-claims"|href="[^"]*m/warranty-claims" class="app-subnav-link active"#', $html);
        // other sections
        $this->get('/inventory/transfers/create')->assertOk()->assertSee('app-subnav', false)->assertSee(route('crud.index', 'stock-levels'), false);
        // the dashboard has no section bar
        $this->get('/dashboard')->assertOk()->assertDontSee('class="app-subnav', false);
    }
}
