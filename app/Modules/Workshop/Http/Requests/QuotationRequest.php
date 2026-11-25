<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->isMethod('post') ? 'quotations.create' : 'quotations.update');
    }

    protected function prepareForValidation(): void
    {
        // drop empty template rows
        $items = array_values(array_filter((array) $this->input('items', []), fn ($i) => ! empty($i['description']) || ! empty($i['part_id']) || ! empty($i['service_id'])));
        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        $c = tenant()->id();

        return [
            'job_card_id' => ['nullable', 'integer', Rule::exists('job_cards', 'id')->where('company_id', $c)],
            'customer_id' => ['required_without:job_card_id', 'nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $c)],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type' => ['required', 'in:service,part,labor,other'],
            'items.*.part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('company_id', $c)],
            'items.*.service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('company_id', $c)],
            'items.*.description' => ['required', 'string', 'max:250'],
            'items.*.option_group' => ['nullable', 'string', 'max:10'],
            'items.*.part_condition' => ['nullable', 'in:new,used,refurbished'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
