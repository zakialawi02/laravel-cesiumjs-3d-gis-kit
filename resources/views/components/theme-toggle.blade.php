{{-- Light/dark switch handled by ui-interactions.js ([data-theme-toggle]). --}}
<button type="button" data-theme-toggle {{ $attributes->merge(['class' => 'inline-flex size-9 items-center justify-center rounded-full text-foreground/80 transition hover:bg-muted hover:text-primary']) }}
    aria-label="{{ __('Toggle dark mode') }}" @unless ($attributes->has('data-tooltip')) title="{{ __('Toggle dark mode') }}" @endunless>
    <i class="ri-moon-line text-lg dark:hidden"></i>
    <i class="ri-sun-line hidden text-lg dark:inline"></i>
</button>
