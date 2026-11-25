<?php

namespace App\Modules\Workshop\Services;

use App\Exceptions\BusinessException;
use App\Modules\Sales\Models\Invoice;
use App\Modules\Workshop\Models\JobCard as J;
use App\Support\Money;
use App\Support\Settings;

/**
 * Step-by-step guide shown on the job card: which of the 8 phases the vehicle is in,
 * what is still required (checklist) and the next action, from reception to delivery.
 *
 * The checklist mirrors JobCardService::guard(); the "move" button is validated by
 * calling guard() itself, so the guide never promises a move the system would refuse.
 */
final class JobCardGuide
{
    /** phase => job card statuses belonging to it */
    public const PHASES = [
        'reception' => [],
        'inspection' => [J::PENDING_INSPECTION, J::UNDER_INSPECTION],
        'quotation' => [J::AWAITING_QUOTATION, J::AWAITING_APPROVAL],
        'parts' => [J::APPROVED, J::AWAITING_PARTS],
        'work' => [J::IN_PROGRESS],
        'qc' => [J::QUALITY_INSPECTION],
        'invoice' => [J::READY],
        'delivery' => [J::DELIVERED, J::COMPLETED],
    ];

    public const ICONS = [
        'reception' => 'bi-car-front', 'inspection' => 'bi-search', 'quotation' => 'bi-file-earmark-text', 'parts' => 'bi-box-seam',
        'work' => 'bi-tools', 'qc' => 'bi-patch-check', 'invoice' => 'bi-receipt', 'delivery' => 'bi-key',
    ];

    public function __construct(protected JobCardService $jobs) {}

    public function build(J $jc): array
    {
        $status = $jc->status;
        $onHold = $status === J::ON_HOLD;
        // while on hold, show the phase the card was in before it was paused
        $effective = $onHold ? ($jc->histories->where('to_status', J::ON_HOLD)->sortByDesc('id')->first()?->from_status ?? J::PENDING_INSPECTION) : $status;

        $keys = array_keys(self::PHASES);
        $current = $this->phaseOf($effective);
        $currentIndex = array_search($current, $keys, true);

        $phases = [];
        foreach ($keys as $i => $key) {
            $phases[] = [
                'key' => $key, 'number' => $i + 1, 'icon' => self::ICONS[$key],
                'title' => __('guide.phases.'.$key.'.title'),
                'state' => $status === J::CANCELLED ? ($i < $currentIndex ? 'done' : 'cancelled')
                    : ($status === J::COMPLETED || $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'upcoming')),
            ];
        }

        return [
            'phases' => $phases,
            'current' => $current,
            'number' => $currentIndex + 1,
            'total' => count($keys),
            'on_hold' => $onHold,
            'cancelled' => $status === J::CANCELLED,
            'finished' => $status === J::COMPLETED,
            'title' => __('guide.phases.'.$current.'.title'),
            'who' => __('guide.phases.'.$current.'.who'),
            'instruction' => __('guide.phases.'.$current.'.'.($status === J::COMPLETED ? 'done' : 'do')),
            'checklist' => $this->checklist($jc, $current),
            'actions' => $onHold || in_array($status, [J::CANCELLED, J::COMPLETED], true) ? [] : $this->actions($jc, $current),
        ];
    }

    public function phaseOf(string $status): string
    {
        foreach (self::PHASES as $key => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $key;
            }
        }

        return 'inspection';
    }

    /** Facts about the job card used by the checklist and actions. */
    protected function facts(J $jc): array
    {
        $tasks = $jc->tasks->where('approval_status', 'approved')->where('status', '!=', 'cancelled');
        $parts = $jc->parts->where('approval_status', 'approved')->where('status', '!=', 'cancelled');
        $posted = $jc->invoices->whereIn('status', ['posted', 'partially_paid', 'paid']);
        $qc = $jc->qualityChecks->sortByDesc('checked_at')->first();
        $paymentRequired = Settings::bool('workshop.require_payment_before_delivery') && ! $jc->fleet_id && ! $jc->insurance_claim_id
            && (int) $jc->customer->payment_terms_days === 0;
        $free = in_array($jc->job_type, ['warranty', 'internal'], true) || $jc->fleet_id;

        return [
            'tasks' => $tasks->count(),
            'tasks_done' => $tasks->where('status', 'done')->count(),
            'tasks_assigned' => $tasks->whereNotNull('technician_id')->count(),
            'tasks_started' => $tasks->whereIn('status', ['in_progress', 'paused', 'done'])->count(),
            'parts' => $parts->count(),
            'parts_issued' => $parts->filter(fn ($p) => Money::cmp($p->qty_issued, $p->qty) >= 0)->count(),
            'pending_approval' => $jc->tasks->where('approval_status', 'pending')->count() + $jc->parts->where('approval_status', 'pending')->count(),
            'inspection' => $jc->inspections->sortByDesc('id')->first(),
            'quotation' => $jc->quotations->where('is_supplementary', false)->sortByDesc('id')->first(),
            'qc_required' => Settings::bool('workshop.require_qc'),
            'qc_passed' => $qc && $qc->result === 'pass',
            'invoiced' => $posted->isNotEmpty() || $free,
            'draft_invoice' => $jc->invoices->whereIn('status', ['draft', 'held'])->first(),
            'invoice' => $posted->first(),
            'balance' => Money::sum($posted->map(fn (Invoice $i) => $i->balanceDue())),
            'payment_required' => $paymentRequired,
            'free' => $free,
        ];
    }

    protected function item(string $key, bool $ok, array $params = [], bool $optional = false): array
    {
        return ['label' => __('guide.check.'.$key, $params), 'ok' => $ok, 'optional' => $optional];
    }

    protected function checklist(J $jc, string $phase): array
    {
        $f = $this->facts($jc);
        $insp = $f['inspection'];
        $q = $f['quotation'];

        return match ($phase) {
            'inspection' => [
                $this->item('inspection_started', (bool) $insp),
                $this->item('inspection_completed', $insp?->status === 'completed'),
            ],
            'quotation' => [
                $this->item('quotation_created', (bool) $q),
                $this->item('quotation_sent', $q && in_array($q->status, ['sent', 'approved', 'partially_approved', 'converted'], true)),
                $this->item('quotation_approved', $q && in_array($q->status, ['approved', 'partially_approved', 'converted'], true)),
            ],
            'parts', 'work' => array_values(array_filter([
                $this->item('tasks_approved', $f['tasks'] > 0, ['n' => $f['tasks']]),
                $this->item('technicians_assigned', $f['tasks'] > 0 && $f['tasks_assigned'] === $f['tasks'], ['a' => $f['tasks_assigned'], 'n' => $f['tasks']]),
                $f['parts'] ? $this->item('parts_issued', $f['parts_issued'] === $f['parts'], ['a' => $f['parts_issued'], 'n' => $f['parts']]) : null,
                $this->item('tasks_done', $f['tasks'] > 0 && $f['tasks_done'] === $f['tasks'], ['a' => $f['tasks_done'], 'n' => $f['tasks']]),
                $f['pending_approval'] ? $this->item('additional_pending', false, ['n' => $f['pending_approval']]) : null,
            ])),
            'qc' => [
                $this->item('tasks_done', $f['tasks_done'] === $f['tasks'], ['a' => $f['tasks_done'], 'n' => $f['tasks']]),
                $this->item('qc_passed', $f['qc_passed']),
            ],
            'invoice', 'delivery' => array_values(array_filter([
                $f['qc_required'] ? $this->item('qc_passed', $f['qc_passed']) : null,
                $f['free'] ? $this->item('no_invoice_needed', true) : $this->item('invoice_posted', $f['invoiced']),
                $f['invoice'] ? $this->item($f['payment_required'] ? 'balance_paid' : 'balance_paid_optional', ! Money::isPositive($f['balance']),
                    ['b' => money($f['balance'])], ! $f['payment_required']) : null,
                $phase === 'delivery' ? $this->item('vehicle_delivered', in_array($jc->status, [J::DELIVERED, J::COMPLETED], true)) : null,
            ])),
            default => [],
        };
    }

    /**
     * Next actions for the current phase. Types: tab (open a tab on this page), link, post (form to url),
     * status (change status), anchor (scroll to an element). The first one flagged primary is highlighted.
     */
    protected function actions(J $jc, string $phase): array
    {
        $f = $this->facts($jc);
        $a = [];
        switch ($phase) {
            case 'inspection':
                if ($f['inspection'] && $f['inspection']->status !== 'completed') {
                    $a[] = ['type' => 'link', 'url' => route('inspections.show', $f['inspection']), 'label' => __('guide.act.continue_inspection'), 'icon' => 'bi-search', 'perm' => 'inspections.update', 'primary' => true];
                } else {
                    $a[] = ['type' => 'tab', 'target' => '#tab-insp', 'label' => __('guide.act.start_inspection'), 'icon' => 'bi-search', 'perm' => 'inspections.create', 'primary' => true];
                }
                $a[] = ['type' => 'link', 'url' => route('quotations.sales.create', ['job_card_id' => $jc->id]), 'label' => __('guide.act.quote_without_inspection'), 'icon' => 'bi-file-earmark-plus', 'perm' => 'quotations.create'];
                break;

            case 'quotation':
                $q = $f['quotation'];
                if (! $q) {
                    $insp = $f['inspection'];
                    if ($insp && $insp->status === 'completed') {
                        $a[] = ['type' => 'post', 'url' => route('inspections.quotation', $insp), 'label' => __('guide.act.quote_from_inspection'), 'icon' => 'bi-magic', 'perm' => 'quotations.create', 'primary' => true];
                    }
                    $a[] = ['type' => 'link', 'url' => route('quotations.sales.create', ['job_card_id' => $jc->id]), 'label' => __('guide.act.new_quotation'), 'icon' => 'bi-file-earmark-plus', 'perm' => 'quotations.create', 'primary' => ! $insp];
                } else {
                    $a[] = ['type' => 'link', 'url' => route('quotations.show', $q), 'label' => __($q->status === 'draft' ? 'guide.act.send_quotation' : 'guide.act.record_approval'),
                        'icon' => $q->status === 'draft' ? 'bi-send' : 'bi-check2-square', 'perm' => 'quotations.view', 'primary' => true];
                }
                break;

            case 'parts':
                if ($f['tasks'] === 0) {
                    // approved without any task (e.g. approved manually): the first thing to do is add the work
                    $a[] = ['type' => 'tab', 'target' => '#tab-tasks', 'label' => __('guide.act.add_task'), 'icon' => 'bi-plus-circle', 'perm' => 'job_cards.update', 'primary' => true];
                    break;
                }
                if ($f['parts'] > $f['parts_issued']) {
                    $a[] = ['type' => 'tab', 'target' => '#tab-parts', 'label' => __('guide.act.issue_parts'), 'icon' => 'bi-box-arrow-up', 'perm' => 'stock.issue', 'primary' => true];
                    $a[] = ['type' => 'post', 'url' => route('job-cards.purchase-request', $jc), 'label' => __('guide.act.request_purchase'), 'icon' => 'bi-cart-plus', 'perm' => 'purchase_requests.create'];
                }
                $a[] = ['type' => 'tab', 'target' => '#tab-tasks', 'label' => __('guide.act.assign_start'), 'icon' => 'bi-play-circle', 'perm' => 'job_cards.view', 'primary' => $f['parts'] === $f['parts_issued']];
                break;

            case 'work':
                $a[] = ['type' => 'tab', 'target' => '#tab-tasks', 'label' => __('guide.act.finish_tasks'), 'icon' => 'bi-check2-all', 'perm' => 'job_cards.view', 'primary' => $f['tasks_done'] < $f['tasks']];
                if ($f['parts'] > $f['parts_issued']) {
                    $a[] = ['type' => 'tab', 'target' => '#tab-parts', 'label' => __('guide.act.issue_parts'), 'icon' => 'bi-box-arrow-up', 'perm' => 'stock.issue'];
                }
                $a[] = $this->move($jc, $f['qc_required'] ? J::QUALITY_INSPECTION : J::READY, $f['tasks_done'] === $f['tasks'] && $f['tasks'] > 0);
                break;

            case 'qc':
                $a[] = ['type' => 'anchor', 'target' => '#qc-card', 'label' => __('guide.act.do_qc'), 'icon' => 'bi-patch-check', 'perm' => 'quality_checks.create', 'primary' => true];
                break;

            case 'invoice':
                if (! $f['invoiced']) {
                    $a[] = $f['draft_invoice']
                        ? ['type' => 'link', 'url' => route('invoices.show', $f['draft_invoice']), 'label' => __('guide.act.post_invoice'), 'icon' => 'bi-receipt', 'perm' => 'invoices.view', 'primary' => true]
                        : ['type' => 'post', 'url' => route('job-cards.invoice', $jc), 'label' => __('guide.act.prepare_invoice'), 'icon' => 'bi-receipt', 'perm' => 'invoices.create', 'primary' => true];
                } elseif (Money::isPositive($f['balance'])) {
                    $a[] = ['type' => 'link', 'url' => route('payments.receive', ['customer_id' => $jc->customer_id, 'invoice_id' => $f['invoice']->id]),
                        'label' => __('guide.act.receive_payment', ['b' => money($f['balance'])]), 'icon' => 'bi-cash-coin', 'perm' => 'payments.create', 'primary' => $f['payment_required']];
                }
                if ($f['invoice']) {
                    $a[] = ['type' => 'link', 'url' => route('invoices.print', [$f['invoice'], 'a4']), 'label' => __('guide.act.print_invoice'), 'icon' => 'bi-printer', 'perm' => 'invoices.print', 'blank' => true];
                }
                $a[] = $this->move($jc, J::DELIVERED, $f['invoiced'] && (! $f['payment_required'] || ! Money::isPositive($f['balance'])));
                break;

            case 'delivery':
                $a[] = $this->move($jc, J::COMPLETED, true);
                break;
        }

        return array_values(array_filter($a));
    }

    /** Status change button, disabled with the exact reason when the system would refuse it. */
    protected function move(J $jc, string $to, bool $ready): array
    {
        $reason = null;
        if (! JobCardWorkflow::can($jc->status, $to)) {
            $reason = __('Cannot move job card from :a to :b.', ['a' => status_label($jc->status), 'b' => status_label($to)]);
        } else {
            try {
                $this->jobs->guard($jc, $to);
            } catch (BusinessException $e) {
                $reason = $e->getMessage();
            }
        }

        return [
            'type' => 'status', 'status' => $to, 'icon' => $to === J::DELIVERED ? 'bi-key' : ($to === J::COMPLETED ? 'bi-flag' : 'bi-arrow-right-circle'),
            'label' => __('guide.act.move_'.$to), 'perm' => 'job_cards.change_status',
            'primary' => $ready && ! $reason, 'disabled' => (bool) $reason, 'reason' => $reason,
            'confirm' => $to === J::DELIVERED ? __('guide.confirm_delivery') : null,
        ];
    }
}
