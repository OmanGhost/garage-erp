<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentService;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(protected AppointmentService $service) {}

    /** Day / week / month calendar with capacity indicators. */
    public function calendar(Request $request)
    {
        abort_unless(auth()->user()->can('appointments.view'), 403);
        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'week';
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        [$from, $to] = match ($view) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'month' => [$date->copy()->startOfMonth()->startOfWeek(Carbon::SATURDAY), $date->copy()->endOfMonth()->endOfWeek(Carbon::FRIDAY)],
            default => [$date->copy()->startOfWeek(Carbon::SATURDAY), $date->copy()->endOfWeek(Carbon::FRIDAY)],
        };
        $appointments = Appointment::query()->with('customer', 'vehicle', 'department', 'technician')
            ->when($request->integer('department_id'), fn ($q, $d) => $q->where('department_id', $d))
            ->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->get();

        return view('appointments::calendar', compact('view', 'date', 'from', 'to', 'appointments') + [
            'departments' => Options::departments(), 'technicians' => Options::technicians(),
            'bays' => \App\Modules\Workshop\Models\WorkshopBay::query()->where('is_active', true)->pluck('name', 'id'),
            'capacity' => \App\Modules\Core\Models\Branch::query()->find(tenant()->branchId())?->daily_capacity,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('appointments.create'), 403);
        $c = tenant()->id();
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $c)],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'department_id' => ['nullable', 'integer'], 'service_id' => ['nullable', 'integer'], 'technician_id' => ['nullable', 'integer'], 'bay_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'], 'duration' => ['required', 'integer', 'min:15', 'max:1440'], 'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'in:staff,phone,whatsapp'],
        ]);
        $a = $this->service->book($data + ['status' => 'confirmed'], $request->boolean('notify', true));

        return redirect()->route('appointments.calendar', ['date' => $a->starts_at->toDateString()])->with('success', __('Appointment :n booked.', ['n' => $a->number]));
    }

    public function show(Appointment $appointment)
    {
        abort_unless(auth()->user()->can('appointments.view'), 403);

        return view('appointments::show', ['a' => $appointment->load('customer', 'vehicle', 'department', 'technician', 'bay', 'service', 'jobCard')]);
    }

    public function status(Request $request, Appointment $appointment)
    {
        abort_unless(auth()->user()->can('appointments.update'), 403);
        $status = $request->validate(['status' => ['required', 'in:scheduled,confirmed,no_show,cancelled,completed']])['status'];
        $appointment->update(['status' => $status]);

        return back()->with('success', __('Appointment updated.'));
    }

    public function reschedule(Request $request, Appointment $appointment)
    {
        abort_unless(auth()->user()->can('appointments.update'), 403);
        $data = $request->validate(['starts_at' => ['required', 'date']]);
        $this->service->reschedule($appointment, $data['starts_at']);

        return back()->with('success', __('Appointment rescheduled.'));
    }
}
