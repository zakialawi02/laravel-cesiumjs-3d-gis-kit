@section('title', $data['title'] ?? 'Forgot Password' . ' | ' . config('app.name'))
@section('meta_description', '')

<x-guest-layout>
    <div class="flex h-screen w-full overflow-hidden bg-background">
        <!-- Left Side - Background Image -->
        <div class="relative hidden h-full md:block md:w-1/2 lg:w-2/3 xl:w-3/4">
            <img src="{{ asset('assets/img/auth-topography.webp') }}" alt="Topographic contour map" class="h-full w-full object-cover object-center" />
            <div class="absolute inset-0 bg-gradient-to-br from-[#003e64]/25 via-[#1a4fa0]/15 to-black/30 pointer-events-none">
            </div>
        </div>

        <!-- Right Side - Forgot Password Form -->
        <div class="flex h-full w-full flex-col items-center justify-center bg-background p-8 md:w-1/2 lg:w-1/3 xl:w-1/4 md:p-8 lg:p-10 overflow-y-auto overflow-x-hidden">
            <div class="w-full max-w-[420px] md:max-w-full lg:max-w-[400px]">
                <!-- Logo & Branding -->
                <div class="mb-6 flex flex-col items-center">
                    <a href="/"
                        class="mb-3 flex h-14 w-14 items-center justify-center rounded-[14px] bg-primary shadow-[0_4px_14px_rgba(26,79,160,0.3)] transition-transform hover:scale-105 active:scale-95">
                        <x-application-logo class="max-h-10 max-w-10 w-9 brightness-0 invert" />
                    </a>
                    <h1 class="mb-2 text-center text-sm font-extrabold uppercase tracking-[0.08em] text-accent ">
                        {{ config('app.name') }}
                    </h1>
                    <div class="h-[3px] w-10 rounded-sm bg-secondary"></div>
                </div>

                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-foreground">{{ __('Forgot Password?') }}</h2>
                    <p class="mt-2 text-sm text-base-content-muted">
                        {{ __('No problem. Enter your email and we\'ll send you a password reset link.') }}
                    </p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-6" :status="session('status')" />

                <!-- Email Status Warning if SMTP is off -->
                @if (!config('mail.smtp_on'))
                    <div class="mb-6 rounded-lg bg-warning/10 border border-warning/20 p-4 text-center text-xs font-medium text-warning flex items-center justify-center gap-2">
                        <i class="ri-error-warning-line text-sm"></i>
                        <span>{{ __('Email system is unavailable. Please contact admin manually.') }}</span>
                    </div>
                @endif

                <form class="space-y-5" method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="flex flex-col">
                        <x-input-label for="email" :value="__('Email Address')" />
                        <x-text-input icon="ri-mail-line" id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" placeholder="example@mail.com"
                            :is-error="$errors->has('email')" :disabled="!config('mail.smtp_on')" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <!-- Submit Button -->
                    <x-button-primary class="group w-full" type="submit" :disabled="!config('mail.smtp_on')">
                        {{ __('Send Reset Link') }}
                        <i class="ri-mail-send-line text-[1.05rem] transition-transform duration-250 group-hover:translate-x-[3px]"></i>
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
