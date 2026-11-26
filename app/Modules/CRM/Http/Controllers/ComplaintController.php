<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Complaint;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Complaint page: details, tracking history, follow-up (status / assignee / note / resolution) and printing. */
class ComplaintController extends Controller
{
    protected function can(string $perm): void
    {
        abort_unless(auth()->user()->can($perm), 403);
    }

    public function show(Complaint $complaint)
    {
        $this->can('complaints.view');
        $complaint->load('customer', 'vehicle', 'jobCard.vehicle', 'assignee', 'updates.user');

        return view('crm::complaint', ['c' => $complaint, 'users' => Options::users()]);
    }

    /** Follow-up: change status / assignee, add a note, record the resolution — one form. */
    public function followUp(Request $request, Complaint $complaint)
    {
        $this->can('complaints.update');
        $data = $request->validate([
            'status' => ['required', Rule::in(Complaint::STATUSES)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())],
            'note' => ['nullable', 'string', 'max:2000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);
        if (in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution'] ?? $complaint->resolution)) {
            return back()->withInput()->withErrors(['resolution' => __('Describe how the complaint was resolved before closing it.')]);
        }
        $complaint->fill([
            'status' => $data['status'], 'assigned_to' => $request->has('assigned_to') ? ($data['assigned_to'] ?? null) : $complaint->assigned_to,
            'resolution' => $data['resolution'] ?? $complaint->resolution,
        ]);
        if (in_array($complaint->status, ['resolved', 'closed'], true)) {
            $complaint->resolved_at ??= now();
        } else {
            $complaint->resolved_at = null;
        }
        $complaint->save();
        if (filled($data['note'] ?? null)) {
            $complaint->track('note', null, null, $data['note']);
        }

        return back()->with('success', __('Complaint :n updated.', ['n' => $complaint->displayNumber()]));
    }

    public function print(Complaint $complaint, string $format = 'a4')
    {
        $this->can('complaints.print');
        $complaint->load('customer', 'vehicle', 'jobCard.vehicle', 'assignee', 'updates.user');
        $html = view('crm::print.complaint', ['c' => $complaint, 'history' => $this->history($complaint)])->render();
        if ($format === 'pdf') {
            return response(\App\Support\Export\Exporter::pdfFromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$complaint->displayNumber().'.pdf"']);
        }

        return response($html);
    }

    /** Readable history lines (oldest first) used by the page and the printout. */
    public static function history(Complaint $c): \Illuminate\Support\Collection
    {
        return $c->updates->sortBy('id')->values()->map(fn ($u) => [
            'at' => $u->created_at?->format('Y-m-d H:i'),
            'user' => $u->user?->name ?? __('Customer / system'),
            'type' => $u->type,
            'text' => match ($u->type) {
                'created' => __('Complaint registered'),
                'status' => __('Status changed from :a to :b', ['a' => status_label((string) $u->from_status), 'b' => status_label((string) $u->to_status)]),
                'assigned' => __('Assigned to :u', ['u' => $u->note ?: '—']),
                'resolution' => __('Resolution recorded').': '.$u->note,
                default => $u->note,
            },
            'note' => in_array($u->type, ['created', 'note'], true) ? $u->note : null,
        ]);
    }
}
