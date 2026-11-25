<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
