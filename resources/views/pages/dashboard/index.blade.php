@php
    $hour = (int) now()->format('H');
    $greeting = match (true) {
        $hour < 11 => ['Good morning', 'ri-sun-line'],
        $hour < 15 => ['Good afternoon', 'ri-sun-fill'],
        $hour < 19 => ['Good evening', 'ri-sun-cloud-line'],
        default => ['Good night', 'ri-moon-clear-line'],
    };

    $jobTone = fn (\App\Enums\JobStatus $status): string => match ($status) {
        \App\Enums\JobStatus::DONE => 'bg-success/10 text-success',
        \App\Enums\JobStatus::FAILED => 'bg-error/10 text-error',
        default => 'bg-primary/10 text-primary',
    };

    $layerTone = fn (\App\Enums\LayerStatus $status): string => match ($status) {
        \App\Enums\LayerStatus::PUBLISHED => 'bg-success/10 text-success',
        \App\Enums\LayerStatus::PROCESS_FAILED => 'bg-error/10 text-error',
        default => 'bg-primary/10 text-primary',
    };
@endphp

<x-app-layout title="Dashboard">
    <div class="space-y-6">

        <!-- Welcome -->
        <section class="bg-neutral border-border rounded-xl border p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="bg-secondary/15 text-secondary flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl">
                        <i class="ri-global-line text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-foreground flex items-center gap-2 text-xl font-extrabold md:text-2xl">
                            <i class="{{ $greeting[1] }} text-secondary text-lg"></i>
                            <span>{{ $greeting[0] }}, <span class="text-primary">{{ auth()->user()->name }}</span></span>
                        </h2>
                        <p class="text-foreground/55 mt-1 flex flex-wrap items-center gap-1.5 font-mono text-[11px] font-semibold uppercase tracking-wider">
                            <span>{{ auth()->user()->role->label() }}</span>
                            <span class="text-foreground/30">•</span>
                            <span>{{ now()->translatedFormat('D, d M Y') }}</span>
                        </p>
                    </div>
                </div>
                <x-button-primary href="{{ route('home') }}" class="shrink-0 text-sm">
                    <i class="ri-compass-3-line"></i>
                    <span>Open 3D Map</span>
                </x-button-primary>
            </div>
        </section>

        <!-- Metrics -->
        <section @class(['grid grid-cols-1 gap-4 sm:grid-cols-2', 'xl:grid-cols-4' => $isAdmin]) aria-label="Summary">
            <div class="bg-neutral border-border rounded-xl border p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-foreground/60 text-xs font-bold uppercase tracking-wider">{{ $isAdmin ? 'Total Layers' : 'Available Layers' }}</p>
                        <p class="text-foreground mt-2 text-3xl font-extrabold">{{ number_format($stats['layers']) }}</p>
                    </div>
                    <span class="bg-primary/10 text-primary flex h-11 w-11 items-center justify-center rounded-xl"><i class="ri-stack-line text-xl"></i></span>
                </div>
                <div class="border-border/60 text-foreground/60 mt-4 flex items-center gap-4 border-t pt-3 text-xs">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span>{{ $stats['terrain_layers'] }} Terrain</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-sky-500"></span>{{ $stats['vector_layers'] }} Vector</span>
                </div>
            </div>

            <div class="bg-neutral border-border rounded-xl border p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-foreground/60 text-xs font-bold uppercase tracking-wider">Layer Categories</p>
                        <p class="text-foreground mt-2 text-3xl font-extrabold">{{ number_format($stats['categories']) }}</p>
                    </div>
                    <span class="bg-rose-500/10 text-rose-500 flex h-11 w-11 items-center justify-center rounded-xl"><i class="ri-folders-line text-xl"></i></span>
                </div>
                <div class="border-border/60 text-foreground/60 mt-4 border-t pt-3 text-xs">Groups shown in the map's Layer panel</div>
            </div>

            @if ($isAdmin)
                <div class="bg-neutral border-border rounded-xl border p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-foreground/60 text-xs font-bold uppercase tracking-wider">Conversions</p>
                            <p class="text-foreground mt-2 text-3xl font-extrabold">{{ number_format($stats['active_jobs']) }}</p>
                        </div>
                        <span class="bg-secondary/15 text-secondary flex h-11 w-11 items-center justify-center rounded-xl"><i class="ri-loader-4-line text-xl"></i></span>
                    </div>
                    <div class="border-border/60 text-foreground/60 mt-4 flex items-center justify-between border-t pt-3 text-xs">
                        <span>Running / queued</span>
                        <span @class(['font-bold', 'text-error' => $stats['failed_jobs'] > 0])>{{ $stats['failed_jobs'] }} failed</span>
                    </div>
                </div>

                <div class="bg-neutral border-border rounded-xl border p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-foreground/60 text-xs font-bold uppercase tracking-wider">Users</p>
                            <p class="text-foreground mt-2 text-3xl font-extrabold">{{ number_format($stats['users']) }}</p>
                        </div>
                        <span class="bg-accent/10 text-accent flex h-11 w-11 items-center justify-center rounded-xl"><i class="ri-team-line text-xl"></i></span>
                    </div>
                    <div class="border-border/60 text-foreground/60 mt-4 border-t pt-3 text-xs">Admin &amp; Engineering</div>
                </div>
            @endif
        </section>

        <!-- Recent activity -->
        <section @class(['grid grid-cols-1 gap-4', 'lg:grid-cols-2' => $isAdmin])>

            <div class="bg-neutral border-border rounded-xl border shadow-sm">
                <div class="border-border flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-foreground text-sm font-bold">Latest Layers</h3>
                    <i class="ri-stack-line text-foreground/40"></i>
                </div>
                <ul class="divide-border divide-y">
                    @forelse ($recentLayers as $layer)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="bg-muted text-foreground/70 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                <i class="{{ $layer->layer_type === \App\Enums\LayerType::TERRAIN ? 'ri-landscape-line' : 'ri-shape-line' }}"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-foreground truncate text-sm font-semibold">{{ $layer->name }}</p>
                                <p class="text-foreground/50 truncate text-[11px]">{{ $layer->category?->name ?? 'Other' }} • {{ $layer->created_at->diffForHumans() }}</p>
                            </div>
                            @if ($isAdmin)
                                <span class="{{ $layerTone($layer->status) }} shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase">{{ str_replace('_', ' ', $layer->status->value) }}</span>
                            @endif
                        </li>
                    @empty
                        <li><x-empty-state icon="ri-stack-line" iconClass="text-2xl" message="No layers yet" containerClass="py-8" /></li>
                    @endforelse
                </ul>
            </div>

            @if ($isAdmin)
                <div class="bg-neutral border-border rounded-xl border shadow-sm">
                    <div class="border-border flex items-center justify-between border-b px-5 py-3">
                        <h3 class="text-foreground text-sm font-bold">Latest Conversions</h3>
                        <i class="ri-loader-4-line text-foreground/40"></i>
                    </div>
                    <ul class="divide-border divide-y">
                        @forelse ($recentJobs as $job)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-foreground truncate text-sm font-semibold">{{ $job->layer?->name ?? $job->sourceFile?->original_name ?? '—' }}</p>
                                    <p class="text-foreground/50 text-[11px]">{{ ucfirst($job->layer_type->value) }} • {{ ($job->finished_at ?? $job->created_at)->diffForHumans() }}</p>
                                </div>
                                <span class="{{ $jobTone($job->status) }} shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase">{{ $job->status->label() }}</span>
                            </li>
                        @empty
                            <li><x-empty-state icon="ri-history-line" iconClass="text-2xl" message="No conversions yet" containerClass="py-8" /></li>
                        @endforelse
                    </ul>
                </div>
            @endif

        </section>
    </div>
</x-app-layout>
