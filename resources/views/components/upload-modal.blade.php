<x-modal name="upload" maxWidth="lg" id="uploadModal">
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h3 id="uploadModalLabel" class="text-xl font-bold text-primary">Upload New Asset</h3>
                <p class="text-xs text-foreground/60">Configure your data before uploading to the map</p>
            </div>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full hover:bg-muted text-foreground/70 hover:text-error transition-all" data-modal-close
                aria-label="Close">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>

        <form id="uploadForm" class="space-y-6">
                    <!-- Data Type Selection -->
                    <div>
                        <x-input-label for="data_type" :value="__('Data Type')" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-radio-card name="dataType" value="terrain" icon="ri-landscape-line" title="Terrain" description="Topography data" checked />
                            <x-radio-card name="dataType" value="vector" icon="ri-shape-line" title="Vector" description="Points, lines, polygons" />
                            {{-- <x-radio-card name="dataType" value="imagery" icon="ri-image-line" title="Imagery" description="Satellite/Drone images" /> --}}
                            {{-- <x-radio-card name="dataType" value="pointcloud" icon="ri-bubble-chart-line" title="Point Cloud" description="3D laser scanning" /> --}}
                        </div>
                    </div>

                    <!-- Metadata Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <x-input-label for="layerName" :value="__('Layer Name')" icon="ri-font-size" />
                            <x-text-input id="layerName" name="layerName" placeholder="Enter layer name" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="acquisitionDate" :value="__('Acquisition Date')" icon="ri-calendar-line" />
                            <input type="date" id="acquisitionDate" name="acquisitionDate"
                                class="w-full rounded-xl border border-border bg-input px-4 py-2.5 text-sm transition-all focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10 text-foreground">
                        </div>
                    </div>

                    <!-- Category Selection -->
                    <div id="layerCategorySelectWrapper" class="hidden space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
                        <x-input-label for="layerCategorySelect" :value="__('Category')" icon="ri-price-tag-3-line" />
                        <select id="layerCategorySelect" name="layerCategorySelect"
                            class="w-full rounded-xl border border-border bg-input px-4 py-2.5 text-sm transition-all focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10 text-foreground">
                            <option value="">Loading categories...</option>
                        </select>
                    </div>

                    <!-- CRS Selection (Shown for STR files) -->
                    <div id="crsSelectionContainer" class="hidden space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
                        <x-input-label for="sourceEpsg" :value="__('Source CRS (EPSG)')" icon="ri-map-pin-range-line" />
                        @php
                            $defaultEpsg = config('geodata.utm.epsg');
                            $defaultLabel = config('geodata.utm.label');
                            $crsOptions = [
                                'EPSG:4326' => 'WGS 84 (EPSG:4326)',
                                'EPSG:32749' => 'WGS 84 / UTM zone 49S (EPSG:32749)',
                                'EPSG:32750' => 'WGS 84 / UTM zone 50S (EPSG:32750)',
                                'EPSG:32751' => 'WGS 84 / UTM zone 51S (EPSG:32751)',
                                'EPSG:32649' => 'WGS 84 / UTM zone 49N (EPSG:32649)',
                                'EPSG:32650' => 'WGS 84 / UTM zone 50N (EPSG:32650)',
                                'EPSG:32651' => 'WGS 84 / UTM zone 51N (EPSG:32651)',
                            ];
                            if (!array_key_exists($defaultEpsg, $crsOptions)) {
                                $crsOptions[$defaultEpsg] = "WGS 84 / {$defaultLabel} ({$defaultEpsg})";
                            }
                        @endphp
                        <select id="sourceEpsg" name="sourceEpsg"
                            class="w-full rounded-xl border border-border bg-input px-4 py-2.5 text-sm transition-all focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10 text-foreground">
                            <option value="">-- Select CRS --</option>
                            @foreach ($crsOptions as $code => $name)
                                <option value="{{ $code }}">
                                    {{ $name }}{{ $code === $defaultEpsg ? ' (Project Default)' : '' }}
                                </option>
                            @endforeach






                        </select>
                        <p class="text-[10px] text-foreground/50">Required for .STR, .DWG, and .DXF files to ensure correct reprojection.</p>
                    </div>

                    <!-- File Selection -->
                    <div id="fileSelectionContainer">
                        <!-- Standard Single File Selection -->
                        <div id="singleFileUploadSection">
                            <x-input-label for="file" :value="__('File (Required)')" icon="ri-file-upload-line" />
                            <div id="modalDropZone"
                                class="group flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border py-10 text-center transition-all hover:border-primary/50 hover:bg-primary/2 cursor-pointer">
                                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-neutral shadow-inner transition-transform group-hover:scale-110">
                                    <i class="ri-file-upload-line text-3xl text-primary"></i>
                                </div>
                                <p class="text-sm font-bold text-foreground">Drag and drop or <span class="text-primary underline">browse files</span></p>
                                <p class="text-[10px] font-medium text-foreground/50 mt-1 uppercase tracking-wider">Max size {{ (int) config('geodata.max_upload_mb') }} MB</p>
                                <p id="supportedFormats" class="text-[9px] font-bold text-primary/70 mt-1 truncate max-w-[80%] uppercase tracking-tighter">Supported: .TIF, .TIFF, .Geotiff
                                </p>
                                <div id="fileNameDisplay" class="mt-4 hidden items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-xs font-bold text-primary">
                                    <i class="ri-file-line"></i>
                                    <span class="truncate max-w-[200px]">filename.zip</span>
                                    <button type="button" id="removeFileBtn" class="hover:text-error transition-colors"><i class="ri-close-line"></i></button>
                                </div>
                                <input type="file" id="modalFileInput" class="hidden">
                            </div>
                        </div>

                        <!-- Terrain Specific Double File Selection -->
                        <div id="terrainFileUploadSection" class="hidden space-y-4">
                            <!-- DEM Field -->
                            <div>
                                <label class="mb-2 block text-xs font-bold text-foreground flex items-center gap-2 uppercase tracking-wider">
                                    <i class="ri-landscape-line text-primary"></i>
                                    DEM File (Required)
                                </label>
                                <div id="demDropZone"
                                    class="group flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border py-6 text-center transition-all hover:border-primary/50 hover:bg-primary/2 cursor-pointer">
                                    <i class="ri-file-upload-line text-2xl text-primary mb-1"></i>
                                    <p class="text-[11px] font-bold text-foreground">Click or drop DEM file</p>
                                    <p class="text-[9px] text-foreground/50">.TIF, .TIFF, .GEOTIFF</p>
                                    <div id="demNameDisplay" class="mt-2 hidden items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[10px] font-bold text-primary">
                                        <i class="ri-file-line"></i>
                                        <span class="truncate max-w-[150px]">dem.tif</span>
                                        <button type="button" id="removeDemBtn" class="hover:text-error transition-colors"><i class="ri-close-line"></i></button>
                                    </div>
                                    <input type="file" id="demFileInput" class="hidden" accept=".tif,.tiff,.geotiff">
                                </div>
                            </div>

                            <!-- Orthophoto Field -->
                            <div>
                                <label class="mb-2 block text-xs font-bold text-foreground flex items-center gap-2 uppercase tracking-wider">
                                    <i class="ri-image-line text-primary"></i>
                                    Orthophoto (Optional)
                                </label>
                                <div id="orthoDropZone"
                                    class="group flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border py-6 text-center transition-all hover:border-primary/50 hover:bg-primary/2 cursor-pointer">
                                    <i class="ri-file-upload-line text-2xl text-primary mb-1"></i>
                                    <p class="text-[11px] font-bold text-foreground">Click or drop Orthophoto</p>
                                    <p class="text-[9px] text-foreground/50">.TIF, .TIFF, .GEOTIFF</p>
                                    <div id="orthoNameDisplay" class="mt-2 hidden items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[10px] font-bold text-primary">
                                        <i class="ri-file-line"></i>
                                        <span class="truncate max-w-[150px]">ortho.jpg</span>
                                        <button type="button" id="removeOrthoBtn" class="hover:text-error transition-colors"><i class="ri-close-line"></i></button>
                                    </div>
                                    <input type="file" id="orthoFileInput" class="hidden" accept=".tif,.tiff,.geotiff">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <x-button-light type="button" class="flex-1" data-modal-close>Cancel</x-button-light>
                        <x-button-primary type="submit" id="submitUploadBtn" class="flex-1" disabled>Start Upload</x-button-primary>
                    </div>
                </form>
    </div>
</x-modal>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const uploadForm = document.getElementById('uploadForm');
        if (!uploadForm) return; // Exit if not present on this page

        const modalDropZone = document.getElementById('modalDropZone');
        const modalFileInput = document.getElementById('modalFileInput');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const submitUploadBtn = document.getElementById('submitUploadBtn');
        const removeFileBtn = document.getElementById('removeFileBtn');

        const demDropZone = document.getElementById('demDropZone');
        const demFileInput = document.getElementById('demFileInput');
        const demNameDisplay = document.getElementById('demNameDisplay');
        const removeDemBtn = document.getElementById('removeDemBtn');

        const orthoDropZone = document.getElementById('orthoDropZone');
        const orthoFileInput = document.getElementById('orthoFileInput');
        const orthoNameDisplay = document.getElementById('orthoNameDisplay');
        const removeOrthoBtn = document.getElementById('removeOrthoBtn');

        const singleFileUploadSection = document.getElementById('singleFileUploadSection');
        const terrainFileUploadSection = document.getElementById('terrainFileUploadSection');
        const crsSelectionContainer = document.getElementById('crsSelectionContainer');

        let selectedFile = null;
        let selectedDemFile = null;
        let selectedOrthoFile = null;

        const fileTypes = {
            terrain: {
                accept: ".tif,.tiff,.geotiff",
                label: "Supported: .TIF, .TIFF, .GEOTIFF"
            },
            vector: {
                accept: ".str,.zip,.dwg,.dxf,.geojson",
                label: "Supported: .STR, .ZIP (SHP), .DWG, .DXF, .GEOJSON"
            },
            imagery: {
                accept: ".tif,.tiff,.geotiff",
                label: "Supported: .TIF, .TIFF, .GEOTIFF"
            },
            pointcloud: {
                accept: ".las,.laz",
                label: "Supported: .LAS, .LAZ"
            }
        };

        const updateFileInputAccept = () => {
            const selectedType = uploadForm.querySelector('input[name="dataType"]:checked').value;
            const config = fileTypes[selectedType];

            const layerCategorySelectWrapper = document.getElementById('layerCategorySelectWrapper');

            if (selectedType === 'terrain' || selectedType === 'vector') {
                if (layerCategorySelectWrapper) layerCategorySelectWrapper.classList.remove('hidden');
            } else {
                if (layerCategorySelectWrapper) layerCategorySelectWrapper.classList.add('hidden');
            }

            if (selectedType === 'terrain') {
                singleFileUploadSection.classList.add('hidden');
                terrainFileUploadSection.classList.remove('hidden');
                crsSelectionContainer.classList.add('hidden');
            } else {
                singleFileUploadSection.classList.remove('hidden');
                terrainFileUploadSection.classList.add('hidden');
                if (config) {
                    modalFileInput.accept = config.accept;
                    const label = document.getElementById('supportedFormats');
                    if (label) label.textContent = config.label;
                }

                const fileName = selectedFile ? selectedFile.name.toLowerCase() : '';
                const needsCrs = fileName.endsWith('.str') || fileName.endsWith('.dwg') || fileName.endsWith('.dxf');

                if (needsCrs) {
                    crsSelectionContainer.classList.remove('hidden');
                } else {
                    crsSelectionContainer.classList.add('hidden');
                }
            }
            validateSubmitButton();
        };

        const validateSubmitButton = () => {
            const selectedType = uploadForm.querySelector('input[name="dataType"]:checked').value;
            if (selectedType === 'terrain') {
                submitUploadBtn.disabled = !selectedDemFile;
            } else {
                if (!selectedFile) {
                    submitUploadBtn.disabled = true;
                } else {
                    const fn = selectedFile.name.toLowerCase();
                    if (fn.endsWith('.str') || fn.endsWith('.dwg') || fn.endsWith('.dxf')) {
                        submitUploadBtn.disabled = !document.getElementById('sourceEpsg').value;
                    } else {
                        submitUploadBtn.disabled = false;
                    }
                }
            }
        };

        const resetUploadForm = () => {
            selectedFile = null;
            selectedDemFile = null;
            selectedOrthoFile = null;
            modalFileInput.value = '';
            demFileInput.value = '';
            orthoFileInput.value = '';

            const layerNameInput = document.getElementById('layerName');
            const acquisitionDateInput = document.getElementById('acquisitionDate');
            if (layerNameInput) layerNameInput.value = '';
            if (acquisitionDateInput) acquisitionDateInput.value = '';

            fileNameDisplay.classList.add('hidden');
            demNameDisplay.classList.add('hidden');
            orthoNameDisplay.classList.add('hidden');
            crsSelectionContainer.classList.add('hidden');
            const epsgSelect = document.getElementById('sourceEpsg');
            if (epsgSelect) epsgSelect.value = '';

            validateSubmitButton();
        };

        // Listeners for inputs/dropdowns
        const epsgSelect = document.getElementById('sourceEpsg');
        if (epsgSelect) epsgSelect.addEventListener('change', validateSubmitButton);
        fetchCategories();

        uploadForm.querySelectorAll('input[name="dataType"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateFileInputAccept();
                resetUploadForm();
            });
        });

        const uploadModalEl = document.getElementById('uploadModal');
        if (uploadModalEl) {
            uploadModalEl.addEventListener('modal:opened', updateFileInputAccept);
            uploadModalEl.addEventListener('modal:closed', resetUploadForm);
        }

        function fetchCategories() {
            fetch('{{ route('layer-categories.list') }}')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('layerCategorySelect');
                        if (select) {
                            select.innerHTML = data.categories.map(cat =>
                                `<option value="${cat.id}" ${cat.slug === 'other' ? 'selected' : ''}>${window.escapeHtml(cat.name)}</option>`
                            ).join('');
                        }
                    }
                });
        }

        // Helper for dropzone events
        const setupDropZone = (dropZone, fileInput, handler) => {
            dropZone.addEventListener('click', () => fileInput.click());
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-primary', 'bg-primary/5');
            });
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('border-primary', 'bg-primary/5');
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-primary', 'bg-primary/5');
                if (e.dataTransfer.files.length) {
                    handler(e.dataTransfer.files[0]);
                }
            });
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    handler(e.target.files[0]);
                }
            });
        };

        const validateFile = (file, acceptString) => {
            const extension = "." + file.name.split('.').pop().toLowerCase();
            if (file.size < 1 || file.size > {{ (int) config('geodata.max_upload_mb') }} * 1024 * 1024) {
                alert("File is empty or exceeds the {{ (int) config('geodata.max_upload_mb') }} MiB upload limit.");
                return false;
            }
            return acceptString.split(',').includes(extension);
        };

        // Set up standard dropzone
        if (modalDropZone && modalFileInput) {
            setupDropZone(modalDropZone, modalFileInput, (file) => {
                const selectedType = uploadForm.querySelector('input[name="dataType"]:checked').value;
                const config = fileTypes[selectedType];
                if (!validateFile(file, config.accept)) {
                    alert(`Invalid file type. Please upload: ${config.accept}`);
                    return;
                }
                selectedFile = file;
                fileNameDisplay.querySelector('span').textContent = file.name;
                fileNameDisplay.classList.remove('hidden');
                fileNameDisplay.classList.add('flex');

                const fn = file.name.toLowerCase();
                if (fn.endsWith('.str') || fn.endsWith('.dwg') || fn.endsWith('.dxf')) {
                    crsSelectionContainer.classList.remove('hidden');
                } else {
                    crsSelectionContainer.classList.add('hidden');
                }
                validateSubmitButton();
            });
        }

        // Set up DEM dropzone
        if (demDropZone && demFileInput) {
            setupDropZone(demDropZone, demFileInput, (file) => {
                if (!validateFile(file, ".tif,.tiff,.geotiff")) {
                    alert("Invalid DEM file. Please upload: .tif, .tiff, .geotiff");
                    return;
                }
                selectedDemFile = file;
                demNameDisplay.querySelector('span').textContent = file.name;
                demNameDisplay.classList.remove('hidden');
                demNameDisplay.classList.add('flex');
                validateSubmitButton();
            });
        }

        // Set up Orthophoto dropzone
        if (orthoDropZone && orthoFileInput) {
            setupDropZone(orthoDropZone, orthoFileInput, (file) => {
                if (!validateFile(file, ".tif,.tiff,.geotiff")) {
                    alert("Invalid Orthophoto. Please upload: .tif, .tiff, .geotiff");
                    return;
                }
                selectedOrthoFile = file;
                orthoNameDisplay.querySelector('span').textContent = file.name;
                orthoNameDisplay.classList.remove('hidden');
                orthoNameDisplay.classList.add('flex');
                validateSubmitButton();
            });
        }

        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedFile = null;
                fileNameDisplay.classList.add('hidden');
                fileNameDisplay.classList.remove('flex');
                modalFileInput.value = '';
                crsSelectionContainer.classList.add('hidden');
                validateSubmitButton();
            });
        }

        if (removeDemBtn) {
            removeDemBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedDemFile = null;
                demFileInput.value = '';
                demNameDisplay.classList.add('hidden');
                validateSubmitButton();
            });
        }

        if (removeOrthoBtn) {
            removeOrthoBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedOrthoFile = null;
                orthoFileInput.value = '';
                orthoNameDisplay.classList.add('hidden');
                validateSubmitButton();
            });
        }

        // Expose helpers globally
        window.UploadModal = {
            getSelectedFile: () => selectedFile,
            getSelectedDemFile: () => selectedDemFile,
            getSelectedOrthoFile: () => selectedOrthoFile,
            getLayerCategoryId: () => document.getElementById('layerCategorySelect')?.value || null,
            reset: resetUploadForm,
            update: updateFileInputAccept
        };

        // Initial setup
        updateFileInputAccept();
    });
</script>
