<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    private const LAST_ADMIN_MESSAGE = 'At least one admin must remain. Make another user an admin first.';

    /**
     * Searchable, role-filterable list of user accounts.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'role' => in_array($request->input('role'), UserRole::values(), true) ? $request->input('role') : null,
        ];

        $users = User::query()
            ->withCount('layers')
            ->when($filters['search'] !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$filters['search']}%")
                ->orWhereLike('username', "%{$filters['search']}%")
                ->orWhereLike('email', "%{$filters['search']}%")))
            ->when($filters['role'], fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('pages.dashboard.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roleCounts' => User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
        ]);
    }

    /**
     * Create an account; admin-created accounts are trusted, so the email counts as verified.
     */
    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'username', 'email', 'role', 'password']) + ['email_verified_at' => now()]);

        return back()->with('success', "User \"{$user->name}\" created.");
    }

    /**
     * Update details and role; a blank password keeps the current one.
     */
    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'username', 'email', 'role']));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
            $user->setRememberToken(Str::random(60));
        }

        DB::transaction(function () use ($user): void {
            if ($user->isDirty('role') && $user->getOriginal('role') === UserRole::Admin && ! $this->anotherAdminRemains($user)) {
                throw ValidationException::withMessages(['role' => self::LAST_ADMIN_MESSAGE]);
            }

            $user->save();
        });

        return back()->with('success', "User \"{$user->name}\" updated.");
    }

    /**
     * Soft delete an account. Admins cannot delete themselves or the last admin.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $deleted = DB::transaction(function () use ($user): bool {
            if ($user->isAdmin() && ! $this->anotherAdminRemains($user)) {
                return false;
            }

            return (bool) $user->delete();
        });

        if (! $deleted) {
            return back()->with('error', self::LAST_ADMIN_MESSAGE);
        }

        return back()->with('success', "User \"{$user->name}\" deleted.");
    }

    /**
     * Lock every admin row (in a stable order) so two admins cannot demote or delete each other at the same time.
     */
    private function anotherAdminRemains(User $user): bool
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->contains(fn (string $id): bool => $id !== $user->id);
    }
}
