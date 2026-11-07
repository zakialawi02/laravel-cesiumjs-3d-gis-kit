@php
    $tabs = [
        ['id' => 'cut-fill', 'label' => __('Cut & fill'), 'icon' => null],
        ['id' => 'profile-section', 'label' => __('Profile'), 'icon' => 'ri-line-chart-line'],
    ];
@endphp

{{--
    Vol/Profile analysis: top-right card on md+, bottom sheet on phones.
    cesium-map.js toggles .active, hides the panel while drawing and fills every id below.
--}}
<div id="analysisPanel" class="map-analysis map-sheet" role="dialog" aria-labelledby="analysisPanelTitle">
    <span class="map-sheet-grabber" aria-hidden="true"></span>

    <div id="analysisNormalContent" class="flex min-h-0 flex-1 flex-col">
        <header class="map-panel-head">
            <div class="min-w-0 flex-1">
                <h3 id="analysisPanelTitle" class="map-panel-title">{{ __('Terrain analysis') }}</h3>
                <p class="map-panel-sub">{{ __('Runs on the active terrain layer.') }}</p>
            </div>
            <x-button-close id="closeAnalysisBtn" aria-label="{{ __('Close analysis') }}" />
        </header>

        <div class="flex-none px-3 pb-3">
            <div class="map-segmented" role="tablist" aria-label="{{ __('Analysis tools') }}">
                @foreach ($tabs as $tab)
                    <button type="button" role="tab" id="{{ $tab['id'] }}-tab-btn" aria-controls="{{ $tab['id'] }}-tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-analysis-tab
                        class="map-segment">
                        @if ($tab['icon'])
                            <i class="{{ $tab['icon'] }} text-sm" aria-hidden="true"></i>
                        @else
                            <span class="map-glyph size-3.5" style="--glyph: url('{{ asset('assets/img/icons/analysis-cut-fill.svg') }}')" aria-hidden="true"></span>
                        @endif
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="map-panel-body px-3.5">
            {{-- Cut & fill --}}
            <div id="cut-fill-tab" role="tabpanel" aria-labelledby="cut-fill-tab-btn" class="flex flex-col gap-5">
                <section class="map-step">
                    <h4 class="map-step-title"><span class="map-step-num">1</span>{{ __('Draw the area') }}</h4>
                    <button id="cutFillDrawBtn" type="button" class="map-btn w-full" data-map-action data-variant="primary">
                        <i class="ri-edit-box-line" aria-hidden="true"></i>
                        <span id="cutFillDrawBtnText">{{ __('Draw area') }}</span>
                    </button>
                    <p class="map-help-text">{{ __('Click to add points. Double-click or press Done to close the polygon.') }}</p>
                </section>

                <section class="map-step">
                    <h4 class="map-step-title"><span class="map-step-num">2</span>{{ __('Set the parameters') }}</h4>

                    <div class="map-form-row">
                        <label for="cutFillResolution" class="map-form-label">{{ __('Grid resolution') }}</label>
                        <select id="cutFillResolution" class="map-field">
                            <option value="1">1 m (slow)</option>
                            <option value="2">2 m</option>
                            <option value="3" selected>3 m (balanced)</option>
                            <option value="4">4 m</option>
                            <option value="5">5 m</option>
                            <option value="10">10 m (fast)</option>
                        </select>
                    </div>

                    <div class="map-form-row">
                        <label for="cutFillTargetType" class="map-form-label">{{ __('Target surface') }}</label>
                        <select id="cutFillTargetType" class="map-field">
                            <option value="flat" selected>{{ __('Flat elevation') }}</option>
                            <option value="boundary">{{ __('Polygon boundary (vertex heights)') }}</option>
                            <option value="terrain">{{ __('Another terrain layer') }}</option>
                        </select>
                    </div>

                    <div id="cutFillFlatGroup" class="map-form-row">
                        <label for="cutFillTargetElevation" class="map-form-label">{{ __('Target elevation') }}</label>
                        <div class="relative">
                            <input type="text" inputmode="decimal" id="cutFillTargetElevation" value="0.0" class="map-field pr-9 tabular-nums"
                                oninput="this.value = this.value.replace(/[^0-9.,\-]/g, '');">
                            <span class="map-field-unit">m</span>
                        </div>
                    </div>

                    <div id="cutFillBoundaryOffsetGroup" class="map-form-row hidden">
                        <label for="cutFillBoundaryOffset" class="map-form-label">{{ __('Boundary height offset') }}</label>
                        <div class="relative">
                            <input type="text" inputmode="decimal" id="cutFillBoundaryOffset" value="0.0" class="map-field pr-9 tabular-nums" aria-describedby="cutFillBoundaryOffsetHint"
                                oninput="this.value = this.value.replace(/[^0-9.,\-]/g, '');">
                            <span class="map-field-unit">m</span>
                        </div>
                        <p id="cutFillBoundaryOffsetHint" class="map-help-text">{{ __('Added to the boundary vertex heights (+/- metres).') }}</p>
                    </div>

                    <div id="cutFillTerrainGroup" class="map-form-row hidden">
                        <label for="cutFillCompareTerrain" class="map-form-label">{{ __('Comparison terrain') }}</label>
                        <div class="relative">
                            <select id="cutFillCompareTerrain" class="map-field">
                                <option value="">{{ __('Loading terrains...') }}</option>
                            </select>
                            <span id="cutFillCompareTerrainSpinner" class="pointer-events-none absolute right-9 top-1/2 hidden -translate-y-1/2 text-map-accent-ink">
                                <i class="ri-loader-4-line animate-spin text-sm" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-2 gap-2">
                    <button id="cutFillClearBtn" type="button" class="map-btn map-btn-secondary">{{ __('Clear') }}</button>
                    <button id="cutFillCalculateBtn" type="button" class="map-btn map-btn-primary" disabled>{{ __('Calculate volume') }}</button>
                </div>

                <div id="cutFillResultsSection" class="map-results hidden" aria-live="polite">
                    <h4 class="map-step-title">{{ __('Results') }}</h4>
                    <div class="map-stat-grid">
                        <div class="map-stat map-stat--cut">
                            <span class="map-stat-label">{{ __('Cut volume') }}</span>
                            <span id="cutFillResultCut" class="map-stat-value text-map-danger">-</span>
                        </div>
                        <div class="map-stat map-stat--fill">
                            <span class="map-stat-label">{{ __('Fill volume') }}</span>
                            <span id="cutFillResultFill" class="map-stat-value text-map-accent-ink">-</span>
                        </div>
                    </div>
                    <dl class="map-result-list">
                        <div><dt>{{ __('Net volume (cut − fill)') }}</dt><dd id="cutFillResultNet">-</dd></div>
                        <div><dt>{{ __('Planar area (2D)') }}</dt><dd id="cutFillResultPlanarArea">-</dd></div>
                        <div class="hidden"><dt>{{ __('Surface area (3D)') }}</dt><dd id="cutFillResultSurfaceArea">-</dd></div>
                        <div><dt>{{ __('Average depth (cut/fill)') }}</dt><dd id="cutFillResultAvgDepth">-</dd></div>
                    </dl>
                </div>

                <section class="map-step">
                    <h4 class="map-step-title">{{ __('Display') }}</h4>
                    <label class="map-switch-row">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[0.8125rem] font-semibold text-map-ink">{{ __('Heatmap grid') }}</span>
                            <span class="map-help-text">{{ __('Colour grid cells by cut/fill depth') }}</span>
                        </span>
                        <span class="map-switch">
                            <input type="checkbox" id="cutFillToggleHeatmap" class="peer sr-only" checked>
                            <span class="map-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="map-switch-row">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[0.8125rem] font-semibold text-map-ink">{{ __('Grid points') }}</span>
                            <span class="map-help-text">{{ __('Show the sampled terrain nodes') }}</span>
                        </span>
                        <span class="map-switch">
                            <input type="checkbox" id="cutFillTogglePoints" class="peer sr-only">
                            <span class="map-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                </section>
            </div>

            {{-- Elevation profile --}}
            <div id="profile-section-tab" role="tabpanel" aria-labelledby="profile-section-tab-btn" class="flex hidden flex-col gap-5">
                <section class="map-step">
                    <h4 class="map-step-title"><span class="map-step-num">1</span>{{ __('Draw the profile line') }}</h4>
                    <button id="profileDrawBtn" type="button" class="map-btn w-full" data-map-action data-variant="primary">
                        <i class="ri-pencil-ruler-2-line" aria-hidden="true"></i>
                        <span id="profileDrawBtnText">{{ __('Draw profile line') }}</span>
                    </button>
                    <p class="map-help-text">{{ __('Click to add points. Double-click or press Done to finish the line.') }}</p>
                </section>

                <section class="map-step">
                    <h4 class="map-step-title"><span class="map-step-num">2</span>{{ __('Set the parameters') }}</h4>

                    <div class="map-form-row">
                        <label for="profileSampleInterval" class="map-form-label">{{ __('Sampling interval') }}</label>
                        <select id="profileSampleInterval" class="map-field">
                            <option value="0.5">0.5 m (high detail)</option>
                            <option value="1">1 m</option>
                            <option value="2">2 m</option>
                            <option value="3" selected>3 m (balanced)</option>
                            <option value="5">5 m (fast)</option>
                        </select>
                    </div>

                    <div class="map-form-row">
                        <label for="profileMode" class="map-form-label">{{ __('Mode') }}</label>
                        <select id="profileMode" class="map-field">
                            <option value="single">{{ __('Single (active terrain only)') }}</option>
                            <option value="double">{{ __('Compare (active + another terrain)') }}</option>
                        </select>
                    </div>

                    <div id="profileTerrainGroup" class="map-form-row hidden">
                        <label for="profileCompareTerrain" class="map-form-label">{{ __('Comparison terrain') }}</label>
                        <div class="relative">
                            <select id="profileCompareTerrain" class="map-field">
                                <option value="">{{ __('Loading terrains...') }}</option>
                            </select>
                            <span id="profileCompareTerrainSpinner" class="pointer-events-none absolute right-9 top-1/2 hidden -translate-y-1/2 text-map-accent-ink">
                                <i class="ri-loader-4-line animate-spin text-sm" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>
                </section>

                <section class="map-step">
                    <h4 class="map-step-title">{{ __('Display') }}</h4>
                    <label class="map-switch-row">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-[0.8125rem] font-semibold text-map-ink">{{ __('Elevation colour bands') }}</span>
                            <span class="map-help-text">{{ __('Colour the terrain by height') }}</span>
                        </span>
                        <span class="map-switch">
                            <input type="checkbox" id="settingElevationBand" class="peer sr-only">
                            <span class="map-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                </section>

                <div class="grid grid-cols-2 gap-2">
                    <button id="profileClearBtn" type="button" class="map-btn map-btn-secondary">{{ __('Clear') }}</button>
                    <button id="profileGenerateBtn" type="button" class="map-btn map-btn-primary" disabled>{{ __('Generate profile') }}</button>
                </div>

                <div id="profileResultsSection" class="map-results hidden" aria-live="polite">
                    <h4 class="map-step-title">{{ __('Elevation profile') }}</h4>
                    <div class="overflow-hidden rounded-[0.625rem] border border-map-line bg-map-surface-strong p-1">
                        <canvas id="profileChart" class="h-44 w-full" role="img" aria-label="{{ __('Elevation profile chart') }}"></canvas>
                    </div>
                    <div id="profileChartLegend" class="flex hidden items-center justify-center gap-4 text-[0.6875rem] font-semibold text-map-muted">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-1.5 w-3 rounded-sm bg-[#6366f1]"></span>{{ __('Active terrain') }}</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-1.5 w-3 rounded-sm bg-[#f97316]"></span>{{ __('Comparison terrain') }}</span>
                    </div>
                    <div class="map-stat-grid">
                        <div class="map-stat"><span class="map-stat-label">{{ __('Total length') }}</span><span id="profileTotalLength" class="map-stat-value">-</span></div>
                        <div class="map-stat"><span class="map-stat-label">{{ __('Average slope') }}</span><span id="profileAvgSlope" class="map-stat-value">-</span></div>
                        <div class="map-stat"><span class="map-stat-label">{{ __('Min elevation') }}</span><span id="profileMinElevation" class="map-stat-value">-</span></div>
                        <div class="map-stat"><span class="map-stat-label">{{ __('Max elevation') }}</span><span id="profileMaxElevation" class="map-stat-value text-map-accent-ink">-</span></div>
                        <div class="map-stat"><span class="map-stat-label">{{ __('Elevation gain') }}</span><span id="profileElevationGain" class="map-stat-value text-map-success">-</span></div>
                        <div class="map-stat"><span class="map-stat-label">{{ __('Elevation loss') }}</span><span id="profileElevationLoss" class="map-stat-value text-map-danger">-</span></div>
                    </div>

                    <div id="profileCompareStatsSection" class="flex hidden flex-col gap-2 border-t border-map-line pt-3">
                        <p class="map-label">{{ __('Comparison terrain') }}</p>
                        <div class="map-stat-grid">
                            <div class="map-stat"><span class="map-stat-label">{{ __('Average slope') }}</span><span id="profileCompareAvgSlope" class="map-stat-value">-</span></div>
                            <div class="map-stat"><span class="map-stat-label">{{ __('Min elevation') }}</span><span id="profileCompareMinElevation" class="map-stat-value">-</span></div>
                            <div class="map-stat"><span class="map-stat-label">{{ __('Max elevation') }}</span><span id="profileCompareMaxElevation" class="map-stat-value text-map-accent-ink">-</span></div>
                            <div class="map-stat"><span class="map-stat-label">{{ __('Elevation gain') }}</span><span id="profileCompareElevationGain" class="map-stat-value text-map-success">-</span></div>
                            <div class="map-stat col-span-2"><span class="map-stat-label">{{ __('Elevation loss') }}</span><span id="profileCompareElevationLoss" class="map-stat-value text-map-danger">-</span></div>
                        </div>
                    </div>

                    <div id="profileDeltaStatsSection" class="flex hidden flex-col gap-2 border-t border-map-line pt-3">
                        <p class="map-label">{{ __('Difference (active − comparison)') }}</p>
                        <div class="map-stat-grid">
                            <div class="map-stat col-span-2"><span class="map-stat-label">{{ __('Average difference') }}</span><span id="profileDeltaAvg" class="map-stat-value">-</span></div>
                            <div class="map-stat"><span class="map-stat-label">{{ __('Min difference') }}</span><span id="profileDeltaMin" class="map-stat-value">-</span></div>
                            <div class="map-stat"><span class="map-stat-label">{{ __('Max difference') }}</span><span id="profileDeltaMax" class="map-stat-value">-</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
