<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveLayerCategoryRequest extends FormRequest
{
    /**
     * Route group already restricts this to admins; keep the check explicit.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageData();
    }

    /**
     * Icons are raster only (no SVG) so an uploaded icon can never carry script.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'icon' => ['nullable', 'file', 'mimes:png,webp', 'max:512', 'dimensions:max_width=512,max_height=512'],
            'remove_icon' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'The color must be a hex value such as #1a4fa0.',
        ];
    }
}
