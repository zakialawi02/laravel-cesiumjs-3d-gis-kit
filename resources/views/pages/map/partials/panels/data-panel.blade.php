<!-- Data Expanded Panel (Upload Assets) -->
<div id="dataPanel" class="expanded-panel flex flex-col bg-neutral shadow-2xl">
    <!-- Header -->
    <div class="flex items-center justify-between px-3 py-2">
        <h2 class="text-xl font-semibold text-primary">Upload Assets</h2>
        <x-button-close id="closeDataBtn" />
    </div>

    <div class="px-5 pb-3 flex-none">
        <!-- Upload Button (Fixed) -->
        <x-button-primary id="openUploadModalBtn" class="w-full text-sm" data-modal-open="upload">
            <i class="ri-add-circle-line"></i>
            <span>Add New Asset</span>
        </x-button-primary>
    </div>

    <div class="flex-1 overflow-y-auto px-3 pb-6">
        <!-- Active Uploads Section (Static Design) -->
        <div id="activeUploadsSection" class="mb-6">
            <div class="mb-3 flex items-center justify-between">
                <span class="text-[11px] font-bold tracking-widest text-foreground/70 uppercase">Active Uploads</span>
            </div>
            <div id="activeUploadsList" class="space-y-3"></div>

            <!-- Empty state: toggled by syncUploadBadge() in index.blade.php -->
            <div id="activeUploadsEmpty">
                <x-empty-state icon="ri-upload-cloud-2-line" iconClass="text-2xl" message="No uploads in progress"
                    subtitle="Click &laquo;Add New Asset&raquo; to upload a DEM, orthophoto or vector data."
                    containerClass="rounded-xl border border-dashed border-border px-3 py-4" />
            </div>
        </div>

        <!-- Recent Upload -->
        <div class="mb-4 flex items-center justify-between">
            <span class="text-[11px] font-bold tracking-widest text-foreground/70 uppercase">Recent
                Upload</span>
            <button type="button" id="refreshRecentUploadsBtn" class="text-xs text-primary hover:text-primary/80 inline-flex items-center gap-1" title="Refresh">
                <i class="ri-refresh-line"></i> Refresh
            </button>
        </div>

        <div id="recentUploadList" class="space-y-2">
            <x-loading-spinner id="recentUploadLoading" message="Loading recent uploads..." />
        </div>
    </div>
</div>
