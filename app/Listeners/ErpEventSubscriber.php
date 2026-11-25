<?php

namespace App\Listeners;

use App\Modules\Core\Notifications\SystemMessage;
use App\Modules\Core\Services\NotificationService;
use App\Modules\Core\Services\WebhookService;
use App\Modules\Sales\Events\InvoicePosted;
use App\Modules\Sales\Events\PaymentReceived;
use App\Modules\Workshop\Events\JobCardStatusChanged;
use App\Modules\Workshop\Models\JobCard;
use Illuminate\Events\Dispatcher;

/**
 * Cross-module reactions: customer notifications, staff in-app alerts and webhooks.
 */
class ErpEventSubscriber
{
    public function onJobCardStatus(JobCardStatusChanged $e): void
    {
        $jc = $e->jobCard;
        tenant()->id() ?? tenant()->set($jc->company_id);
        WebhookService::dispatch('job_card.status_changed', ['id' => $jc->id, 'number' => $jc->number, 'from' => $e->from, 'to' => $e->to]);

        $notifyStatuses = [JobCard::AWAITING_APPROVAL, JobCard::IN_PROGRESS, JobCard::READY, JobCard::DELIVERED];
        if (in_array($e->to, $notifyStatuses, true) && $jc->customer) {
            $link = $jc->checkIn ? route('public.track', $jc->checkIn->receipt_token) : '';
            $event = $e->to === JobCard::READY ? 'job_card.ready' : 'job_card.status_changed';
            $due = \App\Support\Money::sum($jc->invoices()->whereIn('status', ['posted', 'partially_paid'])->get()->map->balanceDue());
            app(NotificationService::class)->notifyCustomer($jc->customer, $event, [
                'number' => $jc->number, 'status' => status_label($e->to), 'plate' => $jc->vehicle?->plate(), 'link' => $link, 'total' => money($due),
            ], [$jc->customer->preferred_channel ?: 'sms'], $jc);
        }
        // staff: advisor gets an in-app notification
        if ($jc->advisor && $jc->advisor->id !== auth()->id()) {
            $jc->advisor->notify(new SystemMessage($jc->number.': '.status_label($e->to), $jc->vehicle?->label() ?? '', route('job-cards.show', $jc)));
        }
    }

    public function onInvoicePosted(InvoicePosted $e): void
    {
        $inv = $e->invoice;
        WebhookService::dispatch('invoice.posted', ['id' => $inv->id, 'number' => $inv->number, 'total' => (string) $inv->total, 'customer_id' => $inv->customer_id]);
        if ($inv->type !== 'pos' && $inv->customer && $inv->customer->code !== 'WALKIN') {
            app(NotificationService::class)->notifyCustomer($inv->customer, 'invoice.posted', [
                'number' => $inv->number, 'total' => money($inv->total), 'link' => route('public.invoice', $inv->uuid),
            ], ['mail'], $inv);
        }
    }

    public function onPaymentReceived(PaymentReceived $e): void
    {
        $p = $e->payment;
        WebhookService::dispatch('payment.received', ['id' => $p->id, 'number' => $p->number, 'amount' => (string) $p->amount, 'party' => $p->party_type.':'.$p->party_id]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            JobCardStatusChanged::class => 'onJobCardStatus',
            InvoicePosted::class => 'onInvoicePosted',
            PaymentReceived::class => 'onPaymentReceived',
        ];
    }
}
