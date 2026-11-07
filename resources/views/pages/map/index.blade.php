@section('title', $data['title'] ?? '3D Map Viewer')
@section('meta_description', '')

<x-app-front-layout>
    {{-- Full-bleed globe with floating chrome. Layout and states live in the "Map workspace" section of app.css. --}}
    <div class="map-app" id="mapApp">
        <div id="cesiumContainer" class="map-canvas"></div>

        @include('pages.map.partials.command-bar')

        @include('pages.map.partials.panels.layers-panel')

        @if (auth()->user()->canUploadData())
            @include('pages.map.partials.panels.data-panel')
        @endif

        @include('pages.map.partials.map-controls')
        @include('pages.map.partials.map-overlays')
        @include('pages.map.partials.panels.help-panel')
        @include('pages.map.partials.panels.analysis-panel')

        @include('pages.map.partials.mobile-nav')

        @if (auth()->user()->canUploadData())
            <x-upload-modal />
        @endif
    </div>


    @push('javascript')
        <script>
            window.userRole = @js(auth()->user()->role->value);
        </script>
        @vite('resources/js/map.js')

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Managed event listeners helper for SPA-like cleanup support
                const _pageListeners = [];
                const activePollTimers = [];

                function addPageEventListener(target, type, listener, options) {
                    if (target) {
                        target.addEventListener(type, listener, options);
                        _pageListeners.push({
                            target,
                            type,
                            listener,
                            options
                        });
                    }
                }

                function cleanupPageListeners() {
                    _pageListeners.forEach(({
                        target,
                        type,
                        listener,
                        options
                    }) => {
                        if (target && typeof target.removeEventListener === 'function') {
                            target.removeEventListener(type, listener, options);
                        }
                    });
                    _pageListeners.length = 0;
                }

                window.cleanupMapPage = () => {
                    activePollTimers.forEach(t => clearInterval(t));
                    activePollTimers.length = 0;
                    cleanupPageListeners();
                    if (window.cleanupMapListeners) {
                        window.cleanupMapListeners();
                    }
                    if (window.mapController && typeof window.mapController.destroy === 'function') {
                        window.mapController.destroy();
                    }
                };

                // pollJobStatus and setProgressState loaded from resources/js/upload/poll-job-status.js and resources/js/utils/progress-state.js

                // Data-driven Panel Toggles
                const panelConfigs = [{
                        name: 'layers',
                        onOpen: null
                    },
                    {
                        name: 'data',
                        onOpen: () => fetchRecentUploads()
                    },
                ];

                const panelInstances = {};
                panelConfigs.forEach(config => {
                    const panelName = config.name;
                    panelInstances[panelName] = {
                        toggleBtn: document.getElementById(`${panelName}ToggleBtn`),
                        toggleBtnMobile: document.getElementById(`${panelName}ToggleBtnMobile`),
                        closeBtn: document.getElementById(`close${panelName.charAt(0).toUpperCase() + panelName.slice(1)}Btn`),
                        panel: document.getElementById(`${panelName}Panel`),
                        onOpen: config.onOpen,
                    };
                });

                function updatePanelState(name, isOpen) {
                    [panelInstances[name].toggleBtn, panelInstances[name].toggleBtnMobile].forEach(btn => {
                        if (!btn) return;
                        btn.classList.toggle('active', isOpen);
                        btn.setAttribute('aria-expanded', String(isOpen));
                    });
                }

                function closeAllPanels() {
                    Object.keys(panelInstances).forEach(name => {
                        panelInstances[name].panel?.classList.remove('panel-open');
                        updatePanelState(name, false);
                    });
                    closeUserDropdown();
                    if (window.mapController) {
                        window.mapController.disableCoordinatePickerMode();
                    }
                }

                function togglePanel(name) {
                    const panelInstance = panelInstances[name];
                    if (!panelInstance.panel) return;
                    const isOpen = panelInstance.panel.classList.contains('panel-open');
                    closeAllPanels();
                    if (!isOpen) {
                        panelInstance.panel.classList.add('panel-open');
                        updatePanelState(name, true);
                        if (panelInstance.onOpen) panelInstance.onOpen();
                    }
                }

                /**
                 * Open a panel without toggling it closed when it is already open (side panels share one slot).
                 */
                function openPanel(name) {
                    if (!panelInstances[name]?.panel?.classList.contains('panel-open')) {
                        togglePanel(name);
                    }
                }

                function closeUserDropdown() {
                    [
                        ['userDropdown', 'userMenuBtn'],
                        ['userDropdownMobile', 'userMenuBtnMobile'],
                    ].forEach(([dropdownId, buttonId]) => {
                        const dropdown = document.getElementById(dropdownId);
                        if (dropdown) {
                            dropdown.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
                            dropdown.classList.remove('opacity-100', 'scale-100');
                        }
                        document.getElementById(buttonId)?.setAttribute('aria-expanded', 'false');
                    });
                }

                // Keyboard: L / D toggle panels, Escape closes them (ignored while typing).
                const panelShortcuts = { l: 'layers', d: 'data' };
                addPageEventListener(document, 'keydown', (e) => {
                    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
                    const target = e.target;
                    if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, select'))) return;
                    if (document.querySelector('[data-modal][aria-hidden="false"]')) return;

                    if (e.key === 'Escape') {
                        if (Object.values(panelInstances).some(p => p.panel?.classList.contains('panel-open'))) {
                            closeAllPanels();
                        }
                        return;
                    }

                    const panelName = panelShortcuts[e.key.toLowerCase()];
                    if (panelName && panelInstances[panelName]?.panel && !e.repeat) {
                        e.preventDefault();
                        togglePanel(panelName);
                    }
                });

                Object.keys(panelInstances).forEach(name => {
                    const panel = panelInstances[name];
                    if (panel.toggleBtn) panel.toggleBtn.addEventListener('click', () => togglePanel(name));
                    if (panel.toggleBtnMobile) panel.toggleBtnMobile.addEventListener('click', () => togglePanel(name));
                    if (panel.closeBtn) panel.closeBtn.addEventListener('click', closeAllPanels);
                });

                const coordWgsGroup = document.getElementById('coordWgsGroup');
                const coordUtmGroup = document.getElementById('coordUtmGroup');

                function updateCoordinateDisplay(isUtm) {
                    if (coordWgsGroup && coordUtmGroup) {
                        if (isUtm) {
                            coordWgsGroup.classList.remove('flex');
                            coordWgsGroup.classList.add('hidden');
                            coordUtmGroup.classList.remove('hidden');
                            coordUtmGroup.classList.add('flex');
                        } else {
                            coordUtmGroup.classList.remove('flex');
                            coordUtmGroup.classList.add('hidden');
                            coordWgsGroup.classList.remove('hidden');
                            coordWgsGroup.classList.add('flex');
                        }
                    }

                    const pickWgsGroup = document.getElementById('pickWgsGroup');
                    const pickUtmGroup = document.getElementById('pickUtmGroup');
                    if (pickWgsGroup && pickUtmGroup) {
                        if (isUtm) {
                            pickWgsGroup.classList.remove('grid');
                            pickWgsGroup.classList.add('hidden');
                            pickUtmGroup.classList.remove('hidden');
                            pickUtmGroup.classList.add('grid');
                        } else {
                            pickUtmGroup.classList.remove('grid');
                            pickUtmGroup.classList.add('hidden');
                            pickWgsGroup.classList.remove('hidden');
                            pickWgsGroup.classList.add('grid');
                        }
                    }

                    if (window.mapController && typeof window.mapController.refreshFeatureProperties === 'function') {
                        window.mapController.refreshFeatureProperties();
                    }
                }

                // Coordinate display follows the source: WGS84 latitude/longitude.
                updateCoordinateDisplay(false);

                // User Dropdown Logic
                const userMenuBtn = document.getElementById('userMenuBtn');
                const userDropdown = document.getElementById('userDropdown');

                const userMenuBtnMobile = document.getElementById('userMenuBtnMobile');
                const userDropdownMobile = document.getElementById('userDropdownMobile');

                const toggleUserMenu = (dropdown, e) => {
                    e.stopPropagation();
                    const isOpen = dropdown.classList.contains('opacity-100');
                    if (isOpen) {
                        closeUserDropdown();
                    } else {
                        closeUserDropdown();
                        dropdown.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
                        dropdown.classList.add('opacity-100', 'scale-100');
                        e.currentTarget?.setAttribute('aria-expanded', 'true');
                    }
                };

                // Dark mode switch inside the account menu reports its state to assistive tech.
                const syncThemeToggles = () => {
                    const isDark = document.documentElement.classList.contains('dark');
                    document.querySelectorAll('#mapApp [data-theme-toggle]').forEach(btn => btn.setAttribute('aria-pressed', String(isDark)));
                };
                syncThemeToggles();
                addPageEventListener(window, 'theme-changed', syncThemeToggles);

                if (userMenuBtn && userDropdown) {
                    addPageEventListener(userMenuBtn, 'click', (e) => toggleUserMenu(userDropdown, e));
                    addPageEventListener(document, 'click', (e) => {
                        if (!userDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
                            if (userDropdown.classList.contains('opacity-100')) closeUserDropdown();
                        }
                    });
                }

                if (userMenuBtnMobile && userDropdownMobile) {
                    addPageEventListener(userMenuBtnMobile, 'click', (e) => toggleUserMenu(userDropdownMobile, e));
                    addPageEventListener(document, 'click', (e) => {
                        if (!userDropdownMobile.contains(e.target) && !userMenuBtnMobile.contains(e.target)) {
                            if (userDropdownMobile.classList.contains('opacity-100')) closeUserDropdown();
                        }
                    });
                }

                // Upload Modal Logic (Used by page submit and active progress list handlers)
                const uploadForm = document.getElementById('uploadForm');
                const activeUploadsList = document.getElementById('activeUploadsList');

                // Note: File selection and modal UI event listeners are encapsulated inside the reusable upload-modal component.
                // We access the current state via the global window.UploadModal helper.

                // Initialize Layers
                fetchAllLayers();

                /**
                 * Mengatur kontrol filter pencarian dan bulan/tahun dengan debounce dan clear button.
                 * @param {string} searchId - ID input search
                 * @param {string} monthYearId - ID input month-year
                 * @param {string} clearBtnId - ID button clear filter
                 * @param {function} onFetch - Fungsi fetch data yang dipicu
                 * @param {number} [debounceMs=400] - Durasi debounce dalam milidetik
                 */
                function wireFilterControls(searchId, monthYearId, clearBtnId, onFetch, debounceMs = 400) {
                    const searchEl = document.getElementById(searchId);
                    const monthYearEl = document.getElementById(monthYearId);
                    const clearBtn = document.getElementById(clearBtnId);
                    let timeout;

                    const trigger = () => {
                        clearTimeout(timeout);
                        timeout = setTimeout(onFetch, debounceMs);
                    };

                    if (searchEl) {
                        addPageEventListener(searchEl, 'input', trigger);
                    }

                    if (monthYearEl) {
                        addPageEventListener(monthYearEl, 'change', function() {
                            if (clearBtn) {
                                clearBtn.classList.toggle('hidden', !this.value);
                            }
                            trigger();
                        });

                        // Initial check
                        if (monthYearEl.value && clearBtn) {
                            clearBtn.classList.remove('hidden');
                        }
                    }

                    if (clearBtn && monthYearEl) {
                        addPageEventListener(clearBtn, 'click', function() {
                            monthYearEl.value = '';
                            this.classList.add('hidden');
                            trigger();
                        });
                    }
                }

                // Layer Filter functionality
                wireFilterControls(
                    'searchLayers',
                    'filterMonthYearLayers',
                    'clearMonthYearLayersFilter',
                    () => {
                        fetchAllLayers();
                    }
                );

                document.getElementById('refreshRecentUploadsBtn')?.addEventListener('click', fetchRecentUploads);

                const refreshLayersBtn = document.getElementById('refreshLayersBtn');
                if (refreshLayersBtn) {
                    refreshLayersBtn.addEventListener('click', function() {
                        const icon = this.querySelector('i');
                        if (icon) icon.classList.add('animate-spin');

                        const allLayersListContainer = document.getElementById('allLayersList');
                        if (allLayersListContainer) allLayersListContainer.innerHTML = '';

                        fetchAllLayers();
                        if (icon) {
                            setTimeout(() => icon.classList.remove('animate-spin'), 600);
                        }
                    });
                }

                function fetchRecentUploads() {
                    const list = document.getElementById('recentUploadList');
                    if (!list) return;

                    // Ensure loading state if list is being initialized or refreshed from empty
                    if (list.innerHTML.trim() === '' || list.querySelector('#recentUploadEmptyState')) {
                        list.innerHTML = `
                            <x-loading-spinner id="recentUploadLoading" message="Loading recent uploads" />
                        `;
                    }

                    fetch('{{ route('layers.recent-uploads') }}')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (data.jobs.length === 0) {
                                    list.innerHTML = `
                                        <div id="recentUploadEmptyState" class="map-empty">
                                            <i class="ri-history-line" aria-hidden="true"></i>
                                            <p class="map-empty-title">No recent uploads</p>
                                            <p class="map-empty-text">Finished and failed conversions are listed here.</p>
                                        </div>
                                    `;
                                    return;
                                }

                                list.innerHTML = data.jobs.map(renderJobItem).join('');
                            }
                        })
                        .catch(err => {
                            console.error('Error loading recent uploads:', err);
                            list.innerHTML = `
                                <div class="map-empty">
                                    <i class="ri-error-warning-line text-map-danger" aria-hidden="true"></i>
                                    <p class="map-empty-title">Couldn't load recent uploads</p>
                                    <p class="map-empty-text">Check your connection, then press Refresh.</p>
                                </div>
                            `;
                        });
                }

                // Full class strings (not interpolated) so Tailwind can see them.
                const jobTones = {
                    done: { tile: 'bg-map-success/12 text-map-success', icon: 'ri-checkbox-circle-fill' },
                    failed: { tile: 'bg-map-danger/12 text-map-danger', icon: 'ri-error-warning-fill' },
                    running: { tile: 'bg-map-accent-soft text-map-accent-ink', icon: 'ri-loader-4-line animate-spin' },
                };

                function renderJobItem(job) {
                    const tone = jobTones[job.is_done ? 'done' : (job.is_failed ? 'failed' : 'running')];
                    const typeIcon = job.layer_type === 'terrain' ? 'ri-landscape-line' : 'ri-shape-line';
                    const processing = (!job.is_done && !job.is_failed) ?
                        '<span aria-hidden="true">&middot;</span><span class="font-semibold text-map-accent-ink animate-pulse">Processing</span>' : '';

                    return `
                        <div class="flex items-center gap-3 rounded-xl px-2 py-1.5 transition-colors hover:bg-map-hover">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-[0.625rem] ${tone.tile}" aria-hidden="true">
                                <i class="${tone.icon} text-base"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1.5 text-[0.8125rem] font-semibold text-map-ink">
                                    <i class="${typeIcon} text-xs text-map-subtle" aria-hidden="true"></i>
                                    <span class="truncate">${window.escapeHtml(job.layer_name)}</span>
                                </p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[0.6875rem] text-map-subtle">
                                    <span>${window.escapeHtml(job.time_diff)}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>${window.escapeHtml(job.status_label)}</span>
                                    ${processing}
                                </p>
                            </div>
                        </div>`;
                }

                async function fetchAllLayers() {
                    const searchInput = document.getElementById('searchLayers');
                    const monthYearInput = document.getElementById('filterMonthYearLayers');

                    const searchQuery = searchInput ? searchInput.value : '';
                    const monthYear = monthYearInput ? monthYearInput.value : '';

                    const list = document.getElementById('allLayersList');
                    if (!list) return;

                    if (list.innerHTML.trim() === '' || list.querySelector('#layersEmptyState') || list.querySelector('#layersErrorState')) {
                        list.innerHTML = `
                            <x-skeleton count="4" id="layersLoading" />
                        `;
                    }

                    const render = (categories) => {
                        let filteredCats = JSON.parse(JSON.stringify(categories));

                        if (searchQuery || monthYear) {
                            filteredCats = filteredCats.map(cat => {
                                cat.layers = cat.layers.filter(layer => {
                                    let matches = true;
                                    if (searchQuery) {
                                        matches = matches && layer.name.toLowerCase().includes(searchQuery.toLowerCase());
                                    }
                                    if (monthYear) {
                                        const acqDate = layer.layer_type === 'terrain' ?
                                            (layer.terrain_detail?.acquisition_date || '') :
                                            (layer.vector_detail?.acquisition_date || '');
                                        const crDate = layer.created_at || '';
                                        matches = matches && (matchMonthYear(acqDate, monthYear) || matchMonthYear(crDate, monthYear));
                                    }
                                    return matches;
                                });
                                return cat;
                            }).filter(cat => cat.layers.length > 0);
                        }

                        if (filteredCats.length === 0) {
                            const isFiltered = Boolean(searchQuery || monthYear);
                            const emptyTitle = searchQuery ?
                                'No layers match “' + window.escapeHtml(searchQuery) + '”' :
                                (isFiltered ? 'No layers in this month' : 'No layers yet');
                            const emptyText = isFiltered ?
                                'Try another name or clear the month filter.' :
                                (window.userRole === 'admin' ? 'Upload terrain or vector data from the Data panel.' : 'Layers appear here once an admin uploads data.');
                            list.innerHTML = `
                                <div id="layersEmptyState" class="map-empty mt-1">
                                    <i class="${isFiltered ? 'ri-search-line' : 'ri-stack-line'}" aria-hidden="true"></i>
                                    <p class="map-empty-title">${emptyTitle}</p>
                                    <p class="map-empty-text">${emptyText}</p>
                                </div>
                            `;
                            return;
                        }

                        const html = filteredCats.map(cat => {
                            const layersHtml = cat.layers.map(layer => {
                                const layerIdStr = String(layer.id);
                                let isActive = false;
                                let typeIcon = 'ri-shape-line';
                                let dataAttrs = '';

                                if (layer.layer_type === 'terrain') {
                                    isActive = window.mapController && window.mapController.loadedTerrainId === layerIdStr;
                                    typeIcon = 'ri-landscape-fill';
                                    const bbox = JSON.stringify(layer.terrain_detail ? (layer.terrain_detail.native_bounding_box || {}) : {});
                                    dataAttrs =
                                        `data-id='${layer.id}' data-terrain-url='${layer.terrain_detail ? layer.terrain_detail.tileset_url : ''}' data-has-ortho='${layer.terrain_detail ? layer.terrain_detail.has_ortho : 'false'}' data-bbox='${bbox}' data-has-ortho-tiles='${layer.terrain_detail ? layer.terrain_detail.has_ortho_tiles : 'false'}' data-ortho-tiles-url='${layer.terrain_detail ? (layer.terrain_detail.ortho_tiles_url || '') : ''}' data-ortho-tiles-min-zoom='${layer.terrain_detail ? (layer.terrain_detail.ortho_min_zoom || 10) : 10}' data-ortho-tiles-max-zoom='${layer.terrain_detail ? (layer.terrain_detail.ortho_max_zoom || 18) : 18}'`;
                                } else {
                                    if (window.mapController && window.mapController.activeVectorLayers.has(layerIdStr)) {
                                        const collections = window.mapController.activeVectorLayers.get(layerIdStr).collections;
                                        isActive = collections && collections.length > 0 && collections[0].show;
                                    }
                                    typeIcon = 'ri-shape-line';
                                    const bbox = JSON.stringify(layer.vector_detail ? (layer.vector_detail.native_bounding_box || {}) : {});
                                    dataAttrs =
                                        `data-id='${layer.id}' data-bbox='${bbox}' data-category-color='${window.safeColor(cat.color)}' data-category-icon-url='${window.escapeHtml(cat.icon_url || '')}' data-category-name='${window.escapeHtml(cat.name)}'`;
                                }

                                const acqDate = layer.layer_type === 'terrain' ?
                                    (layer.terrain_detail?.acquisition_date || 'N/A') :
                                    (layer.vector_detail?.acquisition_date || 'N/A');

                                return `
                                    <x-layer-card
                                        id="${layer.id}"
                                        name="${window.escapeHtml(layer.name)}"
                                        acquisitionDate="${window.escapeHtml(acqDate)}"
                                        createdAt="${window.escapeHtml(layer.created_at)}"
                                        dataAttrs="${dataAttrs}"
                                        layerType="${layer.layer_type}"
                                        isActiveJS="${isActive}"
                                        categoryColor="${window.safeColor(cat.color)}"
                                    >
                                    </x-layer-card>
                                `;
                            }).join('');

                            return `
                                <x-accordion-group-data id="${window.escapeHtml(cat.id)}" title="${window.escapeHtml(cat.name)}" count="${cat.layers.length}">
                                    <x-slot:icon>
                                        <span class="map-group-dot" style="--dot: ${window.safeColor(cat.color)}" aria-hidden="true"></span>
                                    </x-slot:icon>
                                    ${layersHtml}
                                </x-accordion-group-data>
                            `;
                        }).join('');

                        list.innerHTML = `<div class="flex flex-col">${html}</div>`;

                        document.dispatchEvent(new CustomEvent('vectorLayersRendered'));
                    };

                    let url = '{{ route('layers.all') }}';
                    const params = new URLSearchParams();
                    if (searchQuery) params.append('search', searchQuery);
                    if (monthYear) params.append('month_year', monthYear);

                    if (params.toString()) {
                        url += '?' + params.toString();
                    }
                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                render(data.categories);
                            }
                        })
                        .catch(err => {
                            console.error('Error loading layers:', err);
                            list.innerHTML = `
                                <div id="layersErrorState" class="map-empty mt-1">
                                    <i class="ri-error-warning-line text-map-danger" aria-hidden="true"></i>
                                    <p class="map-empty-title">Couldn't load layers</p>
                                    <p class="map-empty-text">Check your connection, then press the refresh button.</p>
                                </div>
                            `;
                        });
                }

                const uploadStates = new Map();

                /**
                 * Mirror the number of in-flight uploads on the Data nav buttons (desktop + mobile).
                 */
                function syncUploadBadge() {
                    const count = activeUploadsList ? activeUploadsList.children.length : 0;
                    const emptyState = document.getElementById('activeUploadsEmpty');
                    if (emptyState) emptyState.hidden = count > 0;
                    ['uploadCount', 'uploadCountMobile'].forEach(id => {
                        const badge = document.getElementById(id);
                        if (!badge) return;
                        badge.textContent = count > 99 ? '99+' : String(count);
                        badge.hidden = count === 0;
                    });
                }

                if (activeUploadsList) {
                    new MutationObserver(syncUploadBadge).observe(activeUploadsList, { childList: true });
                    syncUploadBadge();
                }

                // Prevent closing page if uploads are in progress
                addPageEventListener(window, 'beforeunload', (e) => {
                    if (uploadStates.size > 0) {
                        const states = Array.from(uploadStates.values());
                        const isUploading = states.some(s => s.status === 'uploading');
                        const isProcessing = states.some(s => s.status === 'processing');

                        if (isUploading) {
                            e.preventDefault();
                            e.returnValue = 'Uploads are still in progress. If you leave now, the upload will be cancelled. Are you sure you want to leave?';
                            return e.returnValue;
                        } else if (isProcessing) {
                            e.preventDefault();
                            e.returnValue = 'Background processing is running on the server and can be left safely. You are safe to leave or close this page.';
                            return e.returnValue;
                        }
                    }
                });

                // uploadFileInChunks, generateUploadId, createProgressCard loaded from resources/js/upload/chunked-upload.js
                if (uploadForm) uploadForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const selectedType = uploadForm.querySelector('input[name="dataType"]:checked').value;
                    const layerNameInput = document.getElementById('layerName');
                    const csrfToken = getCsrfToken();

                    if (selectedType === 'terrain') {
                        const selectedDemFile = window.UploadModal.getSelectedDemFile();
                        const selectedOrthoFile = window.UploadModal.getSelectedOrthoFile();
                        if (!selectedDemFile) return;

                        // Capture values before form reset clears them
                        const uploadDemFile = selectedDemFile;
                        const uploadOrthoFile = selectedOrthoFile;
                        const finalLayerName = layerNameInput ? layerNameInput.value || uploadDemFile.name : uploadDemFile.name;
                        const finalAcquisitionDate = document.getElementById('acquisitionDate')?.value;
                        const layerCategoryId = window.UploadModal.getLayerCategoryId();

                        closeModal('upload');
                        window.UploadModal.reset();

                        // Show the data panel (closes the layers panel, which shares its slot)
                        openPanel('data');

                        // Create progress item in activeUploadsList
                        const uploadId = generateUploadId();
                        const progressHtml = `
                            <x-upload-progress-card
                                uploadId="${uploadId}"
                                fileName="${escapeUploadText(finalLayerName)}"
                                type="terrain"
                                typeIcon="ri-landscape-line"
                            />
                        `;
                        activeUploadsList.insertAdjacentHTML('afterbegin', progressHtml);
                        const progressItem = document.getElementById(uploadId);
                        const progressLabel = progressItem.querySelector('.progress-label');
                        const progressPercent = progressItem.querySelector('.progress-percent');
                        const progressContainer = progressItem.querySelector('.progress-container');
                        const progressBar = progressItem.querySelector('.progress-bar');

                        try {
                            const demResult = await uploadFileInChunks(uploadDemFile, (current, total) => {
                                const percent = Math.round((current / total) * 100);
                                progressLabel.textContent = `Uploading DEM (${uploadDemFile.name})...`;
                                progressPercent.textContent = `${percent}%`;
                                progressBar.style.width = `${percent}%`;
                            }, uploadId, uploadStates, {
                                chunkUploadUrl: '{{ route('upload.chunk') }}'
                            });

                            let orthoResult = null;
                            if (uploadOrthoFile) {
                                progressLabel.textContent = `Uploading Orthophoto (${uploadOrthoFile.name})...`;
                                progressPercent.textContent = `0%`;
                                progressBar.style.width = `0%`;
                                orthoResult = await uploadFileInChunks(uploadOrthoFile, (current, total) => {
                                    const percent = Math.round((current / total) * 100);
                                    progressPercent.textContent = `${percent}%`;
                                    progressBar.style.width = `${percent}%`;
                                }, uploadId, uploadStates, {
                                    chunkUploadUrl: '{{ route('upload.chunk') }}'
                                });
                            }

                            // Remove controls once uploading is done
                            const controls = progressItem.querySelector('.upload-controls');
                            if (controls) controls.remove();

                            progressLabel.textContent = 'Assembling and verifying files...';
                            progressPercent.textContent = '100%';
                            progressBar.style.width = '100%';

                            const assemblePayload = {
                                layerName: finalLayerName,
                                acquisitionDate: finalAcquisitionDate,
                                demIdentifier: demResult.identifier,
                                demFileName: demResult.fileName,
                                demTotalChunks: demResult.totalChunks,
                                orthoIdentifier: orthoResult ? orthoResult.identifier : null,
                                orthoFileName: orthoResult ? orthoResult.fileName : null,
                                orthoTotalChunks: orthoResult ? orthoResult.totalChunks : null,
                                layer_category_id: layerCategoryId
                            };

                            const assembleData = await submitGeodataUpload('{{ route('upload.assemble-raster') }}', assemblePayload, uploadStates.get(uploadId));
                            const jobId = assembleData.job_id;

                            // Update upload state to processing
                            const state = uploadStates.get(uploadId);
                            if (state) {
                                state.status = 'processing';
                            }

                            // Start polling for status
                            setProgressState(progressLabel, 'queued', 'Queued for processing.');
                            progressPercent.style.display = 'none'; // Hide percent text
                            progressContainer.style.display = 'none'; // Hide progress bar

                            const updateUploadCount = syncUploadBadge;

                            updateUploadCount();

                            pollJobStatus(jobId, {
                                interval: window.CONFIG.POLL_INTERVAL.TERRAIN,
                                statusBaseUrl: '{{ route('upload.status', ['job' => ':job']) }}'.replace(':job', ''),
                                onProcessing: () => {
                                    setProgressState(progressLabel, 'processing', 'Processing terrain tiles.');
                                },
                                onDone: () => {
                                    setProgressState(progressLabel, 'success', 'Successfully Processed!');

                                    uploadStates.delete(uploadId);
                                    setTimeout(() => {
                                        fetchRecentUploads();
                                        fetchAllLayers();
                                        progressItem.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                                        setTimeout(() => {
                                            progressItem.remove();
                                            updateUploadCount();
                                        }, 500);
                                    }, 1000);
                                },
                                onFail: (errorMessage) => {
                                    uploadStates.delete(uploadId);
                                    setProgressState(progressLabel, 'error', 'Processing Failed: ' + errorMessage);
                                }
                            }, activePollTimers);

                        } catch (error) {
                            console.error('Upload Error:', error);
                            if (error.message !== 'CANCELLED') {
                                if (typeof progressLabel !== 'undefined') {
                                    setProgressState(progressLabel, 'error', 'Upload Failed: ' + error.message);
                                }
                                if (typeof progressPercent !== 'undefined') progressPercent.style.display = 'none';
                                if (typeof progressContainer !== 'undefined') progressContainer.style.display = 'none';
                            }
                            if (error.message !== 'CANCELLED') markUploadFailed(uploadId, uploadStates, progressItem);

                            syncUploadBadge();
                        }
                    } else if (selectedType === 'vector') {
                        const selectedFile = window.UploadModal.getSelectedFile();
                        if (!selectedFile) return;

                        const uploadFile = selectedFile;
                        const finalLayerName = layerNameInput ? layerNameInput.value || uploadFile.name : uploadFile.name;
                        const sourceEpsg = document.getElementById('sourceEpsg')?.value;
                        const finalAcquisitionDate = document.getElementById('acquisitionDate')?.value;
                        const layerCategoryId = window.UploadModal.getLayerCategoryId();

                        closeModal('upload');
                        window.UploadModal.reset();

                        // Show the data panel (closes the layers panel, which shares its slot)
                        openPanel('data');

                        const uploadId = generateUploadId();
                        const progressHtml = `
                            <x-upload-progress-card
                                uploadId="${uploadId}"
                                fileName="${escapeUploadText(finalLayerName)}"
                                type="vector"
                                typeIcon="ri-shape-line"
                            />
                        `;
                        activeUploadsList.insertAdjacentHTML('afterbegin', progressHtml);
                        const progressItem = document.getElementById(uploadId);
                        const progressLabel = progressItem.querySelector('.progress-label');
                        const progressPercent = progressItem.querySelector('.progress-percent');
                        const progressContainer = progressItem.querySelector('.progress-container');
                        const progressBar = progressItem.querySelector('.progress-bar');

                        try {
                            const result = await uploadFileInChunks(uploadFile, (current, total) => {
                                const percent = Math.round((current / total) * 100);
                                progressPercent.textContent = `${percent}%`;
                                progressBar.style.width = `${percent}%`;
                            }, uploadId, uploadStates, {
                                chunkUploadUrl: '{{ route('upload.chunk') }}'
                            });

                            const controls = progressItem.querySelector('.upload-controls');
                            if (controls) controls.remove();

                            progressLabel.textContent = 'Assembling and importing...';
                            progressPercent.textContent = '100%';
                            progressBar.style.width = '100%';

                            const assemblePayload = {
                                layerName: finalLayerName,
                                identifier: result.identifier,
                                fileName: result.fileName,
                                totalChunks: result.totalChunks,
                                sourceEpsg: sourceEpsg || null,
                                acquisitionDate: finalAcquisitionDate || null,
                                layer_category_id: layerCategoryId || null
                            };

                            const assembleData = await submitGeodataUpload('{{ route('upload.assemble-vector') }}', assemblePayload, uploadStates.get(uploadId));
                            const jobId = assembleData.job_id;

                            // Update upload state to processing
                            const state = uploadStates.get(uploadId);
                            if (state) {
                                state.status = 'processing';
                            }

                            setProgressState(progressLabel, 'queued', 'Queued for processing.');
                            progressPercent.style.display = 'none';
                            progressContainer.style.display = 'none';

                            pollJobStatus(jobId, {
                                interval: window.CONFIG.POLL_INTERVAL.VECTOR,
                                statusBaseUrl: '{{ route('upload.status', ['job' => ':job']) }}'.replace(':job', ''),
                                onProcessing: () => {
                                    setProgressState(progressLabel, 'processing', 'Importing features to PostGIS.');
                                },
                                onDone: () => {
                                    setProgressState(progressLabel, 'success', 'Successfully Imported!');

                                    uploadStates.delete(uploadId);
                                    setTimeout(() => {
                                        fetchAllLayers();
                                        fetchRecentUploads();
                                        progressItem.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                                        setTimeout(() => {
                                            progressItem.remove();
                                        }, 500);
                                    }, 1000);
                                },
                                onFail: (errorMessage) => {
                                    uploadStates.delete(uploadId);
                                    setProgressState(progressLabel, 'error', 'Failed: ' + errorMessage);
                                }
                            }, activePollTimers);

                        } catch (error) {
                            if (error.message !== 'CANCELLED') {
                                setProgressState(progressLabel, 'error', 'Error: ' + error.message);
                            }
                            if (error.message !== 'CANCELLED') markUploadFailed(uploadId, uploadStates, progressItem);
                        }
                    } else {
                        const selectedFile = window.UploadModal.getSelectedFile();
                        if (!selectedFile) return;
                        closeModal('upload');
                    }
                });

                // Copy the picked point ("lat, lon, z") from the coordinate card.
                const copyPickedBtn = document.getElementById('copyPickedCoordinatesBtn');
                addPageEventListener(copyPickedBtn, 'click', async () => {
                    const read = id => (document.getElementById(id)?.textContent || '').trim();
                    const parts = [read('pickLatVal'), read('pickLngVal')];
                    const elevation = read('pickZVal').replace(/\s*m$/, '');
                    if (elevation && elevation !== '-') parts.push(elevation);
                    if (parts.some(part => !part || part === '-')) return;

                    const text = parts.join(', ');
                    const copyWithSelection = () => {
                        const field = document.createElement('textarea');
                        field.value = text;
                        field.setAttribute('readonly', '');
                        field.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
                        document.body.appendChild(field);
                        field.select();
                        const copied = document.execCommand('copy');
                        field.remove();
                        return copied;
                    };

                    let copied = false;
                    try {
                        await navigator.clipboard.writeText(text);
                        copied = true;
                    } catch (error) {
                        copied = copyWithSelection();
                    }

                    const icon = copyPickedBtn.querySelector('i');
                    if (icon) icon.className = copied ? 'ri-check-line text-map-success' : 'ri-error-warning-line text-map-danger';
                    copyPickedBtn.setAttribute('aria-label', copied ? 'Coordinates copied' : 'Copy failed');
                    setTimeout(() => {
                        if (icon) icon.className = 'ri-file-copy-line';
                        copyPickedBtn.setAttribute('aria-label', 'Copy coordinates');
                    }, 1500);
                });

                // Pause/Cancel/Dismiss delegation loaded from resources/js/upload/upload-wiring.js
                if (activeUploadsList) wireUploadControls(activeUploadsList, uploadStates, {
                    cancelBtnClass: 'cancel-upload-btn',
                    onCancel: (uploadId, progressItem, state) => {
                        uploadStates.delete(uploadId);
                        progressItem.remove();
                    }
                });

            });
        </script>
    @endpush

</x-app-front-layout>
