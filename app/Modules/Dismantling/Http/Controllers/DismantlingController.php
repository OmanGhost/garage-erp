<?php

namespace App\Modules\Dismantling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dismantling\Models\DismantledPart;
use App\Modules\Dismantling\Models\DismantledVehicle;
use App\Modules\Dismantling\Services\DismantlingService;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DismantlingController extends Controller
{
    public function __construct(protected DismantlingService $service) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function show(DismantledVehicle $vehicle)
    {
        $this->can('dismantling.view');
        $vehicle->load('make', 'vehicleModel', 'supplier', 'parts.part', 'parts.warehouse', 'scraps.bankAccount', 'warehouse');

        return view('dismantling::show', ['v' => $vehicle, 'profit' => $this->service->profitability($vehicle),
            'warehouses' => Options::warehouses(), 'banks' => Options::bankAccounts()]);
    }

    public function addPart(Request $request, DismantledVehicle $vehicle)
    {
        $this->can('dismantling.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'oem_number' => ['nullable', 'string', 'max:80'], 'grade' => ['required', 'in:A,B,C,damaged'],
            'estimated_value' => ['required', 'numeric', 'min:0'], 'sale_price' => ['nullable', 'numeric', 'min:0'],
            'part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', tenant()->id())],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())], 'notes' => ['nullable', 'string', 'max:250'],
            'photos' => ['nullable', 'array', 'max:6'], 'photos.*' => ['image', 'max:8192'],
        ]);
        $part = $this->service->addPart($vehicle, $data);
        foreach ($request->file('photos', []) as $f) {
            \App\Modules\Core\Services\FileStorage::attach($part, $f, 'part');
        }

        return back()->with('success', __('Extracted part registered with serial :s.', ['s' => $part->serial_number]));
    }

    /** Factory part numbers for the checklist (JSON). */
    public function suggest(Request $request)
    {
        $this->can('dismantling.view');

        return response()->json(['suggestions' => $this->service->suggestions(
            $request->integer('make') ?: null, $request->integer('model') ?: null, $request->integer('year') ?: null)]);
    }

    /** Parts ticked in the checklist on the dismantling file. */
    public function addChecklist(Request $request, DismantledVehicle $vehicle)
    {
        $this->can('dismantling.update');
        $request->validate(DismantlingService::checklistRules() + [
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', tenant()->id())],
        ]);
        $parts = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $vehicle) {
            $parts = $this->service->addChecklist($vehicle, (array) $request->input('checklist', []), $request->integer('warehouse_id') ?: $vehicle->warehouse_id);
            if ($parts && $request->boolean('checklist_to_stock')) {
                $this->can('dismantling.approve');
                $this->service->moveToStock($vehicle);
            }

            return $parts;
        });
        if (! $parts) {
            return back()->with('error', __('Tick at least one part.'));
        }

        return back()->with('success', $request->boolean('checklist_to_stock')
            ? __(':n parts registered and moved to stock.', ['n' => count($parts)])
            : __(':n parts registered. Move them to stock when ready.', ['n' => count($parts)]));
    }

    public function deletePart(DismantledPart $part)
    {
        $this->can('dismantling.update');
        abort_unless($part->status === 'pending', 403);
        $part->delete();

        return back();
    }

    public function moveToStock(Request $request, DismantledVehicle $vehicle)
    {
        $this->can('dismantling.approve');
        $moved = $this->service->moveToStock($vehicle, $request->boolean('final'));

        return back()->with('success', __(':n parts moved to stock with allocated cost.', ['n' => count($moved)]));
    }

    public function addCost(Request $request, DismantledVehicle $vehicle)
    {
        $this->can('dismantling.update');
        $data = $request->validate(['type' => ['required', 'in:transport_cost,dismantling_cost,other_costs'], 'amount' => ['required', 'numeric', 'gt:0'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())]]);
        $this->service->addCost($vehicle, $data['type'], $data['amount'], $data['bank_account_id'] ?? null);

        return back()->with('success', __('Cost added and posted.'));
    }

    public function scrap(Request $request, DismantledVehicle $vehicle)
    {
        $this->can('dismantling.update');
        $data = $request->validate(['description' => ['required', 'string', 'max:190'], 'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'], 'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', tenant()->id())]]);
        $this->service->recordScrap($vehicle, $data);

        return back()->with('success', __('Scrap recorded.'));
    }

    public function close(DismantledVehicle $vehicle)
    {
        $this->can('dismantling.approve');
        $this->service->close($vehicle);

        return back()->with('success', __('Vehicle file closed.'));
    }

    public function labels(DismantledVehicle $vehicle)
    {
        $this->can('dismantling.print');

        return redirect()->route('parts.labels', ['ids' => $vehicle->parts()->whereNotNull('part_id')->pluck('part_id')->all()]);
    }
}
