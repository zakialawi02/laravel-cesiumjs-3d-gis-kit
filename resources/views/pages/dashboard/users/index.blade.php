@php
    use App\Enums\UserRole;

    $modalName = 'user-form';
    $storeUrl = route('dashboard.users.store');
    $reopen = old('_modal') === $modalName;
    $reopenEdit = $reopen && old('_action') !== $storeUrl;
    $reopenSelf = $reopenEdit && old('_action') === route('dashboard.users.update', auth()->user());
    $roleStyles = [
        UserRole::Admin->value => 'bg-primary/10 text-primary',
        UserRole::Engineering->value => 'bg-secondary/20 text-foreground/70',
    ];
    $hasFilters = $filters['search'] !== '' || $filters['role'];
@endphp

<x-app-layout title="User Management">
    <div class="space-y-5">

        <!-- Header -->
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="text-foreground text-xl font-extrabold">User Management</h2>
                <p class="text-foreground/60 text-sm">Create accounts, assign roles and reset passwords. Admins manage data and users; Engineering users view the map.</p>
            </div>
            <x-button-primary type="button" class="text-sm" data-user-create>
                <i class="ri-user-add-line"></i> New User
            </x-button-primary>
        </section>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <!-- Role chips (quick filters) -->
            <nav class="flex flex-wrap gap-2" aria-label="Filter by role">
                <a href="{{ route('dashboard.users.index', array_filter(array_merge($filters, ['role' => null]))) }}" @if (! $filters['role']) aria-current="page" @endif
                    @class([
                        'rounded-full border px-3 py-1 text-xs font-semibold transition',
                        'border-primary bg-primary text-primary-foreground' => !$filters['role'],
                        'border-border text-foreground/70 hover:bg-muted' => (bool) $filters['role'],
                    ])>
                    All <span class="opacity-70">{{ $roleCounts->sum() }}</span>
                </a>
                @foreach (UserRole::cases() as $role)
                    <a href="{{ route('dashboard.users.index', array_filter(array_merge($filters, ['role' => $role->value]))) }}" @if ($filters['role'] === $role->value) aria-current="page" @endif
                        @class([
                            'rounded-full border px-3 py-1 text-xs font-semibold transition',
                            'border-primary bg-primary text-primary-foreground' => $filters['role'] === $role->value,
                            'border-border text-foreground/70 hover:bg-muted' => $filters['role'] !== $role->value,
                        ])>
                        {{ $role->label() }} <span class="opacity-70">{{ $roleCounts[$role->value] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>

            <!-- Search -->
            <form method="GET" action="{{ route('dashboard.users.index') }}" class="flex w-full gap-2 md:max-w-sm">
                @if ($filters['role'])
                    <input type="hidden" name="role" value="{{ $filters['role'] }}">
                @endif
                <div class="flex-1">
                    <label for="search" class="sr-only">Search</label>
                    <x-text-input id="search" name="search" type="search" icon="ri-search-line" placeholder="Search name, username or email..." :value="$filters['search']" />
                </div>
                @if ($hasFilters)
                    <a href="{{ route('dashboard.users.index') }}" class="border-border hover:bg-muted inline-flex items-center justify-center rounded-[0.625rem] border px-3 text-sm" title="Reset filters">
                        <i class="ri-close-line"></i>
                    </a>
                @endif
            </form>
        </div>

        <!-- Table -->
        <section class="bg-neutral border-border overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-muted/50 text-foreground/60 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Layers</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        @forelse ($users as $user)
                            @php
                                $isSelf = $user->is(auth()->user());
                                $payload = [
                                    'action' => route('dashboard.users.update', $user),
                                    'name' => $user->name,
                                    'username' => $user->username,
                                    'email' => $user->email,
                                    'role' => $user->role->value,
                                    'is_self' => $isSelf,
                                ];
                            @endphp
                            <tr class="hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $user->profile_photo_url }}" alt="" class="border-border h-9 w-9 shrink-0 rounded-full border object-cover">
                                        <div class="min-w-0">
                                            <p class="text-foreground flex items-center gap-1.5 font-semibold">
                                                <span class="max-w-xs truncate">{{ $user->name }}</span>
                                                @if ($isSelf)
                                                    <span class="bg-muted text-foreground/60 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase">You</span>
                                                @endif
                                            </p>
                                            <p class="text-foreground/50 font-mono text-[11px]">{{ '@'.$user->username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-foreground/80 px-4 py-3 text-xs">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $roleStyles[$user->role->value] ?? 'bg-muted' }} rounded-full px-2 py-0.5 text-[10px] font-bold uppercase">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="text-foreground/70 px-4 py-3 text-xs">{{ $user->layers_count }}</td>
                                <td class="text-foreground/60 px-4 py-3 text-xs" title="{{ $user->created_at }}">{{ $user->created_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="text-foreground/60 hover:bg-primary/10 hover:text-primary rounded-md p-1.5" title="Edit" aria-label="Edit {{ $user->name }}"
                                            data-user-edit="{{ json_encode($payload) }}">
                                            <i class="ri-edit-line text-base"></i>
                                        </button>
                                        @if ($isSelf)
                                            <span class="text-foreground/25 cursor-not-allowed p-1.5" title="You cannot delete your own account"><i class="ri-lock-line text-base"></i></span>
                                        @else
                                            <form method="POST" action="{{ route('dashboard.users.destroy', $user) }}"
                                                data-confirm="Delete user &quot;{{ $user->name }}&quot;? They will no longer be able to sign in.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-foreground/60 hover:bg-error/10 hover:text-error rounded-md p-1.5" title="Delete" aria-label="Delete {{ $user->name }}">
                                                    <i class="ri-delete-bin-line text-base"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="ri-team-line" iconClass="text-3xl" :message="$hasFilters ? 'No users match these filters.' : 'No users yet.'" containerClass="py-12" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-border border-t px-4 py-3">{{ $users->links('pagination.app') }}</div>
            @endif
        </section>
    </div>

    <!-- Create / edit modal -->
    <x-modal :name="$modalName" maxWidth="lg" :show="$reopen">
        <form method="POST" id="userForm" action="{{ $reopen ? old('_action') : $storeUrl }}" class="space-y-4 p-6">
            @csrf
            <input type="hidden" name="_method" id="userFormMethod" value="{{ $reopenEdit ? 'PUT' : 'POST' }}">
            <input type="hidden" name="_modal" value="{{ $modalName }}">
            <input type="hidden" name="_action" value="{{ old('_action', $storeUrl) }}">

            <div class="flex items-center justify-between">
                <h3 class="text-foreground text-base font-bold" id="userFormTitle">{{ $reopenEdit ? 'Edit User' : 'New User' }}</h3>
                <button type="button" class="text-foreground/60 hover:text-error" data-modal-close aria-label="Close"><i class="ri-close-line text-xl"></i></button>
            </div>

            <div>
                <x-input-label for="user_name" value="Name" />
                <x-text-input id="user_name" name="name" type="text" icon="ri-user-line" :value="old('name')" required maxlength="255" autocomplete="off" :isError="$errors->has('name')" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" data-input-error />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="user_username" value="Username" />
                    <x-text-input id="user_username" name="username" type="text" icon="ri-at-line" :value="old('username')" required minlength="3" maxlength="50" autocomplete="off"
                        autocapitalize="none" spellcheck="false" :isError="$errors->has('username')" />
                    <x-input-error :messages="$errors->get('username')" class="mt-1" data-input-error />
                </div>
                <div>
                    <x-input-label for="user_role" value="Role" />
                    <select id="user_role" name="role" @disabled($reopenSelf)
                        class="border-border bg-input text-foreground w-full rounded-[0.625rem] border-[1.5px] px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60">
                        @foreach (UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role', UserRole::Engineering->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    {{-- A disabled select is not submitted, so the locked own-role is sent through this input instead. --}}
                    <input type="hidden" name="role" id="userRoleLocked" value="{{ UserRole::Admin->value }}" @disabled(! $reopenSelf)>
                    <p id="userRoleLockedHint" @class(['text-foreground/60 mt-1 text-xs', 'hidden' => ! $reopenSelf])>You cannot change your own role.</p>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" data-input-error />
                </div>
            </div>

            <div>
                <x-input-label for="user_email" value="Email" />
                <x-text-input id="user_email" name="email" type="email" icon="ri-mail-line" :value="old('email')" required maxlength="255" autocomplete="off" :isError="$errors->has('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" data-input-error />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="user_password" value="Password" />
                    <x-text-input id="user_password" name="password" type="password" icon="ri-lock-password-line" :required="! $reopenEdit" minlength="8" autocomplete="new-password"
                        :isError="$errors->has('password')" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" data-input-error />
                </div>
                <div>
                    <x-input-label for="user_password_confirmation" value="Confirm Password" />
                    <x-text-input id="user_password_confirmation" name="password_confirmation" type="password" icon="ri-lock-password-line" autocomplete="new-password" />
                </div>
            </div>
            <p id="userPasswordHint" class="text-foreground/60 -mt-2 text-xs">
                {{ $reopenEdit ? 'At least 8 characters. Leave blank to keep the current password.' : 'At least 8 characters.' }}
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <x-button-light type="button" data-modal-close>Cancel</x-button-light>
                <x-button-primary type="submit"><i class="ri-save-line"></i> Save</x-button-primary>
            </div>
        </form>
    </x-modal>

    @push('javascript')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('userForm');
                const storeUrl = @js($storeUrl);
                const defaultRole = @js(UserRole::Engineering->value);
                const roleSelect = document.getElementById('user_role');
                const roleLocked = document.getElementById('userRoleLocked');
                const roleLockedHint = document.getElementById('userRoleLockedHint');
                const passwordInput = document.getElementById('user_password');
                const passwordHint = document.getElementById('userPasswordHint');

                const lockRole = (locked) => {
                    roleSelect.disabled = locked;
                    roleLocked.disabled = !locked;
                    roleLockedHint.classList.toggle('hidden', !locked);
                };

                const fill = (data) => {
                    const isEdit = !!data;
                    form.reset();
                    form.action = isEdit ? data.action : storeUrl;
                    form.elements._action.value = form.action;
                    document.getElementById('userFormMethod').value = isEdit ? 'PUT' : 'POST';
                    document.getElementById('userFormTitle').textContent = isEdit ? 'Edit User' : 'New User';
                    form.elements.name.value = data?.name ?? '';
                    form.elements.username.value = data?.username ?? '';
                    form.elements.email.value = data?.email ?? '';
                    roleSelect.value = data?.role ?? defaultRole;
                    lockRole(!!data?.is_self);
                    passwordInput.toggleAttribute('required', !isEdit);
                    passwordHint.textContent = isEdit ? 'At least 8 characters. Leave blank to keep the current password.' : 'At least 8 characters.';
                    form.querySelectorAll('[data-input-error]').forEach(el => el.remove());
                    form.querySelectorAll('.border-red-500').forEach(el => el.classList.replace('border-red-500', 'border-border'));
                    window.openModal(@js($modalName));
                };

                document.querySelector('[data-user-create]')?.addEventListener('click', () => fill(null));
                document.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-user-edit]');
                    if (editBtn) fill(JSON.parse(editBtn.dataset.userEdit));
                });

                document.querySelectorAll('form[data-confirm]').forEach(f => {
                    f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
                });
            });
        </script>
    @endpush
</x-app-layout>
