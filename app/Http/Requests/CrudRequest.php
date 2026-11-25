<?php

namespace App\Http\Requests;

use App\Support\Crud\CrudRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request whose rules come from the resource field definitions.
 */
class CrudRequest extends FormRequest
{
    public function authorize(): bool
    {
        $r = CrudRegistry::resolve($this->route('slug'));

        return $r->can($this->isMethod('post') ? 'create' : 'update');
    }

    public function rules(): array
    {
        $r = CrudRegistry::resolve($this->route('slug'));
        $model = $this->route('id') ? $r->scope($r->query())->find($this->route('id')) : null;
        $rules = $r->rules($model);
        if ($r::$hasAttachments) {
            $rules['attachments'] = ['nullable', 'array', 'max:10'];
            $rules['attachments.*'] = ['file', 'max:'.config('erp.uploads.max_kb'), 'mimes:'.config('erp.uploads.mimes')];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $r = CrudRegistry::resolve($this->route('slug'));
        $out = [];
        foreach ($r->fields() as $f) {
            $out[$f->name] = $f->getLabel();
        }

        return $out;
    }
}
