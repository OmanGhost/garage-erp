<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Plan;
use App\Modules\Core\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SaasBillingTest extends TestCase
{
    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant('alpha', 'admin@alpha.test');
        tenant()->forget();
        Auth::forgetUser();
        $this->super = new User(['name' => 'Platform', 'email' => 'super@platform.test', 'password' => 'Secret@12345']);
        $this->super->is_super_admin = true;
        $this->super->is_active = true;
        $this->super->save();
        $this->actingAs($this->super);
    }

    private function custom(array $over = []): SubscriptionInvoice
    {
        $this->post('/admin/billing', $over + [
            'company_id' => $this->company->id, 'type' => 'custom', 'tax_rate' => 5, 'discount' => 10,
            'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString(), 'notes' => 'Thanks',
            'items' => [['description' => 'Setup & training', 'qty' => 2, 'unit_price' => 50], ['description' => '', 'qty' => 1, 'unit_price' => 99]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->flushSession(); // the "issued" flash carries the number into the next page

        return SubscriptionInvoice::query()->latest('id')->firstOrFail();
    }

    public function test_issue_custom_invoice_computes_totals_and_prints(): void
    {
        $inv = $this->custom();
        $this->assertCount(1, $inv->items);                        // the blank line is ignored
        $this->assertSame('100.000', $inv->subtotal);
        $this->assertSame('10.000', $inv->discount);
        $this->assertSame('4.500', $inv->tax_amount);              // 5% of 90
        $this->assertSame('94.500', $inv->amount);
        $this->assertSame('unpaid', $inv->status);
        $this->assertMatchesRegularExpression('/^SUB-\d{4}-000001$/', $inv->number);

        $this->get('/admin/billing')->assertOk()->assertSee($inv->number)->assertSee('Acme alpha');
        $this->get('/admin/billing/create')->assertOk();
        $this->get('/admin/billing/'.$inv->id)->assertOk()->assertSee('Setup &amp; training', false);
        $this->get('/admin/billing/'.$inv->id.'/print')->assertOk()->assertSee($inv->number)->assertSee('Setup &amp; training', false)->assertSee('Thanks');
        $this->get('/admin/billing/'.$inv->id.'/print/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->post('/admin/billing', ['company_id' => $this->company->id, 'type' => 'custom', 'tax_rate' => 5, 'issue_date' => today()->toDateString(),
            'due_date' => today()->toDateString(), 'items' => [['description' => '', 'qty' => 1, 'unit_price' => 1]]])->assertSessionHas('error');
    }

    public function test_renewal_extends_subscription_and_invoices_it(): void
    {
        $plan = Plan::query()->where('is_active', true)->orderBy('sort')->firstOrFail();
        $before = $this->company->fresh()->subscription_ends_at;
        $this->post('/admin/billing', [
            'company_id' => $this->company->id, 'type' => 'renewal', 'plan_id' => $plan->id, 'billing_cycle' => 'yearly', 'tax_rate' => 0,
            'issue_date' => today()->toDateString(), 'due_date' => today()->toDateString(), 'paid' => 1, 'payment_method' => 'bank_transfer', 'payment_reference' => 'TRX-9',
            'items' => [['description' => 'Extra user pack', 'qty' => 1, 'unit_price' => 15]],
        ])->assertSessionHasNoErrors();
        $inv = SubscriptionInvoice::query()->latest('id')->firstOrFail();
        $this->assertCount(2, $inv->items);
        $this->assertSame(\App\Support\Money::add($plan->price_yearly, 15), $inv->amount);
        $this->assertSame('paid', $inv->status);
        $this->assertSame('TRX-9', $inv->payment_reference);
        $this->assertNotNull($inv->subscription_id);
        $c = $this->company->fresh();
        $this->assertSame($plan->id, $c->plan_id);
        $this->assertTrue($c->subscription_ends_at->greaterThan($before ?? now()));
        $this->get('/admin/billing/'.$inv->id.'/print')->assertOk()->assertSee('PAID');

        // the old quick renew on the client file goes through the same service and offers the print link
        $this->post('/admin/companies/'.$this->company->id.'/renew', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertSessionHas('print_voucher');
        $this->assertSame(2, SubscriptionInvoice::query()->count());
    }

    public function test_pay_cancel_and_overdue_filter(): void
    {
        $a = $this->custom(['due_date' => today()->toDateString()]);
        $a->forceFill(['issue_date' => today()->subDays(10), 'due_date' => today()->subDay()])->save();
        $b = $this->custom();

        $this->assertSame('overdue', $a->fresh()->stage());
        $this->get('/admin/billing?status=overdue')->assertOk()->assertSee($a->number)->assertDontSee($b->number);

        $this->post('/admin/billing/'.$a->id.'/pay', ['payment_method' => 'cash', 'payment_reference' => 'R-1'])->assertSessionHas('success');
        $this->assertSame('paid', $a->fresh()->status);
        $this->post('/admin/billing/'.$a->id.'/cancel', ['reason' => 'x'])->assertSessionHas('error');   // paid cannot be cancelled

        $this->post('/admin/billing/'.$b->id.'/cancel', [])->assertSessionHasErrors('reason');
        $this->post('/admin/billing/'.$b->id.'/cancel', ['reason' => 'Issued by mistake'])->assertSessionHas('success');
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->get('/admin/billing?status=cancelled')->assertOk()->assertSee($b->number)->assertDontSee($a->number);
    }

    public function test_settings_are_printed_on_invoices(): void
    {
        $this->post('/admin/billing/settings', ['billing_name' => 'Platform LLC', 'billing_vat_number' => 'OM1100223344', 'billing_tax_rate' => 5,
            'billing_due_days' => 14, 'billing_bank_details' => 'Bank Muscat 0123', 'billing_terms' => 'Pay within 14 days'])->assertSessionHas('success');
        $inv = $this->custom();
        $this->get('/admin/billing/'.$inv->id.'/print')->assertOk()->assertSee('Platform LLC')->assertSee('OM1100223344')
            ->assertSee('Bank Muscat 0123')->assertSee('Pay within 14 days')->assertSee('Tax Invoice');
    }

    public function test_client_sees_and_prints_only_own_invoices(): void
    {
        $mine = $this->custom();
        $cancelled = $this->custom();
        $cancelled->forceFill(['status' => 'cancelled'])->save();
        $otherCompany = $this->company;
        $this->makeTenant('beta', 'admin@beta.test');
        tenant()->forget();
        Auth::forgetUser();
        $this->actingAs($this->super);
        $theirs = $this->custom(['company_id' => $this->company->id]);

        // sign in as alpha's admin
        $alpha = User::query()->where('email', 'admin@alpha.test')->firstOrFail();
        tenant()->forget();
        Auth::forgetUser();
        $this->actingAs($alpha);
        $this->get('/billing')->assertOk()->assertSee($mine->number)->assertDontSee($theirs->number)->assertDontSee($cancelled->number);
        $this->get('/billing/invoices/'.$mine->id)->assertOk()->assertSee($mine->number);
        $this->get('/billing/invoices/'.$mine->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/billing/invoices/'.$theirs->id)->assertNotFound();
        $this->get('/billing/invoices/'.$cancelled->id)->assertNotFound();
        $this->assertSame($otherCompany->id, $mine->company_id);
    }
}
