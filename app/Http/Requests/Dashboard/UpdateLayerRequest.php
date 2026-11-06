<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLayerRequest extends FormRequest
{
    /**
     * Route group already restricts this to admins; keep the check explicit.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageData();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'layer_category_id' => ['nullable', 'string', 'exists:layer_categories,id'],
            'acquisition_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
