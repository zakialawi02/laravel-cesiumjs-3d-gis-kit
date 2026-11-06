@props(['name', 'value', 'icon', 'title', 'description', 'checked' => false])

<label
    class="group relative flex cursor-pointer items-center gap-3 rounded-xl border-2 border-border p-3 transition-all hover:border-primary/30 hover:bg-primary/2 has-checked:border-primary has-checked:bg-primary/5">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" class="hidden" {{ $checked ? 'checked' : '' }} {{ $attributes }}>
    <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-has-checked:bg-primary group-has-checked:text-white">
        <i class="{{ $icon }} text-xl"></i>
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-bold text-foreground truncate">{{ $title }}</p>
        <p class="text-[10px] text-foreground/60 truncate">{{ $description }}</p>
    </div>
    <i class="ri-checkbox-circle-fill absolute right-2 top-2 scale-0 text-primary transition-transform group-has-checked:scale-100"></i>
</label>
