<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\CRM\Models\ReminderContact;
use App\Modules\CRM\Models\ServiceReminder;
use App\Modules\CRM\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Periodic maintenance reminders board: who is due, which messages were sent and whether they reached the
 * customer, manual contacts (call / WhatsApp / visit) with their outcome, and booking the visit.
 */
class ReminderController extends Controller
{
    public const STAGES = ['overdue', 'due', 'sent', 'delivered', 'failed', 'acknowledged', 'booked', 'done', 'cancelled'];

    protected function can(string $perm): void
    {
        abort_unless(auth()->user()->can($perm), 403);
    }

    public function index(Request $request)
    {
        $this->can('service_reminders.view');
        $view = $request->query('view', 'active'); // active | all | done
        $q = trim((string) $request->query('q'));
        $reminders = ServiceReminder::query()->with(['customer', 'vehicle.make', 'vehicle.vehicleModel', 'messages', 'contacts.user', 'appointment'])
            ->when($view === 'active', fn ($w) => $w->whereNotIn('status', ['done', 'cancelled']))
            ->when($view === 'done', fn ($w) => $w->whereIn('status', ['done', 'cancelled']))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', "%$q%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$q%")->orWhere('phone', 'like', "%$q%"))
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', "%$q%"))))
            ->when($request->query('from'), fn ($w, $d) => $w->whereDate('due_date', '>=', $d))
            ->when($request->query('to'), fn ($w, $d) => $w->whereDate('due_date', '<=', $d))
            ->orderBy('due_date')->get();

        $stages = $reminders->groupBy(fn (ServiceReminder $r) => $r->stage());
        $stage = $request->query('stage');
        $list = $stage && in_array($stage, self::STAGES, true) ? ($stages[$stage] ?? collect()) : $reminders;

        return view('crm::reminders', [
            'list' => $list, 'counts' => $stages->map->count(), 'total' => $reminders->count(), 'stage' => $stage, 'view' => $view,
            'dueThisWeek' => $reminders->filter(fn ($r) => $r->due_date && $r->due_date->between(today(), today()->addDays(7)))->count(),
        ]);
    }

    public function show(ServiceReminder $reminder)
    {
        $this->can('service_reminders.view');
        $reminder->load('customer', 'vehicle.make', 'vehicle.vehicleModel', 'messages', 'contacts.user', 'contacts.notificationLog', 'appointment', 'package');

        return view('crm::reminder', ['r' => $reminder]);
    }

    public function send(Request $request, ServiceReminder $reminder, ReminderService $service)
    {
        $this->can('service_reminders.update');
        $channel = $request->validate(['channel' => ['nullable', Rule::in(['sms', 'whatsapp', 'mail'])]])['channel'] ?? null;
        $n = $service->send($reminder, $channel);

        return back()->with($n ? 'success' : 'warning', $n
            ? __('Reminder sent to :c.', ['c' => $reminder->customer->name])
            : __('No message was sent: no SMS / WhatsApp / e-mail channel is configured for this customer. Use the call or WhatsApp buttons and log the contact.'));
    }

    public function contact(Request $request, ServiceReminder $reminder, ReminderService $service)
    {
        $this->can('service_reminders.update');
        $data = $request->validate([
            'channel' => ['required', Rule::in(ReminderContact::CHANNELS)],
            'outcome' => ['required', Rule::in(ReminderContact::OUTCOMES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $service->logContact($reminder, $data['channel'], $data['outcome'], $data['notes'] ?? null, $data['follow_up_on'] ?? null);

        return back()->with('success', __('Contact recorded: :o.', ['o' => status_label($data['outcome'])]));
    }

    public function book(Request $request, ServiceReminder $reminder, AppointmentService $appointments, ReminderService $service)
    {
        $this->can('appointments.create');
        $data = $request->validate(['starts_at' => ['required', 'date', 'after:now'], 'duration' => ['required', 'integer', 'min:15', 'max:480'], 'notes' => ['nullable', 'string', 'max:500']]);
        $a = $appointments->book([
            'customer_id' => $reminder->customer_id, 'vehicle_id' => $reminder->vehicle_id, 'starts_at' => $data['starts_at'], 'duration' => $data['duration'],
            'notes' => trim($reminder->title.' '.($data['notes'] ?? '')), 'source' => 'phone', 'status' => 'confirmed',
        ]);
        $reminder->forceFill(['appointment_id' => $a->id])->save();
        $service->logContact($reminder, 'call', 'booked', __('Appointment :n on :d', ['n' => $a->number, 'd' => $a->starts_at?->format('Y-m-d H:i')]));

        return back()->with('success', __('Appointment :n booked.', ['n' => $a->number]));
    }

    public function complete(ServiceReminder $reminder)
    {
        $this->can('service_reminders.update');
        $reminder->update(['status' => 'done']);

        return back()->with('success', __('Reminder closed.'));
    }
}
