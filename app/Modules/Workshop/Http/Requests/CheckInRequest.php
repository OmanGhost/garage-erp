<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('reception.create');
    }

    /** Say exactly which permission is missing instead of a bare "403 Forbidden". */
    protected function failedAuthorization(): void
    {
        $user = $this->user();
        throw new \Illuminate\Auth\Access\AuthorizationException(__('Your account cannot receive vehicles (missing permission “:p”). Roles: :r. Ask the company administrator to grant it.', [
            'p' => 'reception.create',
            'r' => $user?->getRoleNames()->implode(', ') ?: '—',
        ]));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vin' => $this->vin ? strtoupper(trim($this->vin)) : null,
            'plate_code' => $this->plate_code ? strtoupper(trim($this->plate_code)) : null,
            // the fuel gauge slider moves in eighths of a tank (12.5 %); the column stores whole percent
            'fuel_level' => is_numeric($this->fuel_level) ? (int) round((float) $this->fuel_level) : $this->fuel_level,
            'customer_phone' => $this->customer_phone ? omani_phone($this->customer_phone) : null,
        ]);
    }

    public function messages(): array
    {
        $tooBig = __('A photo is too large or could not be uploaded. Attach smaller photos (up to :mb MB each).', ['mb' => 8]);

        return [
            'photos.*.uploaded' => $tooBig, 'photos.*.max' => $tooBig,
            'photos.*.image' => __('Only photos (JPG, PNG or WEBP) can be attached.'), 'photos.*.mimes' => __('Only photos (JPG, PNG or WEBP) can be attached.'),
            'customer_name.required_without' => __('Select an existing customer or enter the new customer name.'),
            'customer_phone.required_without' => __('Enter the new customer mobile number.'),
            'plate_number.required_without' => __('Select a registered vehicle or enter the plate number of the new vehicle.'),
            'expected_delivery_at.after' => __('The expected delivery must be a future date and time.'),
        ];
    }

    public function rules(): array
    {
        $company = tenant()->id();

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $company)],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:190'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email'],
            'customer_type' => ['nullable', 'in:individual,company,government'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $company)],
            'plate_number' => ['required_without:vehicle_id', 'nullable', 'string', 'max:20'],
            'plate_code' => ['nullable', 'string', 'max:10'],
            'plate_country' => ['nullable', 'string', 'size:2'],
            'vin' => ['nullable', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'vehicle_make_id' => ['nullable', 'integer', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:40'],
            'vehicle_type' => ['nullable', 'string', 'max:20'],
            'fuel_type' => ['nullable', 'string', 'max:20'],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'fuel_level' => ['required', 'integer', 'min:0', 'max:100'],
            'reported_issues' => ['required', 'string', 'max:5000'],
            'damages' => ['nullable', 'array', 'max:40'],
            'damages.*.area' => ['nullable', 'string', 'max:60'],
            'damages.*.type' => ['nullable', 'string', 'max:40'],
            'damages.*.description' => ['nullable', 'string', 'max:250'],
            'damages.*.x' => ['nullable', 'numeric', 'between:0,100'],
            'damages.*.y' => ['nullable', 'numeric', 'between:0,100'],
            'personal_items' => ['nullable', 'array', 'max:30'],
            'personal_items.*' => ['nullable', 'string', 'max:120'],
            'checklist' => ['nullable', 'array'],
            'expected_delivery_at' => ['nullable', 'date', 'after:now'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'job_type' => ['nullable', 'in:repair,maintenance,warranty,insurance,internal'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $company)],
            'service_advisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $company)],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')->where('company_id', $company)],
            'signature' => ['nullable', 'string', 'max:1400000'],
            'customer_signed_name' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'photo_captions' => ['nullable', 'array'],
            'photo_captions.*' => ['nullable', 'string', 'max:60'],
            'allow_duplicate' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
        ];
    }
}
