<?php

namespace Tests\Feature;

use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\InvoiceService;
use App\Modules\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiTest extends TestCase
{
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
    }

    protected function login(): array
    {
        tenant()->forget();
        Auth::forgetUser();
        $this->app['auth']->forgetGuards();
        $res = $this->postJson('/api/v1/auth/token', ['email' => 'admin@acme.test', 'password' => 'Secret@12345', 'device_name' => 'phpunit'])
            ->assertOk()->assertJsonStructure(['token', 'abilities']);

        return ['Authorization' => 'Bearer '.$res->json('token'), 'Accept' => 'application/json'];
    }

    public function test_token_login_and_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/token', ['email' => 'admin@acme.test', 'password' => 'nope', 'device_name' => 'x'])->assertStatus(422);
        $h = $this->login();
        $this->getJson('/api/v1/me', $h)->assertOk()->assertJsonPath('email', 'admin@acme.test');
    }

    public function test_create_customer_vehicle_and_job_card_with_idempotency(): void
    {
        $h = $this->login();
        $c = $this->postJson('/api/v1/customers', ['name' => 'API Customer', 'phone' => '99887766'], $h)->assertCreated();
        $this->assertSame('+96899887766', $c->json('phone'));

        $v = $this->postJson('/api/v1/vehicles', ['customer_id' => $c->json('id'), 'plate_number' => '4455', 'plate_code' => 'AB'], $h)->assertCreated();

        $payload = ['customer_id' => $c->json('id'), 'vehicle_id' => $v->json('id'), 'customer_complaint' => 'Brake noise'];
        $this->postJson('/api/v1/job-cards', $payload, $h + ['Idempotency-Key' => 'k-1'])->assertCreated()->assertJsonPath('status', 'pending_inspection');
        $this->postJson('/api/v1/job-cards', $payload, $h + ['Idempotency-Key' => 'k-1'])->assertStatus(409);

        $this->getJson('/api/v1/job-cards', $h)->assertOk()->assertJsonPath('total', 1);
    }

    public function test_invoice_detail_includes_balance_due(): void
    {
        $part = $this->makePart();
        app(StockService::class)->receive($part, $this->mainWarehouse()->id, 5, 5, ['type' => 'opening']);
        $customer = $this->makeCustomer();
        $svc = app(InvoiceService::class);
        $inv = $svc->post($svc->createDraft(['customer_id' => $customer->id, 'warehouse_id' => $this->mainWarehouse()->id],
            [['item_type' => 'part', 'part_id' => $part->id, 'qty' => 1, 'unit_price' => 10, 'tax_rate' => 5]]));

        $h = $this->login();
        $this->getJson('/api/v1/invoices/'.$inv->id, $h)->assertOk()
            ->assertJsonPath('number', $inv->number)
            ->assertJsonPath('balance_due', '10.500');
    }

    public function test_api_cannot_reach_other_tenants_data(): void
    {
        $foreignVehicle = Vehicle::create(['customer_id' => $this->makeCustomer()->id, 'plate_number' => '1111']);
        $h = $this->login();
        $this->makeTenant('other', 'admin@other.test');
        tenant()->forget();
        Auth::forgetUser();
        $this->app['auth']->forgetGuards();
        $other = $this->postJson('/api/v1/auth/token', ['email' => 'admin@other.test', 'password' => 'Secret@12345', 'device_name' => 'x'])->json('token');

        $this->getJson('/api/v1/vehicles/'.$foreignVehicle->id, ['Authorization' => 'Bearer '.$other, 'Accept' => 'application/json'])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/vehicles/'.$foreignVehicle->id, $h)->assertOk();
    }
}
