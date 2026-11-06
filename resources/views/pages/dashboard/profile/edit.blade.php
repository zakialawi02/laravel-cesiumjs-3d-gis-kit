<x-app-layout title="Edit Profile">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Account summary -->
        <section class="bg-neutral border-border h-fit rounded-xl border p-6 text-center shadow-sm">
            <img src="{{ $user->profile_photo_url }}" alt="" class="border-border mx-auto h-20 w-20 rounded-full border-2 object-cover">
            <h2 class="text-foreground mt-3 text-lg font-bold">{{ $user->name }}</h2>
            <p class="text-foreground/60 text-sm">{{ '@'.$user->username }}</p>
            <span @class([
                'mt-3 inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider',
                'bg-primary/10 text-primary' => $user->isAdmin(),
                'bg-success/10 text-success' => !$user->isAdmin(),
            ])>{{ $user->role->label() }}</span>
            <p class="text-foreground/50 mt-4 text-xs">Member since {{ $user->created_at?->translatedFormat('d M Y') }}</p>
        </section>

        <div class="space-y-6 lg:col-span-2">
            <!-- Profile information -->
            <section class="bg-neutral border-border rounded-xl border shadow-sm">
                <header class="border-border border-b px-6 py-4">
                    <h3 class="text-foreground text-base font-bold">Profile Information</h3>
                    <p class="text-foreground/60 text-xs">Update your account's name, username and email.</p>
                </header>

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-5 p-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" icon="ri-user-3-line" :value="old('name', $user->name)" required autocomplete="name" :isError="$errors->has('name')" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="username" value="Username" />
                            <x-text-input id="username" name="username" type="text" icon="ri-at-line" :value="old('username', $user->username)" required autocomplete="username" :isError="$errors->has('username')" />
                            <x-input-error :messages="$errors->get('username')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" icon="ri-mail-line" :value="old('email', $user->email)" required autocomplete="email" :isError="$errors->has('email')" />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-button-primary type="submit" class="text-sm">
                            <i class="ri-save-line"></i> Save Profile
                        </x-button-primary>
                    </div>
                </form>
            </section>

            <!-- Password -->
            <section class="bg-neutral border-border rounded-xl border shadow-sm">
                <header class="border-border border-b px-6 py-4">
                    <h3 class="text-foreground text-base font-bold">Change Password</h3>
                    <p class="text-foreground/60 text-xs">At least 8 characters. Sessions on other devices will be signed out.</p>
                </header>

                <form method="POST" action="{{ route('profile.password') }}" class="space-y-5 p-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="current_password" value="Current Password" />
                        <x-text-input id="current_password" name="current_password" type="password" icon="ri-lock-line" required autocomplete="current-password"
                            :isError="$errors->updatePassword->has('current_password')" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="password" value="New Password" />
                            <x-text-input id="password" name="password" type="password" icon="ri-lock-password-line" required autocomplete="new-password"
                                :isError="$errors->updatePassword->has('password')" />
                            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" value="Confirm Password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" icon="ri-lock-password-line" required autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-button-primary type="submit" class="text-sm">
                            <i class="ri-key-2-line"></i> Change Password
                        </x-button-primary>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
