<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\VehicleGateLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vehicle gate log: every entry / exit (automatic at reception and delivery, or recorded by the gate), with printing. */
class GateLogController extends Controller
{
    protected function can(string $perm): void
    {
        abort_unless(auth()->user()->can($perm), 403);
    }

    public function index(Request $request)
    {
        $this->can('reception.view');
        $from = $request->query('from', today()->subDays(6)->toDateString());
        $to = $request->query('to', today()->toDateString());
        $q = trim((string) $request->query('q'));
        $logs = VehicleGateLog::query()->with('vehicle.make', 'vehicle.vehicleModel', 'vehicle.customer', 'jobCard.customer', 'user', 'authorization')
            ->whereDate('logged_at', '>=', $from)->whereDate('logged_at', '<=', $to)
            ->when(in_array($request->query('direction'), ['in', 'out'], true), fn ($w) => $w->where('direction', $request->query('direction')))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('person_name', 'like', "%$q%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', "%$q%")->orWhere('vin', 'like', "%$q%"))
                ->orWhereHas('jobCard', fn ($j) => $j->where('number', 'like', "%$q%"))))
            ->latest('logged_at')->latest('id')->paginate(50)->withQueryString();

        return view('workshop::gate-log', [
            'logs' => $logs, 'from' => $from, 'to' => $to,
            'stats' => [
                'in_today' => VehicleGateLog::query()->where('direction', 'in')->whereDate('logged_at', today())->count(),
                'out_today' => VehicleGateLog::query()->where('direction', 'out')->whereDate('logged_at', today())->count(),
                'inside' => JobCard::query()->open()->count(),
                'authorized' => VehicleGateLog::query()->whereNotNull('handover_authorization_id')->whereDate('logged_at', '>=', $from)->whereDate('logged_at', '<=', $to)->count(),
            ],
        ]);
    }

    /** Manual entry by the gate (test drive, road test, a vehicle leaving / returning without a status change…). */
    public function store(Request $request)
    {
        $this->can('reception.create');
        $c = tenant()->id();
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'job_card_id' => ['nullable', 'integer', Rule::exists('job_cards', 'id')->where('company_id', $c)],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'person_name' => ['nullable', 'string', 'max:150'],
            'person_id_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if (empty($data['job_card_id'])) {
            $data['job_card_id'] = JobCard::query()->where('vehicle_id', $data['vehicle_id'])->open()->latest('id')->value('id');
        }
        $log = VehicleGateLog::create($data + ['logged_at' => now(), 'user_id' => auth()->id()]);

        return redirect()->route('gate-logs.index')->with('success', __('Gate movement :r recorded.', ['r' => $log->reference()]))
            ->with('print_voucher', route('gate-logs.print', $log))->with('print_label', __('Print'));
    }

    public function print(VehicleGateLog $gateLog, string $format = 'a5')
    {
        $this->can('reception.view');
        $gateLog->load('vehicle.make', 'vehicle.vehicleModel', 'vehicle.customer', 'jobCard.customer', 'user', 'authorization');
        $html = view('workshop::print.gate-log', ['g' => $gateLog, 'paper' => $format === 'a4' ? 'A4' : 'A5'])->render();
        if ($format === 'pdf') {
            return response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$gateLog->reference().'.pdf"']);
        }

        return response($html);
    }
}
