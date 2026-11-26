<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use Tests\TestCase;

class PopupsTest extends TestCase
{
    public function test_add_edit_and_delete_show_named_notifications(): void
    {
        $this->makeTenant();
        tenant()->forget();

        $this->post('/m/customers', ['code' => 'C-77', 'name' => 'Salim Al Harthy', 'phone' => '92001122', 'type' => 'individual', '_idem' => 'p1'])
            ->assertSessionHas('success_title', 'تمت الإضافة')
            ->assertSessionHas('success', 'تمت إضافة «Salim Al Harthy» إلى العملاء.');

        tenant()->set($this->company->id);
        $c = Customer::query()->where('name', 'Salim Al Harthy')->firstOrFail();
        tenant()->forget();

        $this->put('/m/customers/'.$c->id, ['code' => 'C-77', 'name' => 'Salim Al Harthy', 'phone' => '92001123', 'type' => 'individual'])
            ->assertSessionHas('success_title', 'تم التعديل');

        // the page after saving carries the notification for ui.js, the confirm dialog script and styles
        $this->followingRedirects()->put('/m/customers/'.$c->id, ['code' => 'C-77', 'name' => 'Salim', 'phone' => '92001123', 'type' => 'individual'])
            ->assertSee('id="erp-flash"', false)->assertSee('js/ui.js', false)->assertSee('css/ui.css', false)
            ->assertSee('+96892001123');

        // list delete buttons name the record in the confirmation
        $this->get('/m/customers')->assertSee('<bdi dir="ltr">+96892001123</bdi>', false)->assertSee('حذف «Salim»؟ لا يمكن التراجع عن هذه العملية.', false);

        $this->delete('/m/customers/'.$c->id)
            ->assertSessionHas('success_title', 'تم الحذف')
            ->assertSessionHas('success', 'تم حذف «Salim» من العملاء.');
    }

    public function test_errors_are_sent_as_a_notification_too(): void
    {
        $this->makeTenant();
        tenant()->forget();
        $this->followingRedirects()->from('/m/customers/create')->post('/m/customers', ['_idem' => 'p2'])
            ->assertSee('id="erp-flash"', false)->assertSee('ui-inline-errors', false);
    }
}
