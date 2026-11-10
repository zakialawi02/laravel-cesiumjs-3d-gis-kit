@section('title', $data['title'] ?? 'Reset Password' . ' | ' . config('app.name'))
@section('meta_description', '')

<x-guest-layout>
    <div class="flex h-screen w-full overflow-hidden bg-background">
        <!-- Left Side - Background Image -->
        <div class="relative hidden h-full md:block md:w-1/2 lg:w-2/3 xl:w-3/4">
            <img src="{{ asset('assets/img/auth-topography.webp') }}" alt="Topographic contour map" class="h-full w-full object-cover object-center" />
            <div class="absolute inset-0 bg-gradient-to-br from-[#064e3b]/25 via-[#047857]/15 to-black/30 pointer-events-none">
            </div>
        </div>

        <!-- Right Side - Reset Password Form -->
        <div class="flex h-full w-full flex-col items-center justify-center bg-background p-8 md:w-1/2 lg:w-1/3 xl:w-1/4 md:p-8 lg:p-10 overflow-y-auto overflow-x-hidden">
            <div class="w-full max-w-[420px] md:max-w-full lg:max-w-[400px]">
                <!-- Logo & Branding -->
                <div class="mb-6 flex flex-col items-center">
                    <a href="/"
                        class="mb-3 flex h-14 w-14 items-center justify-center rounded-[14px] bg-primary shadow-[0_4px_14px_rgba(4,120,87,0.3)] transition-transform hover:scale-105 active:scale-95">
                        <x-application-logo class="max-h-10 max-w-10 w-9 brightness-0 invert" />
                    </a>
                    <h1 class="mb-2 text-center text-sm font-extrabold uppercase tracking-[0.08em] text-accent ">
                        {{ config('app.name') }}
                    </h1>
                    <div class="h-[3px] w-10 rounded-sm bg-secondary"></div>
                </div>

                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-foreground">{{ __('Reset Password') }}</h2>
                    <p class="mt-2 text-sm text-base-content-muted">
                        {{ __('Create a new strong password to secure your account.') }}
                    </p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-6" :status="session('status')" />

                <form class="space-y-4" method="POST" action="{{ route('password.store') }}">
                    @csrf

                    <!-- Password Reset Token -->
                    <input name="token" type="hidden" value="{{ $request->route('token') ?? '' }}">

                    <!-- Email Address -->
                    <div class="flex flex-col">
                        <x-input-label for="email" :value="__('Email Address')" />
                        <x-text-input icon="ri-mail-line" id="email" name="email" type="email" :value="old('email', $request->email ?? '')" required autofocus autocomplete="username" placeholder="name@mail.com"
                            :is-error="$errors->has('email')" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <!-- Password -->
                    <div class="flex flex-col">
                        <x-input-label for="password" :value="__('New Password')" />
                        <x-text-input icon="ri-lock-2-line" id="password" name="password" type="password" required autocomplete="new-password" placeholder="••••••••" :is-error="$errors->has('password')">
                            <button class="absolute right-3 flex items-center text-[1.1rem] text-base-content-muted transition-colors duration-200 hover:text-primary" type="button"
                                onclick="togglePassword('password')">
                                <i class="ri-eye-off-line" id="icon-password"></i>
                            </button>
                        </x-text-input>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="flex flex-col">
                        <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                        <x-text-input icon="ri-lock-password-line" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="••••••••"
                            :is-error="$errors->has('password_confirmation')">
                            <button class="absolute right-3 flex items-center text-[1.1rem] text-base-content-muted transition-colors duration-200 hover:text-primary" type="button"
                                onclick="togglePassword('password_confirmation')">
                                <i class="ri-eye-off-line" id="icon-password_confirmation"></i>
                            </button>
                        </x-text-input>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <!-- Submit Button -->
                    <x-button-primary class="group w-full" type="submit">
                        {{ __('Reset Password') }}
                        <i class="ri-restart-line text-[1.05rem] transition-transform duration-250 group-hover:translate-x-[3px]"></i>
                    </x-button-primary>
                </form>

                <div class="mt-8 text-center">
                    <div class="mb-5 h-[1px] w-full bg-border"></div>
                    <p class="text-xs text-base-content-muted">
                        {{ __('Remember your password?') }}
                        <a class="font-bold text-accent transition-colors duration-200 hover:text-primary hover:underline" href="{{ route('login') }}">
                            {{ __('Back to login') }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
