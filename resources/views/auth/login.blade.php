@section('title', $data['title'] ?? 'Login' . ' | ' . config('app.name'))
@section('meta_description', '')

<x-guest-layout>
    <div class="flex h-screen w-full overflow-hidden bg-background">
        <!-- Left Side - Background Image -->
        <div class="relative hidden h-full md:block md:w-1/2 lg:w-2/3 xl:w-3/4">
            <img src="{{ asset('assets/img/auth-topography.webp') }}" alt="Topographic contour map" class="h-full w-full object-cover object-center" />
            <div class="absolute inset-0 bg-gradient-to-br from-[#003e64]/25 via-[#1a4fa0]/15 to-black/30 pointer-events-none">
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="flex h-full w-full flex-col items-center justify-center bg-background p-8  md:w-1/2 lg:w-1/3 xl:w-1/4 md:p-10 lg:p-12 overflow-y-auto overflow-x-hidden">
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

                <!-- Welcome Text -->
                <div class="mb-6 text-center">
                    <h2 class="mb-1 text-lg font-semibold text-accent ">
                        {{ __('Welcome Back') }}
                    </h2>
                    <p class="text-xs text-base-content-muted">
                        {{ __('Login to access your geospatial data') }}
                    </p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4 text-xs" :status="session('status')" />



                <!-- Login Form -->
                <form class="flex flex-col gap-4" method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Username or Email -->
                    <div class="flex flex-col">
                        <x-input-label for="id_user" :value="__('Username or Email')" />
                        <x-text-input icon="ri-user-3-line" id="id_user" name="id_user" type="text" :value="old('id_user')" required autofocus autocomplete="username" placeholder="username or email" />
                        <x-input-error :messages="$errors->get('id_user')" class="mt-1" />
                    </div>

                    <!-- Password -->
                    <div class="flex flex-col">
                        <x-input-label for="password" :value="__('Password')" />
                        <x-text-input icon="ri-lock-2-line" id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
                            <button class="absolute right-3 flex items-center text-[1.1rem] text-base-content-muted transition-colors duration-200 hover:text-primary" type="button"
                                onclick="togglePassword('password')">
                                <i class="ri-eye-off-line" id="icon-password"></i>
                            </button>
                        </x-text-input>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2 text-[0.75rem] text-foreground" for="remember_me">
                            <input class="h-3.5 w-3.5 cursor-pointer rounded-[3px] border-[1.5px] border-border accent-primary" id="remember_me" name="remember" type="checkbox" />
                            <span>{{ __('Remember Me') }}</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a class="text-[0.75rem] font-semibold text-primary transition-colors duration-200 hover:text-accent hover:underline" href="{{ route('password.request') }}">
                                {{ __('Forgot Password?') }}
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <x-button-primary class="group mt-3 w-full" type="submit">
                        {{ __('Log in') }}
                        <i class="ri-login-circle-line text-[1.05rem] transition-transform duration-200 group-hover:translate-x-[3px]"></i>
                    </x-button-primary>
                </form>

                <div class="mt-8 text-center">
                    <div class="mb-5 h-[1px] w-full bg-border"></div>
                    <p class="text-xs text-base-content-muted">
                        {{ __("Don't have an account?") }}
                        <span class="font-bold text-accent">{{ __('Contact Admin') }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
