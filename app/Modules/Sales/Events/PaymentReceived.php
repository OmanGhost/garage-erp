<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
