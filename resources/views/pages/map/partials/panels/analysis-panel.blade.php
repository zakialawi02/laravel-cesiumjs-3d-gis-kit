@php
    $select = 'w-full rounded-lg border border-border bg-input px-3 py-1.5 text-xs text-foreground/80 focus:border-primary focus:ring-1 focus:ring-primary';
    $numberInput = 'w-full rounded-lg border border-border bg-input py-1.5 pl-3 pr-8 text-xs text-foreground/80 focus:border-primary focus:ring-1 focus:ring-primary';
    $label = 'text-[10px] font-bold uppercase tracking-wider text-foreground/70';
    $toggleTrack = "peer h-5 w-9 rounded-full bg-muted after:absolute after:start-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40";
    $stat = 'flex flex-col gap-0.5 rounded-lg border border-border/40 bg-neutral/40 p-2.5';
    $statLabel = 'text-[10px] font-bold uppercase tracking-wider text-foreground/50';
    $tabs = [
        ['id' => 'cut-fill', 'label' => 'Cut & Fill', 'icon' => null],
        ['id' => 'profile-section', 'label' => 'Profile', 'icon' => 'ri-line-chart-line'],
    ];
@endphp

<!-- Vol/Profile analysis panel (bottom sheet on mobile, floating on desktop) -->
<div id="analysisPanel" class="analysis-panel flex flex-col gap-3 overflow-y-auto bg-neutral/95 p-4 shadow-2xl" role="dialog" aria-labelledby="analysisPanelTitle">
    <div id="analysisNormalContent" class="flex flex-1 flex-col gap-3 overflow-y-auto">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between border-b border-border pb-2">
            <h3 id="analysisPanelTitle" class="flex items-center gap-2 text-sm font-bold text-primary">
                <i class="ri-bar-chart-box-line text-base"></i>
                <span>Terrain Analysis</span>
            </h3>
            <x-button-close id="closeAnalysisBtn" aria-label="Close analysis" />
        </div>

        <!-- Tabs -->
        <div class="flex w-full shrink-0 rounded-lg border border-border/40 bg-input p-0.5" role="tablist" aria-label="Analysis tools">
            @foreach ($tabs as $tab)
                <button type="button" role="tab" id="{{ $tab['id'] }}-tab-btn" aria-controls="{{ $tab['id'] }}-tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-analysis-tab
                    class="inline-flex w-1/2 items-center justify-center gap-x-1.5 rounded-md px-2 py-1 text-[11px] font-bold text-foreground/60 transition-all hover:text-primary aria-selected:bg-neutral aria-selected:text-primary aria-selected:shadow-xs">
                    @if ($tab['icon'])
                        <i class="{{ $tab['icon'] }} text-xs"></i>
                    @else
                        <img src="{{ asset('assets/img/icons/analysis-cut-fill.svg') }}" alt="" class="h-3 w-3 dark:invert">
                    @endif
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="flex-1 overflow-y-auto pr-1 [scrollbar-width:thin]">
            <!-- Cut & Fill -->
            <div id="cut-fill-tab" role="tabpanel" aria-labelledby="cut-fill-tab-btn" class="space-y-4">
                <section>
                    <div class="mb-1 flex items-center gap-1">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">1. Area</span>
                    </div>
                    <button id="cutFillDrawBtn" type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-2 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90">
                        <i class="ri-edit-box-line"></i>
                        <span id="cutFillDrawBtnText">Draw Area</span>
                    </button>
                    <p class="mt-1 text-center text-[10px] italic text-foreground/60">Click to add points, double-click (or Done) to finish.</p>
                </section>

                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">2. Parameters</span>
                    </div>
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <label for="cutFillResolution" class="{{ $label }}">Grid Resolution</label>
                            <select id="cutFillResolution" class="{{ $select }}">
                                <option value="1">1 m (slow)</option>
                                <option value="2">2 m</option>
                                <option value="3" selected>3 m (balanced)</option>
                                <option value="4">4 m</option>
                                <option value="5">5 m</option>
                                <option value="10">10 m (fast)</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="cutFillTargetType" class="{{ $label }}">Target Surface</label>
                            <select id="cutFillTargetType" class="{{ $select }}">
                                <option value="flat" selected>Flat elevation</option>
                                <option value="boundary">Polygon boundary (vertex heights)</option>
                                <option value="terrain">Another terrain layer</option>
                            </select>
                        </div>

                        <div id="cutFillFlatGroup" class="flex flex-col gap-1">
                            <label for="cutFillTargetElevation" class="{{ $label }}">Target Elevation</label>
                            <div class="relative">
                                <input type="text" inputmode="decimal" id="cutFillTargetElevation" value="0.0" class="{{ $numberInput }}"
                                    oninput="this.value = this.value.replace(/[^0-9.,\-]/g, '');">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-foreground/40">m</span>
                            </div>
                        </div>

                        <div id="cutFillBoundaryOffsetGroup" class="flex hidden flex-col gap-1">
                            <label for="cutFillBoundaryOffset" class="{{ $label }}">Boundary Height Offset</label>
                            <div class="relative">
                                <input type="text" inputmode="decimal" id="cutFillBoundaryOffset" value="0.0" class="{{ $numberInput }}"
                                    oninput="this.value = this.value.replace(/[^0-9.,\-]/g, '');">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-foreground/40">m</span>
                            </div>
                            <p class="text-[9px] text-foreground/50">Added to the boundary vertex heights (+/- meters).</p>
                        </div>

                        <div id="cutFillTerrainGroup" class="flex hidden flex-col gap-1">
                            <label for="cutFillCompareTerrain" class="{{ $label }}">Comparison Terrain</label>
                            <div class="relative flex items-center">
                                <select id="cutFillCompareTerrain" class="{{ $select }}">
                                    <option value="">Loading terrains...</option>
                                </select>
                                <div id="cutFillCompareTerrainSpinner" class="absolute right-8 top-1/2 hidden -translate-y-1/2 text-primary">
                                    <i class="ri-loader-4-line animate-spin text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex gap-2">
                    <button id="cutFillClearBtn" type="button" class="flex-1 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted">Clear</button>
                    <button id="cutFillCalculateBtn" type="button" disabled
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50">
                        Calculate Volume
                    </button>
                </div>

                <div id="cutFillResultsSection" class="hidden space-y-3 rounded-xl border border-border p-4" aria-live="polite">
                    <div class="mb-1 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-primary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">Results</span>
                    </div>
                    <dl class="space-y-2 text-xs">
                        <div class="flex justify-between border-b border-border/50 pb-1.5"><dt class="text-foreground/60">Planar area (2D)</dt><dd id="cutFillResultPlanarArea" class="font-semibold text-foreground">-</dd></div>
                        <div class="flex hidden justify-between border-b border-border/50 pb-1.5"><dt class="text-foreground/60">Surface area (3D)</dt><dd id="cutFillResultSurfaceArea" class="font-semibold text-foreground">-</dd></div>
                        <div class="flex justify-between border-b border-border/50 pb-1.5"><dt class="text-foreground/60">Cut volume</dt><dd id="cutFillResultCut" class="font-semibold text-error">-</dd></div>
                        <div class="flex justify-between border-b border-border/50 pb-1.5"><dt class="text-foreground/60">Fill volume</dt><dd id="cutFillResultFill" class="font-semibold text-primary">-</dd></div>
                        <div class="flex justify-between border-b border-border/50 pb-1.5"><dt class="text-foreground/60">Net volume (cut − fill)</dt><dd id="cutFillResultNet" class="font-semibold">-</dd></div>
                        <div class="flex justify-between"><dt class="text-foreground/60">Average depth (cut/fill)</dt><dd id="cutFillResultAvgDepth" class="font-semibold text-foreground">-</dd></div>
                    </dl>
                </div>

                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">Display</span>
                    </div>
                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-neutral p-3">
                            <span class="flex flex-col gap-0.5">
                                <span class="text-xs font-bold text-foreground">Heatmap grid</span>
                                <span class="text-[9px] text-foreground/60">Color grid cells by cut/fill depth</span>
                            </span>
                            <span class="relative inline-flex items-center">
                                <input type="checkbox" id="cutFillToggleHeatmap" class="peer sr-only" checked>
                                <span class="{{ $toggleTrack }}"></span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-neutral p-3">
                            <span class="flex flex-col gap-0.5">
                                <span class="text-xs font-bold text-foreground">Grid points</span>
                                <span class="text-[9px] text-foreground/60">Show the sampled terrain nodes</span>
                            </span>
                            <span class="relative inline-flex items-center">
                                <input type="checkbox" id="cutFillTogglePoints" class="peer sr-only">
                                <span class="{{ $toggleTrack }}"></span>
                            </span>
                        </label>
                    </div>
                </section>
            </div>

            <!-- Elevation profile -->
            <div id="profile-section-tab" role="tabpanel" aria-labelledby="profile-section-tab-btn" class="hidden space-y-4">
                <section>
                    <div class="mb-2 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">1. Profile Line</span>
                    </div>
                    <button id="profileDrawBtn" type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-2 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90">
                        <i class="ri-pencil-ruler-2-line"></i>
                        <span id="profileDrawBtnText">Draw Profile Line</span>
                    </button>
                    <p class="mt-1 text-center text-[10px] italic text-foreground/60">Click to add points, double-click (or Done) to finish.</p>
                </section>

                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">2. Parameters</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex flex-col gap-1">
                            <label for="profileSampleInterval" class="{{ $label }}">Sampling Interval</label>
                            <select id="profileSampleInterval" class="{{ $select }}">
                                <option value="0.5">0.5 m (high detail)</option>
                                <option value="1">1 m</option>
                                <option value="2">2 m</option>
                                <option value="3" selected>3 m (balanced)</option>
                                <option value="5">5 m (fast)</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="profileMode" class="{{ $label }}">Mode</label>
                            <select id="profileMode" class="{{ $select }}">
                                <option value="single">Single (active terrain only)</option>
                                <option value="double">Compare (active + another terrain)</option>
                            </select>
                        </div>
                        <div id="profileTerrainGroup" class="flex hidden flex-col gap-1">
                            <label for="profileCompareTerrain" class="{{ $label }}">Comparison Terrain</label>
                            <div class="relative flex items-center">
                                <select id="profileCompareTerrain" class="{{ $select }}">
                                    <option value="">Loading terrains...</option>
                                </select>
                                <div id="profileCompareTerrainSpinner" class="absolute right-8 top-1/2 hidden -translate-y-1/2 text-primary">
                                    <i class="ri-loader-4-line animate-spin text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="mb-3 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-secondary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">Display</span>
                    </div>
                    <label class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-neutral p-3">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-xs font-bold text-foreground">Elevation color bands</span>
                            <span class="text-[9px] text-foreground/60">Color the terrain by height</span>
                        </span>
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" id="settingElevationBand" class="peer sr-only">
                            <span class="{{ $toggleTrack }}"></span>
                        </span>
                    </label>
                </section>

                <div class="flex gap-2">
                    <button id="profileClearBtn" type="button" class="flex-1 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted">Clear</button>
                    <button id="profileGenerateBtn" type="button" disabled
                        class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50">
                        Generate Profile
                    </button>
                </div>

                <div id="profileResultsSection" class="hidden space-y-3 rounded-xl border border-border p-4" aria-live="polite">
                    <div class="mb-1 flex items-center gap-2">
                        <div class="h-1 w-3 rounded-full bg-primary"></div>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-foreground/70">Elevation Profile</span>
                    </div>
                    <div class="relative w-full overflow-hidden rounded-lg border border-border/40 bg-neutral p-0.5">
                        <canvas id="profileChart" class="h-40 w-full" role="img" aria-label="Elevation profile chart"></canvas>
                    </div>
                    <div id="profileChartLegend" class="flex hidden items-center justify-center gap-4 py-1 text-[10px] font-semibold text-foreground/75">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-1.5 w-3 rounded bg-[#6366f1]"></span>Active terrain</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-1.5 w-3 rounded bg-[#f97316]"></span>Comparison terrain</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Total length</span><span id="profileTotalLength" class="text-sm font-bold text-foreground">-</span></div>
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Average slope</span><span id="profileAvgSlope" class="text-sm font-bold text-secondary">-</span></div>
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Min elevation</span><span id="profileMinElevation" class="text-sm font-bold text-foreground">-</span></div>
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Max elevation</span><span id="profileMaxElevation" class="text-sm font-bold text-primary">-</span></div>
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Elevation gain</span><span id="profileElevationGain" class="text-sm font-bold text-emerald-600 dark:text-emerald-400">-</span></div>
                        <div class="{{ $stat }}"><span class="{{ $statLabel }}">Elevation loss</span><span id="profileElevationLoss" class="text-sm font-bold text-red-600 dark:text-red-400">-</span></div>
                    </div>

                    <div id="profileCompareStatsSection" class="hidden space-y-2 border-t border-border/40 pt-3">
                        <div class="mb-1 flex items-center gap-2">
                            <div class="h-1 w-3 rounded-full bg-secondary"></div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-foreground/70">Comparison Terrain</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Average slope</span><span id="profileCompareAvgSlope" class="text-sm font-bold text-secondary">-</span></div>
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Min elevation</span><span id="profileCompareMinElevation" class="text-sm font-bold text-foreground">-</span></div>
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Max elevation</span><span id="profileCompareMaxElevation" class="text-sm font-bold text-primary">-</span></div>
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Elevation gain</span><span id="profileCompareElevationGain" class="text-sm font-bold text-emerald-600 dark:text-emerald-400">-</span></div>
                            <div class="{{ $stat }} col-span-2"><span class="{{ $statLabel }}">Elevation loss</span><span id="profileCompareElevationLoss" class="text-sm font-bold text-red-600 dark:text-red-400">-</span></div>
                        </div>
                    </div>

                    <div id="profileDeltaStatsSection" class="hidden space-y-2 border-t border-border/40 pt-3">
                        <div class="mb-1 flex items-center gap-2">
                            <div class="h-1 w-3 rounded-full bg-emerald-500"></div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-foreground/70">Difference (active − comparison)</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="{{ $stat }} col-span-2"><span class="{{ $statLabel }}">Average difference</span><span id="profileDeltaAvg" class="text-sm font-bold text-foreground">-</span></div>
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Min difference</span><span id="profileDeltaMin" class="text-sm font-bold text-foreground">-</span></div>
                            <div class="{{ $stat }}"><span class="{{ $statLabel }}">Max difference</span><span id="profileDeltaMax" class="text-sm font-bold text-foreground">-</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
