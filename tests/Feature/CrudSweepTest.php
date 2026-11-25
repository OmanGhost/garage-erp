<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\HR\Models\Employee;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Modules\Workshop\Models\Department;
use App\Modules\Workshop\Models\Service;
use App\Modules\Workshop\Services\JobCardService;
use App\Support\Crud\CrudRegistry;
use App\Support\Crud\Field;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Submits every generic CRUD form with only its required fields filled (optional ones
 * empty), then opens, edits and re-saves the record. Any server error (5xx) fails.
 * Catches problems such as empty optional inputs hitting NOT NULL columns.
 */
class CrudSweepTest extends TestCase
{
    protected array $ids = [];

    protected array $used = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTenant();
        $customer = $this->makeCustomer();
        $make = VehicleMake::query()->where('name', 'Toyota')->firstOrFail();
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'plate_number' => '1234', 'vehicle_make_id' => $make->id, 'vehicle_model_id' => $make->models()->value('id')]);
        $part = $this->makePart();
        $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Supplier']);
        $employee = Employee::create(['code' => 'E-1', 'name' => 'Employee', 'hire_date' => now()->subYear()->toDateString(), 'basic_salary' => 300, 'user_id' => $this->admin->id]);
        $service = Service::create(['code' => 'SV-1', 'name_ar' => 'خدمة', 'name_en' => 'Service', 'price' => 5, 'department_id' => Department::query()->value('id')]);
        $jc = app(JobCardService::class)->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'customer_complaint' => 'x']);
        $this->ids = [
            'customers' => $customer->id, 'vehicles' => $vehicle->id, 'parts' => $part->id, 'suppliers' => $supplier->id,
            'employees' => $employee->id, 'services' => $service->id, 'job_cards' => $jc->id, 'users' => $this->admin->id,
            'technicians' => $this->admin->id, 'vehicle_models' => $make->models()->value('id'),
            'accounts' => \App\Modules\Accounting\Models\Account::query()->where('is_group', false)->value('id'),
        ];
        tenant()->forget();
    }

    protected function valueFor(Field $f, int $i, string $slug): mixed
    {
        $rules = implode('|', array_map(fn ($r) => is_string($r) ? $r : '', (array) $f->rulesFor(null)));
        $options = $f->type === 'select' ? array_keys($f->getOptions()) : [];
        if ($f->lookup) {
            return $this->ids[$f->lookup] ?? null;
        }
        if ($options) {
            // paired fields sharing an option set (from / to account) get different values
            $key = md5($slug.json_encode($options));
            $free = array_values(array_diff($options, $this->used[$key] ?? [])) ?: $options;
            $pick = $f->default !== null && in_array($f->default, $free, false) ? $f->default : $free[count($free) - 1];
            $this->used[$key][] = $pick;

            return $pick;
        }
        if (str_contains($rules, 'url')) {
            return 'https://example.om/hook';
        }
        $unique = Str::lower(Str::random(6));

        return match ($f->type) {
            'email' => "sweep.$unique@example.om",
            'tel' => '9'.random_int(1000000, 9999999),
            'integer', 'decimal' => $f->default ?? 1,
            'date' => str_contains($rules, 'after') ? now()->addDays(10 + $i)->toDateString() : now()->toDateString(),
            'datetime' => str_contains($rules, 'after') ? now()->addHours(2)->format('Y-m-d\TH:i') : now()->subHour()->format('Y-m-d\TH:i'),
            'time' => str_contains($rules, 'after') ? '17:00' : '08:00',
            'checkbox' => 1,
            'color' => '#1f6feb',
            'password' => 'Sweep@Pass12345',
            'url' => 'https://example.om',
            default => preg_match('/code|sku|number|prefix|slug/', $f->name) ? strtoupper($slug === 'document-sequences' ? 'SWP' : 'SW-'.$unique) : 'Sweep '.$unique,
        };
    }

    public function test_every_crud_form_saves_with_only_required_fields(): void
    {
        $failures = $unfilled = [];
        $saved = 0;
        foreach (CrudRegistry::all() as $slug => $class) {
            tenant()->forget();
            $index = $this->get("/m/$slug");
            if ($index->status() >= 500) {
                $failures[] = "$slug index: ".$index->status();
            }
            tenant()->set($this->company->id);
            $resource = new $class;
            if ($class::$readonly || ! $resource->can('create')) {
                continue;
            }
            $payload = ['_idem' => (string) Str::uuid()];
            foreach ($resource->formFields(null) as $i => $f) {
                $fr = implode('|', array_filter((array) $f->rulesFor(null), 'is_string'));
                if ($f->required || preg_match('/(^|\|)required/', $fr)) {
                    $payload[$f->name] = $this->valueFor($f, $i, $slug);
                }
            }
            tenant()->forget();
            $create = $this->get("/m/$slug/create");
            if ($create->status() >= 500) {
                $failures[] = "$slug create form: ".$create->status().' '.($create->exception?->getMessage() ?? '');
                continue;
            }
            if ($create->isRedirect()) {
                continue; // resource uses a dedicated screen (covered by other tests)
            }
            $res = $this->post("/m/$slug", $payload);
            if ($res->status() >= 500) {
                $failures[] = "$slug store: ".($res->exception?->getMessage() ?? $res->status());
                continue;
            }
            if (session('errors')) {
                $unfilled[] = "$slug: ".implode(' / ', session('errors')->all());
                continue;
            }
            $saved++;
            tenant()->set($this->company->id);
            $model = ($class::$model)::query()->latest('id')->first();
            tenant()->forget();
            foreach (["/m/$slug/{$model->id}", "/m/$slug/{$model->id}/edit"] as $url) {
                $r = $this->get($url);
                if ($r->status() >= 500) {
                    $failures[] = "$url: ".($r->exception?->getMessage() ?? $r->status());
                }
            }
            $upd = $this->put("/m/$slug/{$model->id}", $payload + ['_idem' => (string) Str::uuid()]);
            if ($upd->status() >= 500) {
                $failures[] = "$slug update: ".($upd->exception?->getMessage() ?? $upd->status());
            }
        }

        if ($unfilled) {
            fwrite(STDERR, "\n[sweep] saved $saved forms; not auto-fillable (validation, not errors):\n  ".implode("\n  ", $unfilled)."\n");
        }
        $this->assertSame([], $failures, "Saved $saved forms. Server errors:\n".implode("\n", $failures));
        $this->assertGreaterThan(50, $saved);
    }
}
