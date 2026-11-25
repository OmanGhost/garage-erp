<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perm = $this->routeIs('sales-orders.*') ? 'sales_orders.create' : ($this->isMethod('post') ? 'invoices.create' : 'invoices.update');

        return (bool) $this->user()?->can($perm);
    }

    protected function prepareForValidation(): void
    {
        $items = array_values(array_filter((array) $this->input('items', []), fn ($i) => ! empty($i['part_id']) || ! empty($i['service_id']) || ! empty($i['description'])));
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        $c = tenant()->id();

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $c)],
            'issue_date' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'payment_terms' => ['required', 'in:cash,credit'],
            'price_level' => ['required', 'in:retail,wholesale,workshop'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $c)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_type' => ['required', 'in:part,service,labor,other'],
            'items.*.part_id' => ['nullable', 'required_if:items.*.item_type,part', 'integer', Rule::exists('parts', 'id')->where('company_id', $c)],
            'items.*.service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('company_id', $c)],
            'items.*.description' => ['nullable', 'string', 'max:250'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
