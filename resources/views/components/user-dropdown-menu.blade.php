@props([
    'buttonId' => 'userMenuBtn',
    'dropdownId' => 'userDropdown',
    'placement' => 'left',
    'avatarSize' => '10',
])

<div class="relative {{ $placement === 'left' ? 'mt-2' : '' }}">
    <button id="{{ $buttonId }}"
        class="group relative flex h-{{ $avatarSize }} w-{{ $avatarSize }} items-center justify-center overflow-hidden rounded-full border-2 border-neutral bg-primary text-neutral shadow-sm transition-all hover:border-primary hover:shadow-md">
        @if (auth()->user()->profile_photo_path)
            <img src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
        @else
            <span class="{{ $avatarSize == '8' ? 'text-[10px]' : 'text-xs' }} font-bold">
                {{ collect(explode(' ', auth()->user()->name))->map(fn($n) => $n[0])->take(2)->implode('') }}
            </span>
        @endif
    </button>

    <!-- User Dropdown Menu -->
    <div id="{{ $dropdownId }}"
        class="absolute {{ $placement === 'left' ? 'bottom-0 left-full ml-4' : 'top-full right-0 mt-2' }} w-56 transform scale-95 opacity-0 pointer-events-none transition-all duration-200 z-50">
        <div class="bg-neutral rounded-xl shadow-2xl border border-border overflow-hidden">
            <!-- User Info Header -->
            <div class="p-4 bg-neutral border-b border-border">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-bold text-foreground uppercase tracking-wider text-[10px]">
                        signed in as</p>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider {{ auth()->user()->role === \App\Enums\UserRole::Admin ? 'bg-primary/10 text-primary' : 'bg-success/10 text-success' }}">
                        {{ auth()->user()->role->label() }}
                    </span>
                </div>
                <p class="text-sm font-bold text-primary truncate">{{ auth()->user()->name }}</p>
                <p class="text-[10px] text-foreground/80 truncate">{{ auth()->user()->email }}</p>
            </div>

            <!-- Menu -->
            <nav class="py-2" aria-label="{{ __('Account') }}">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-foreground hover:bg-muted hover:text-primary transition-colors">
                    <i class="ri-dashboard-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-foreground hover:bg-muted hover:text-primary transition-colors">
                    <i class="ri-user-settings-line"></i>
                    <span>Edit Profile</span>
                </a>
            </nav>

            <!-- Logout -->
            <div class="border-t border-border py-2">
                <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-error hover:bg-error/10 transition-colors text-left">
                    <i class="ri-logout-box-line"></i>
                    <span>Sign Out</span>
                </button>
            </div>
        </div>

        <!-- Invisible pointer area to prevent accidental closing -->
        @if ($placement === 'left')
            <div class="absolute top-0 -left-4 w-4 h-full"></div>
        @endif
    </div>
</div>
