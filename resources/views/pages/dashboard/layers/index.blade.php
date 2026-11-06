@php
    use App\Enums\LayerStatus;
    use App\Enums\LayerType;

    $statusStyles = [
        'published' => 'bg-success/10 text-success',
        'processing' => 'bg-primary/10 text-primary',
        'queued' => 'bg-primary/10 text-primary',
        'pending' => 'bg-muted text-foreground/60',
        'process_failed' => 'bg-error/10 text-error',
        'archived' => 'bg-secondary/20 text-foreground/70',
    ];
    $hasFilters = $filters['search'] !== '' || $filters['type'] || $filters['status'] || $filters['category'] !== '';
    $reopenEdit = old('_modal') === 'layer-edit';
@endphp

<x-app-layout title="Layer Management">
    <div class="space-y-5">

        <!-- Header -->
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="text-foreground text-xl font-extrabold">Layer Management</h2>
                <p class="text-foreground/60 text-sm">Rename, recategorize, archive or delete uploaded terrain and vector layers.</p>
            </div>
            <x-button-primary href="{{ route('home') }}" class="text-sm">
                <i class="ri-upload-cloud-2-line"></i> Upload on Map
            </x-button-primary>
        </section>

        <!-- Status chips (quick filters) -->
        <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
            <a href="{{ route('dashboard.layers.index', array_filter(array_merge($filters, ['status' => null]))) }}"
                @class([
                    'rounded-full border px-3 py-1 text-xs font-semibold transition',
                    'border-primary bg-primary text-primary-foreground' => !$filters['status'],
                    'border-border text-foreground/70 hover:bg-muted' => (bool) $filters['status'],
                ])>
                All <span class="opacity-70">{{ $statusCounts->sum() }}</span>
            </a>
            @foreach (LayerStatus::cases() as $status)
                @continue(! $statusCounts->has($status->value))
                <a href="{{ route('dashboard.layers.index', array_filter(array_merge($filters, ['status' => $status->value]))) }}"
                    @class([
                        'rounded-full border px-3 py-1 text-xs font-semibold capitalize transition',
                        'border-primary bg-primary text-primary-foreground' => $filters['status'] === $status->value,
                        'border-border text-foreground/70 hover:bg-muted' => $filters['status'] !== $status->value,
                    ])>
                    {{ str_replace('_', ' ', $status->value) }} <span class="opacity-70">{{ $statusCounts[$status->value] }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Filters -->
        <form method="GET" action="{{ route('dashboard.layers.index') }}" class="bg-neutral border-border grid grid-cols-1 gap-3 rounded-xl border p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="search" class="sr-only">Search</label>
                <x-text-input id="search" name="search" type="search" icon="ri-search-line" placeholder="Search layer name..." :value="$filters['search']" />
            </div>
            <select name="type" aria-label="Layer type" class="border-border bg-input text-foreground rounded-[0.625rem] border-[1.5px] px-3 py-2 text-sm">
                <option value="">All types</option>
                @foreach (LayerType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ ucfirst($type->value) }}</option>
                @endforeach
            </select>
            <select name="category" aria-label="Category" class="border-border bg-input text-foreground rounded-[0.625rem] border-[1.5px] px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                @if ($filters['status'])
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
                <x-button-primary type="submit" class="flex-1 text-sm"><i class="ri-filter-3-line"></i> Filter</x-button-primary>
                @if ($hasFilters)
                    <a href="{{ route('dashboard.layers.index') }}" class="border-border hover:bg-muted inline-flex items-center justify-center rounded-[0.625rem] border px-3 text-sm" title="Reset filters">
                        <i class="ri-close-line"></i>
                    </a>
                @endif
            </div>
        </form>

        <!-- Table -->
        <section class="bg-neutral border-border overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="bg-muted/50 text-foreground/60 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Layer</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Uploaded</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        @forelse ($layers as $layer)
                            @php
                                $editPayload = [
                                    'action' => route('dashboard.layers.update', $layer),
                                    'name' => $layer->name,
                                    'layer_category_id' => $layer->layer_category_id,
                                    'acquisition_date' => optional(($layer->layer_type === LayerType::TERRAIN ? $layer->terrainDetail : $layer->vectorDetail)?->acquisition_date)->format('Y-m-d'),
                                ];
                            @endphp
                            <tr class="hover:bg-muted/30" data-layer-row="{{ $layer->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="bg-muted text-foreground/70 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" title="{{ ucfirst($layer->layer_type->value) }}">
                                            <i class="{{ $layer->layer_type === LayerType::TERRAIN ? 'ri-landscape-line' : 'ri-shape-line' }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-foreground max-w-xs truncate font-semibold">{{ $layer->name }}</p>
                                            <p class="text-foreground/50 font-mono text-[10px]">{{ $layer->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $layer->category?->color ?? '#6b7280' }}"></span>
                                        {{ $layer->category?->name ?? 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $statusStyles[$layer->status->value] ?? 'bg-muted' }} rounded-full px-2 py-0.5 text-[10px] font-bold uppercase">
                                        {{ str_replace('_', ' ', $layer->status->value) }}
                                    </span>
                                </td>
                                <td class="text-foreground/70 px-4 py-3 text-xs">{{ $layer->formatted_size }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <p class="text-foreground/80">{{ $layer->user?->name ?? 'System' }}</p>
                                    <p class="text-foreground/50" title="{{ $layer->created_at }}">{{ $layer->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="text-foreground/60 hover:bg-primary/10 hover:text-primary rounded-md p-1.5" title="View details"
                                            data-layer-detail="{{ route('dashboard.layers.show', $layer) }}">
                                            <i class="ri-eye-line text-base"></i>
                                        </button>
                                        <button type="button" class="text-foreground/60 hover:bg-primary/10 hover:text-primary rounded-md p-1.5" title="Edit"
                                            data-layer-edit="{{ json_encode($editPayload) }}">
                                            <i class="ri-edit-line text-base"></i>
                                        </button>
                                        @if ($layer->status === LayerStatus::PUBLISHED)
                                            <form method="POST" action="{{ route('dashboard.layers.archive', $layer) }}" data-confirm="Archive &quot;{{ $layer->name }}&quot;? It will be hidden from the map.">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-foreground/60 hover:bg-warning/15 hover:text-warning rounded-md p-1.5" title="Archive (hide from map)">
                                                    <i class="ri-archive-line text-base"></i>
                                                </button>
                                            </form>
                                        @elseif ($layer->status === LayerStatus::ARCHIVED)
                                            <form method="POST" action="{{ route('dashboard.layers.restore', $layer) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-foreground/60 hover:bg-success/15 hover:text-success rounded-md p-1.5" title="Restore to map">
                                                    <i class="ri-inbox-unarchive-line text-base"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="text-foreground/60 hover:bg-error/10 hover:text-error rounded-md p-1.5" title="Delete"
                                            data-layer-delete="{{ route('layers.destroy', $layer) }}" data-layer-name="{{ $layer->name }}">
                                            <i class="ri-delete-bin-line text-base"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="ri-database-2-line" iconClass="text-3xl" :message="$hasFilters ? 'No layers match these filters.' : 'No layers yet. Upload data from the Data panel on the map.'" containerClass="py-12" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($layers->hasPages())
                <div class="border-border border-t px-4 py-3">{{ $layers->links('pagination.app') }}</div>
            @endif
        </section>
    </div>

    <!-- Detail modal -->
    <x-modal name="layer-detail" maxWidth="2xl">
        <div class="border-border flex items-center justify-between border-b px-6 py-4">
            <h3 class="text-foreground text-base font-bold">Layer Details</h3>
            <button type="button" class="text-foreground/60 hover:text-error" data-modal-close aria-label="Close"><i class="ri-close-line text-xl"></i></button>
        </div>
        <div id="layerDetailBody" class="p-6"></div>
    </x-modal>

    <!-- Edit modal -->
    <x-modal name="layer-edit" maxWidth="lg" :show="$reopenEdit">
        <form method="POST" id="layerEditForm" action="{{ $reopenEdit ? old('_action') : '#' }}" class="space-y-4 p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="layer-edit">
            <input type="hidden" name="_action" id="layerEditAction" value="{{ old('_action') }}">

            <div class="flex items-center justify-between">
                <h3 class="text-foreground text-base font-bold">Edit Layer</h3>
                <button type="button" class="text-foreground/60 hover:text-error" data-modal-close aria-label="Close"><i class="ri-close-line text-xl"></i></button>
            </div>

            <div>
                <x-input-label for="layer_name" value="Name" />
                <x-text-input id="layer_name" name="name" type="text" :value="old('name')" required maxlength="255" :isError="$errors->has('name')" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="layer_category_id" value="Category" />
                <select id="layer_category_id" name="layer_category_id" class="border-border bg-input text-foreground w-full rounded-[0.625rem] border-[1.5px] px-3 py-2 text-sm">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('layer_category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('layer_category_id')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="acquisition_date" value="Acquisition Date" />
                <x-text-input id="acquisition_date" name="acquisition_date" type="date" :value="old('acquisition_date')" :max="now()->format('Y-m-d')" :isError="$errors->has('acquisition_date')" />
                <x-input-error :messages="$errors->get('acquisition_date')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-button-light type="button" data-modal-close>Cancel</x-button-light>
                <x-button-primary type="submit"><i class="ri-save-line"></i> Save</x-button-primary>
            </div>
        </form>
    </x-modal>

    @push('javascript')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const detailBody = document.getElementById('layerDetailBody');
                const editForm = document.getElementById('layerEditForm');

                // Confirm before archive (and any form tagged with data-confirm)
                document.querySelectorAll('form[data-confirm]').forEach(form => {
                    form.addEventListener('submit', (e) => {
                        if (!confirm(form.dataset.confirm)) e.preventDefault();
                    });
                });

                document.addEventListener('click', async (e) => {
                    const detailBtn = e.target.closest('[data-layer-detail]');
                    if (detailBtn) {
                        detailBody.innerHTML = '<div class="py-10 text-center text-foreground/50"><i class="ri-loader-4-line animate-spin text-2xl"></i></div>';
                        window.openModal('layer-detail');
                        try {
                            const res = await fetch(detailBtn.dataset.layerDetail, { headers: { Accept: 'text/html' } });
                            if (!res.ok) throw new Error();
                            detailBody.innerHTML = await res.text(); // server-rendered, Blade-escaped fragment
                        } catch {
                            detailBody.innerHTML = '<p class="text-error text-sm">Failed to load layer details.</p>';
                        }
                        return;
                    }

                    const editBtn = e.target.closest('[data-layer-edit]');
                    if (editBtn) {
                        const data = JSON.parse(editBtn.dataset.layerEdit);
                        editForm.action = data.action;
                        document.getElementById('layerEditAction').value = data.action;
                        editForm.elements.name.value = data.name;
                        editForm.elements.layer_category_id.value = data.layer_category_id || '';
                        editForm.elements.acquisition_date.value = data.acquisition_date || '';
                        editForm.querySelectorAll('.text-error').forEach(el => el.remove());
                        window.openModal('layer-edit');
                        return;
                    }

                    const deleteBtn = e.target.closest('[data-layer-delete]');
                    if (deleteBtn) {
                        const name = deleteBtn.dataset.layerName;
                        if (!confirm(`Delete layer "${name}" and all of its processed files? This cannot be undone.`)) return;
                        deleteBtn.disabled = true;
                        try {
                            const res = await fetch(deleteBtn.dataset.layerDelete, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to delete the layer.');
                            deleteBtn.closest('tr')?.remove();
                            MyZkToast.success(`Layer "${name}" deleted.`);
                        } catch (err) {
                            deleteBtn.disabled = false;
                            MyZkToast.error(err.message);
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
