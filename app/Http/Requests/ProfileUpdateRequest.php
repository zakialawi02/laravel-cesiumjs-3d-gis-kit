<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Any authenticated user may update their own profile; the route is behind `auth`.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalise identifiers before validation so uniqueness checks match login lookups.
     * Non-string input is left untouched so the `string` rule rejects it instead of a cast failing.
     */
    protected function prepareForValidation(): void
    {
        foreach (['username', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtolower(trim($this->input($field)))]);
            }
        }
    }

    /**
     * Only name, username and email are editable here; role and photo are not.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class)->ignore($this->user()->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('Username may only contain lowercase letters, numbers, dots, dashes and underscores.'),
        ];
    }
}
