<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    /**
     * Route group already restricts this to admins; keep the check explicit.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageUsers();
    }

    /**
     * Normalise identifiers before validation so uniqueness checks match login lookups.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * The password is required when creating an account and optional (keep current) when editing one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->editedUser();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class)->ignore($user)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Admins may edit their own account here but never drop their own admin role.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('role') || ! $this->editedUser()?->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', __('You cannot remove your own admin role.'));
                }
            },
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

    /**
     * The account being edited, or null when creating one.
     */
    private function editedUser(): ?User
    {
        return $this->route('user');
    }
}
