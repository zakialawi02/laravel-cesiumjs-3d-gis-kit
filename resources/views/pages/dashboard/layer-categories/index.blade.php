@php
    $modalName = 'layer-category-form';
    $reopen = old('_modal') === $modalName;
    $totalLayers = $categories->sum('layers_count');
@endphp

<x-app-layout title="Layer Categories">
    <div class="space-y-5">

        <!-- Header -->
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="text-foreground text-xl font-extrabold">Layer Categories</h2>
                <p class="text-foreground/60 text-sm">Group layers in the map's Layer panel. {{ $categories->count() }} categories • {{ $totalLayers }} layers.</p>
            </div>
            <x-button-primary type="button" class="text-sm" data-category-create>
                <i class="ri-add-line"></i> New Category
            </x-button-primary>
        </section>

        <!-- Search -->
        <form method="GET" action="{{ route('dashboard.layer-categories.index') }}" class="flex max-w-md gap-2">
            <div class="flex-1">
                <label for="search" class="sr-only">Search</label>
                <x-text-input id="search" name="search" type="search" icon="ri-search-line" placeholder="Search category..." :value="$search" />
            </div>
            @if ($search !== '')
                <a href="{{ route('dashboard.layer-categories.index') }}" class="border-border hover:bg-muted inline-flex items-center justify-center rounded-[0.625rem] border px-3 text-sm" title="Clear search">
                    <i class="ri-close-line"></i>
                </a>
            @endif
        </form>

        <!-- Table -->
        <section class="bg-neutral border-border overflow-hidden rounded-xl border shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-muted/50 text-foreground/60 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="w-16 px-4 py-3">Order</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Layers</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        @forelse ($categories as $category)
                            @php
                                $isProtected = $category->slug === $protectedSlug;
                                $payload = [
                                    'action' => route('dashboard.layer-categories.update', $category),
                                    'name' => $category->name,
                                    'color' => $category->color ?: '#6b7280',
                                    'description' => $category->description,
                                    'sort_order' => $category->sort_order,
                                    'icon_url' => $category->icon_url,
                                ];
                            @endphp
                            <tr class="hover:bg-muted/30">
                                <td class="text-foreground/60 px-4 py-3 font-mono text-xs">{{ $category->sort_order }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-black/5"
                                            style="background-color: {{ $category->color ?: '#6b7280' }}">
                                            @if ($category->icon_url)
                                                <img src="{{ $category->icon_url }}" alt="" class="h-6 w-6 object-contain">
                                            @else
                                                <i class="ri-stack-line text-white"></i>
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-foreground flex items-center gap-1.5 font-semibold">
                                                {{ $category->name }}
                                                @if ($isProtected)
                                                    <span class="bg-muted text-foreground/60 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase" title="Default category; layers from deleted categories move here">Default</span>
                                                @endif
                                            </p>
                                            <p class="text-foreground/50 font-mono text-[10px]">{{ $category->slug }} • {{ $category->color }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-foreground/70 max-w-sm px-4 py-3 text-xs">{{ $category->description ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('dashboard.layers.index', ['category' => $category->id]) }}" class="text-primary text-xs font-semibold hover:underline">
                                        {{ $category->layers_count }} {{ \Illuminate\Support\Str::plural('layer', $category->layers_count) }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="text-foreground/60 hover:bg-primary/10 hover:text-primary rounded-md p-1.5" title="Edit"
                                            data-category-edit="{{ json_encode($payload) }}">
                                            <i class="ri-edit-line text-base"></i>
                                        </button>
                                        @if ($isProtected)
                                            <span class="text-foreground/25 cursor-not-allowed p-1.5" title="The default category cannot be deleted"><i class="ri-lock-line text-base"></i></span>
                                        @else
                                            <form method="POST" action="{{ route('dashboard.layer-categories.destroy', $category) }}"
                                                data-confirm="Delete &quot;{{ $category->name }}&quot;?{{ $category->layers_count ? ' Its '.$category->layers_count.' layer(s) will move to Other.' : '' }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-foreground/60 hover:bg-error/10 hover:text-error rounded-md p-1.5" title="Delete">
                                                    <i class="ri-delete-bin-line text-base"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state icon="ri-folders-line" iconClass="text-3xl" :message="$search !== '' ? 'No categories match your search.' : 'No layer categories yet.'" containerClass="py-12" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Create / edit modal -->
    <x-modal :name="$modalName" maxWidth="lg" :show="$reopen">
        <form method="POST" id="categoryForm" enctype="multipart/form-data"
            action="{{ $reopen ? old('_action') : route('dashboard.layer-categories.store') }}" class="space-y-4 p-6">
            @csrf
            <input type="hidden" name="_method" id="categoryFormMethod" value="{{ $reopen && old('_action') !== route('dashboard.layer-categories.store') ? 'PUT' : 'POST' }}">
            <input type="hidden" name="_modal" value="{{ $modalName }}">
            <input type="hidden" name="_action" id="categoryFormAction" value="{{ old('_action', route('dashboard.layer-categories.store')) }}">

            <div class="flex items-center justify-between">
                <h3 class="text-foreground text-base font-bold" id="categoryFormTitle">
                    {{ $reopen && old('_action') !== route('dashboard.layer-categories.store') ? 'Edit Category' : 'New Category' }}
                </h3>
                <button type="button" class="text-foreground/60 hover:text-error" data-modal-close aria-label="Close"><i class="ri-close-line text-xl"></i></button>
            </div>

            <!-- Live preview -->
            <div class="bg-muted/50 flex items-center gap-3 rounded-xl p-3">
                <span id="categoryPreviewBadge" class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                    style="background-color: {{ old('color', '#1a4fa0') }}">
                    <img id="categoryPreviewImg" src="" alt="" class="hidden h-7 w-7 object-contain">
                    <i id="categoryPreviewIcon" class="ri-stack-line text-white"></i>
                </span>
                <div class="min-w-0">
                    <p id="categoryPreviewName" class="text-foreground truncate text-sm font-semibold">{{ old('name') ?: 'Category name' }}</p>
                    <p class="text-foreground/50 text-[11px]">Preview in the Layer panel</p>
                </div>
            </div>

            <div>
                <x-input-label for="category_name" value="Name" />
                <x-text-input id="category_name" name="name" type="text" :value="old('name')" required maxlength="100" :isError="$errors->has('name')" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="category_color" value="Color" />
                    <div class="flex items-center gap-2">
                        <input type="color" id="category_color_picker" value="{{ old('color', '#1a4fa0') }}" class="border-border h-10 w-12 cursor-pointer rounded-lg border bg-transparent p-1" aria-label="Pick color">
                        <x-text-input id="category_color" name="color" type="text" :value="old('color', '#1a4fa0')" required maxlength="7" pattern="#[0-9a-fA-F]{6}" class="font-mono" :isError="$errors->has('color')" />
                    </div>
                    <x-input-error :messages="$errors->get('color')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="category_sort_order" value="Sort Order" />
                    <x-text-input id="category_sort_order" name="sort_order" type="number" min="0" max="9999" :value="old('sort_order', 0)" :isError="$errors->has('sort_order')" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="category_description" value="Description (optional)" />
                <textarea id="category_description" name="description" rows="2" maxlength="200"
                    class="border-border bg-input text-foreground focus:border-primary w-full rounded-[0.625rem] border-[1.5px] px-3 py-2 text-sm outline-none">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="category_icon" value="Icon (optional, PNG/WebP, max 512 KB)" />
                <input type="file" id="category_icon" name="icon" accept="image/png,image/webp"
                    class="text-foreground/70 file:bg-muted file:text-foreground hover:file:bg-muted/70 w-full text-sm file:me-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold">
                <x-input-error :messages="$errors->get('icon')" class="mt-1" />
                <label id="categoryRemoveIconWrap" class="text-foreground/70 mt-2 hidden items-center gap-2 text-xs">
                    <input type="checkbox" name="remove_icon" value="1" id="category_remove_icon" class="accent-error"> Remove current icon
                </label>
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
                const form = document.getElementById('categoryForm');
                const storeUrl = @js(route('dashboard.layer-categories.store'));
                const picker = document.getElementById('category_color_picker');
                const colorInput = form.elements.color;
                const badge = document.getElementById('categoryPreviewBadge');
                const previewImg = document.getElementById('categoryPreviewImg');
                const previewIcon = document.getElementById('categoryPreviewIcon');
                const previewName = document.getElementById('categoryPreviewName');
                const removeWrap = document.getElementById('categoryRemoveIconWrap');
                const isHex = (value) => /^#[0-9a-fA-F]{6}$/.test(value);

                const setPreviewImage = (url) => {
                    previewImg.src = url || '';
                    previewImg.classList.toggle('hidden', !url);
                    previewIcon.classList.toggle('hidden', !!url);
                };

                const setColor = (value) => {
                    if (!isHex(value)) return;
                    picker.value = value;
                    badge.style.backgroundColor = value;
                };

                const fill = (data) => {
                    const isEdit = !!data;
                    form.reset();
                    form.action = isEdit ? data.action : storeUrl;
                    form.elements._action.value = form.action;
                    document.getElementById('categoryFormMethod').value = isEdit ? 'PUT' : 'POST';
                    document.getElementById('categoryFormTitle').textContent = isEdit ? 'Edit Category' : 'New Category';
                    form.elements.name.value = data?.name ?? '';
                    colorInput.value = data?.color ?? '#1a4fa0';
                    form.elements.sort_order.value = data?.sort_order ?? 0;
                    form.elements.description.value = data?.description ?? '';
                    previewName.textContent = data?.name || 'Category name';
                    setColor(colorInput.value);
                    setPreviewImage(data?.icon_url);
                    removeWrap.classList.toggle('hidden', !data?.icon_url);
                    removeWrap.classList.toggle('flex', !!data?.icon_url);
                    form.querySelectorAll('[data-input-error]').forEach(el => el.remove());
                    window.openModal(@js($modalName));
                };

                document.querySelector('[data-category-create]')?.addEventListener('click', () => fill(null));
                document.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-category-edit]');
                    if (editBtn) fill(JSON.parse(editBtn.dataset.categoryEdit));
                });

                picker.addEventListener('input', () => { colorInput.value = picker.value; setColor(picker.value); });
                colorInput.addEventListener('input', () => setColor(colorInput.value.trim()));
                form.elements.name.addEventListener('input', () => { previewName.textContent = form.elements.name.value || 'Category name'; });
                form.elements.icon.addEventListener('change', () => {
                    const file = form.elements.icon.files[0];
                    setPreviewImage(file ? URL.createObjectURL(file) : null);
                });

                document.querySelectorAll('form[data-confirm]').forEach(f => {
                    f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
                });
            });
        </script>
    @endpush
</x-app-layout>
