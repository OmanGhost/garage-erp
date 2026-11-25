<?php

namespace App\Livewire;

use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Models\Part;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Vehicles\Models\Vehicle;
use App\Modules\Workshop\Models\JobCard;
use Livewire\Component;

/**
 * Topbar search across customers, vehicles (plate / VIN), job cards, parts
 * (name / SKU / barcode / OEM) and invoices — respecting permissions.
 */
class GlobalSearch extends Component
{
    public string $q = '';

    public function render()
    {
        $results = [];
        $term = trim($this->q);
        $user = auth()->user();
        if (mb_strlen($term) >= 2 && $user) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            if ($user->can('job_cards.view')) {
                foreach (JobCard::query()->with('vehicle')->where('number', 'like', $like)
                    ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', $like)->orWhere('vin', 'like', $like))
                    ->latest('id')->limit(5)->get() as $j) {
                    $results[] = ['group' => __('Job Cards'), 'icon' => 'bi-clipboard-check', 'title' => $j->number, 'sub' => $j->vehicle?->label().' · '.status_label($j->status), 'url' => route('job-cards.show', $j)];
                }
            }
            if ($user->can('customers.view')) {
                foreach (Customer::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('code', $term))->limit(5)->get() as $c) {
                    $results[] = ['group' => __('Customers'), 'icon' => 'bi-person', 'title' => $c->name, 'sub' => $c->phone, 'url' => route('customers.show', $c)];
                }
            }
            if ($user->can('vehicles.view')) {
                foreach (Vehicle::query()->with('make', 'vehicleModel')->where(fn ($w) => $w->where('plate_number', 'like', $like)->orWhere('vin', 'like', $like))->limit(5)->get() as $v) {
                    $results[] = ['group' => __('Vehicles'), 'icon' => 'bi-car-front', 'title' => $v->plate(), 'sub' => $v->title().' · '.$v->vin, 'url' => route('vehicles.show', $v)];
                }
            }
            if ($user->can('parts.view')) {
                foreach (Part::query()->where(fn ($w) => $w->where('sku', $term)->orWhere('barcode', $term)->orWhere('oem_number', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like))->limit(6)->get() as $p) {
                    $results[] = ['group' => __('Spare Parts'), 'icon' => 'bi-gear', 'title' => $p->label(), 'sub' => money($p->sale_price), 'url' => route('parts.show', $p)];
                }
            }
            if ($user->can('invoices.view')) {
                foreach (Invoice::query()->where('number', 'like', $like)->limit(5)->get() as $i) {
                    $results[] = ['group' => __('Invoices'), 'icon' => 'bi-receipt', 'title' => $i->number, 'sub' => money($i->total), 'url' => route('invoices.show', $i)];
                }
            }
        }

        return view('livewire.global-search', ['results' => collect($results)->groupBy('group')]);
    }
}
