<!-- Layers Expanded Panel -->
<div id="layersPanel" class="expanded-panel flex flex-col bg-neutral shadow-2xl">
    <!-- Header -->
    <div class="flex items-center justify-between px-3 py-2">
        <h2 class="text-xl font-semibold text-primary">Layer</h2>
        <x-button-close id="closeLayersBtn" />
    </div>

    <!-- Search and Filter -->
    <div class="px-3 pb-2 flex-none flex flex-col gap-2">
        <div class="relative">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-foreground/60 text-sm"></i>
            <input type="text" id="searchLayers" placeholder="Search layers..."
                class="w-full rounded-lg border border-border bg-input py-1.5 pl-8 pr-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative flex items-center flex-1">
                <input type="month" id="filterMonthYearLayers" title="Filter by Month/Year" value=""
                    class="w-full rounded-lg border border-border bg-input py-1.5 px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary text-foreground/80 cursor-pointer">
                <button type="button" id="clearMonthYearLayersFilter" class="absolute right-1 text-foreground/50 hover:text-error hidden cursor-pointer" title="Clear filter">
                    <i class="ri-close-line text-sm leading-none"></i>
                </button>
            </div>
            <button type="button" id="refreshLayersBtn"
                class="flex-none flex items-center justify-center p-1.5 rounded-lg border border-border bg-input text-foreground/70 hover:text-primary hover:bg-muted transition"
                title="Refresh Layers">
                <i class="ri-refresh-line text-sm leading-none"></i>
            </button>
        </div>
    </div>

    <!-- Layers List -->
    <div class="flex-1 overflow-y-auto px-3">
        <!-- All Layers Categories Section -->
        <div id="allLayersList">
            <x-skeleton count="6" id="layersLoading" />
        </div>
    </div>
</div>
