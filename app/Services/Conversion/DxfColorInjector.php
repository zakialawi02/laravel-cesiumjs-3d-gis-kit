<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Post-processes a GDAL-converted GeoJSON from a DXF source to inject
 * the raw ACI (AutoCAD Color Index) color codes that GDAL/ogr2ogr does not preserve.
 *
 * Reads the original DXF ENTITIES section, maps EntityHandle → ACI color,
 * then patches each GeoJSON feature with a `RawColor` hex property.
 */
class DxfColorInjector
{
    /**
     * Standard AutoCAD Color Index (ACI) to CSS hex mapping.
     * Covers the 7 base colors plus commonly used extended ACI codes.
     */
    private const ACI_TO_HEX = [
        0 => '#000000',
        1 => '#FF0000', // Red
        2 => '#FFFF00', // Yellow
        3 => '#00FF00', // Green
        4 => '#00FFFF', // Cyan
        5 => '#0000FF', // Blue
        6 => '#FF00FF', // Magenta
        7 => '#FFFFFF', // White/Black
        8 => '#414141', // Dark Gray
        9 => '#808080', // Light Gray
        10 => '#FF0000',
        11 => '#FFAAAA',
        12 => '#BD0000',
        13 => '#BD7E7E',
        14 => '#810000',
        15 => '#815656',
        16 => '#680000',
        17 => '#684545',
        18 => '#4F0000',
        19 => '#4F3535',
        20 => '#FF3F00',
        21 => '#FFBFAA',
        22 => '#BD2E00',
        23 => '#BD8D7E',
        24 => '#811F00',
        25 => '#816056',
        26 => '#681900',
        27 => '#684E45',
        28 => '#4F1300',
        29 => '#4F3B35',
        30 => '#FF7F00',
        31 => '#FFD4AA',
        32 => '#BD5E00',
        33 => '#BD9D7E',
        34 => '#814000',
        35 => '#816B56',
        36 => '#683400',
        37 => '#685645',
        38 => '#4F2700',
        39 => '#4F4235',
        40 => '#FFBF00',
        41 => '#FFEAAA',
        42 => '#BD8D00',
        43 => '#BDAD7E',
        44 => '#816000',
        45 => '#817656',
        46 => '#684E00',
        47 => '#685F45',
        48 => '#4F3B00',
        49 => '#4F4935',
        50 => '#FFFF00',
        51 => '#FFFFAA',
        52 => '#BDBD00',
        53 => '#BDBD7E',
        54 => '#818100',
        55 => '#818156',
        56 => '#686800',
        57 => '#686845',
        58 => '#4F4F00',
        59 => '#4F4F35',
        60 => '#BFFF00',
        61 => '#EAFFAA',
        62 => '#8DBD00',
        63 => '#ADBD7E',
        64 => '#608100',
        65 => '#768156',
        66 => '#4E6800',
        67 => '#5F6845',
        68 => '#3B4F00',
        69 => '#494F35',
        70 => '#7FFF00',
        71 => '#D4FFAA',
        72 => '#5EBD00',
        73 => '#9DBD7E',
        74 => '#408100',
        75 => '#6B8156',
        76 => '#346800',
        77 => '#566845',
        78 => '#274F00',
        79 => '#424F35',
        80 => '#3FFF00',
        81 => '#BFFFAA',
        82 => '#2EBD00',
        83 => '#8DBD7E',
        84 => '#1F8100',
        85 => '#608156',
        86 => '#196800',
        87 => '#4E6845',
        88 => '#134F00',
        89 => '#3B4F35',
        90 => '#00FF00',
        91 => '#AAFFAA',
        92 => '#00BD00',
        93 => '#7EBD7E',
        94 => '#008100',
        95 => '#568156',
        96 => '#006800',
        97 => '#456845',
        98 => '#004F00',
        99 => '#354F35',
        100 => '#00FF3F',
        101 => '#AAFFBF',
        102 => '#00BD2E',
        103 => '#7EBD8D',
        104 => '#00811F',
        105 => '#568160',
        106 => '#006819',
        107 => '#45684E',
        108 => '#004F13',
        109 => '#354F3B',
        110 => '#00FF7F',
        111 => '#AAFFD4',
        112 => '#00BD5E',
        113 => '#7EBD9D',
        114 => '#008140',
        115 => '#56816B',
        116 => '#006834',
        117 => '#456856',
        118 => '#004F27',
        119 => '#354F42',
        120 => '#00FFBF',
        121 => '#AAFFEA',
        122 => '#00BD8D',
        123 => '#7EBDAD',
        124 => '#008160',
        125 => '#568176',
        126 => '#00684E',
        127 => '#45685F',
        128 => '#004F3B',
        129 => '#354F49',
        130 => '#00FFFF',
        131 => '#AAFFFF',
        132 => '#00BDBD',
        133 => '#7EBDBD',
        134 => '#008181',
        135 => '#568181',
        136 => '#006868',
        137 => '#456868',
        138 => '#004F4F',
        139 => '#354F4F',
        140 => '#00BFFF',
        141 => '#AAEAFF',
        142 => '#008DBD',
        143 => '#7EADBD',
        144 => '#006081',
        145 => '#567681',
        146 => '#004E68',
        147 => '#455F68',
        148 => '#003B4F',
        149 => '#35494F',
        150 => '#007FFF',
        151 => '#AAD4FF',
        152 => '#005EBD',
        153 => '#7E9DBD',
        154 => '#004081',
        155 => '#566B81',
        156 => '#003468',
        157 => '#455668',
        158 => '#00274F',
        159 => '#35424F',
        160 => '#003FFF',
        161 => '#AABFFF',
        162 => '#002EBD',
        163 => '#7E8DBD',
        164 => '#001F81',
        165 => '#566081',
        166 => '#001968',
        167 => '#454E68',
        168 => '#00134F',
        169 => '#353B4F',
        170 => '#0000FF',
        171 => '#AAAAFF',
        172 => '#0000BD',
        173 => '#7E7EBD',
        174 => '#000081',
        175 => '#565681',
        176 => '#000068',
        177 => '#454568',
        178 => '#00004F',
        179 => '#35354F',
        180 => '#3F00FF',
        181 => '#BFAAFF',
        182 => '#2E00BD',
        183 => '#8D7EBD',
        184 => '#1F0081',
        185 => '#605681',
        186 => '#190068',
        187 => '#4E4568',
        188 => '#13004F',
        189 => '#3B354F',
        190 => '#7F00FF',
        191 => '#D4AAFF',
        192 => '#5E00BD',
        193 => '#9D7EBD',
        194 => '#400081',
        195 => '#6B5681',
        196 => '#340068',
        197 => '#564568',
        198 => '#27004F',
        199 => '#42354F',
        200 => '#BF00FF',
        201 => '#EAAAFF',
        202 => '#8D00BD',
        203 => '#AD7EBD',
        204 => '#600081',
        205 => '#765681',
        206 => '#4E0068',
        207 => '#5F4568',
        208 => '#3B004F',
        209 => '#49354F',
        210 => '#FF00FF',
        211 => '#FFAAFF',
        212 => '#BD00BD',
        213 => '#BD7EBD',
        214 => '#810081',
        215 => '#815681',
        216 => '#680068',
        217 => '#684568',
        218 => '#4F004F',
        219 => '#4F354F',
        220 => '#FF00BF',
        221 => '#FFAAEA',
        222 => '#BD008D',
        223 => '#BD7EAD',
        224 => '#810060',
        225 => '#815676',
        226 => '#68004E',
        227 => '#68455F',
        228 => '#4F003B',
        229 => '#4F3549',
        230 => '#FF007F',
        231 => '#FFAAD4',
        232 => '#BD005E',
        233 => '#BD7E9D',
        234 => '#810040',
        235 => '#81566B',
        236 => '#680034',
        237 => '#684556',
        238 => '#4F0027',
        239 => '#4F3542',
        240 => '#FF003F',
        241 => '#FFAABF',
        242 => '#BD002E',
        243 => '#BD7E8D',
        244 => '#81001F',
        245 => '#815660',
        246 => '#680019',
        247 => '#68454E',
        248 => '#4F0013',
        249 => '#4F353B',
        250 => '#333333',
        251 => '#505050',
        252 => '#696969',
        253 => '#828282',
        254 => '#BEBEBE',
        255 => '#FFFFFF',
    ];

    /**
     * Inject ACI color codes from the original DXF into the GDAL-generated GeoJSON.
     *
     * @param  string  $dxfPath  Absolute path to the original .dxf file
     * @param  string  $geojsonPath  Absolute path to the GDAL-output .geojson file (modified in-place)
     */
    public function inject(string $dxfPath, string $geojsonPath): bool
    {
        if (! file_exists($dxfPath) || ! file_exists($geojsonPath)) {
            Log::channel('vector_conversion')->warning('DxfColorInjector: Input files not found.', [
                'dxf' => $dxfPath,
                'geojson' => $geojsonPath,
            ]);

            return false;
        }

        // Color enhancement is optional. Bound the legacy in-memory parser so
        // large uploads still import geometry without exhausting the PHP worker.
        if (filesize($dxfPath) > 4 * 1024 * 1024 || filesize($geojsonPath) > 8 * 1024 * 1024) {
            Log::channel('vector_conversion')->info('Skipping optional DXF color enhancement for a large dataset.');

            return false;
        }
        // The external Python parser runs only when explicitly enabled by ops.
        $handleColorMap = config('geodata.enable_python_color_parser', false) ? $this->parseDxfEntityColors($dxfPath) : [];
        $usingPhpFallback = false;
        $layerColorMap = [];
        $entityDataMap = [];

        if (empty($handleColorMap)) {
            Log::channel('vector_conversion')->warning('DxfColorInjector: Python parsing failed or returned empty. Falling back to PHP parsing.');
            $usingPhpFallback = true;

            // Step 1.1: Parse layer color definitions from TABLES section
            $layerColorMap = $this->parseDxfLayerColors($dxfPath);
            Log::channel('vector_conversion')->info('DxfColorInjector: Parsed '.count($layerColorMap).' layer color definitions (PHP fallback).');

            // Step 1.2: Parse entity data (handle, color, true color, layer) from ENTITIES section
            $entityDataMap = $this->parseDxfEntityData($dxfPath);

            if (empty($entityDataMap)) {
                Log::channel('vector_conversion')->info('DxfColorInjector: No entities found in DXF via PHP fallback, skipping injection.');

                return false;
            }

            Log::channel('vector_conversion')->info('DxfColorInjector: Parsed '.count($entityDataMap).' entity data mappings from DXF (PHP fallback).');
        } else {
            Log::channel('vector_conversion')->info('DxfColorInjector: Parsed '.count($handleColorMap).' entity color mappings from DXF using Python.');
        }

        // Step 2: Read GeoJSON and inject RawColor property
        $geojsonContent = file_get_contents($geojsonPath);
        $data = json_decode($geojsonContent, true);

        if (! $data || ! isset($data['features'])) {
            Log::channel('vector_conversion')->warning('DxfColorInjector: Invalid GeoJSON structure.');

            return false;
        }

        $injectedCount = 0;

        foreach ($data['features'] as &$feature) {
            $props = $feature['properties'] ?? [];
            $entityHandle = $props['EntityHandle'] ?? null;

            if ($entityHandle === null) {
                continue;
            }

            // Normalize handle to uppercase for consistent matching
            $handleKey = strtoupper(trim($entityHandle));

            if ($usingPhpFallback) {
                if (! isset($entityDataMap[$handleKey])) {
                    continue;
                }

                $entityData = $entityDataMap[$handleKey];
                $hexColor = $this->resolveEntityColor($entityData, $layerColorMap);

                if ($hexColor !== null) {
                    $feature['properties']['RawColor'] = $hexColor;
                    $feature['properties']['AciColor'] = $this->resolveEntityAci($entityData, $layerColorMap);
                    $injectedCount++;
                }
            } else {
                if (isset($handleColorMap[$handleKey])) {
                    $hexColor = $handleColorMap[$handleKey];

                    $feature['properties']['RawColor'] = $hexColor;
                    $feature['properties']['AciColor'] = null; // AciColor is deprecated in favor of RawColor
                    $injectedCount++;
                }
            }
        }
        unset($feature);

        // Step 3: Write modified GeoJSON back
        $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_THROW_ON_ERROR')) {
            $jsonOptions |= JSON_THROW_ON_ERROR;
        }

        if (file_put_contents($geojsonPath, json_encode($data, $jsonOptions)) === false) {
            Log::channel('vector_conversion')->error("DxfColorInjector: Failed to write modified GeoJSON to $geojsonPath");

            return false;
        }

        Log::channel('vector_conversion')->info("DxfColorInjector: Injected color for {$injectedCount}/".count($data['features']).' features.');

        return true;
    }

    /**
     * Parse the DXF file using a Python script (ezdxf) to extract standard colors.
     *
     * @return array<string, string> EntityHandle (uppercase) => Hex color code (e.g. "#FF0000")
     */
    private function parseDxfEntityColors(string $dxfPath): array
    {
        $pythonPath = base_path('.venv/Scripts/python.exe'); // Windows venv
        if (! file_exists($pythonPath)) {
            $pythonPath = base_path('.venv/bin/python'); // Linux/macOS venv
            if (! file_exists($pythonPath)) {
                // Fallback to system python
                // On Linux/macOS, 'python3' is standard for Python 3.x
                if (PHP_OS_FAMILY === 'Windows') {
                    $pythonPath = 'python';
                } else {
                    $pythonPath = 'python3';
                }
            }
        }

        $scriptPath = __DIR__.'/parse_dxf_colors.py';

        Log::channel('vector_conversion')->info("DxfColorInjector executing Python script: $pythonPath $scriptPath $dxfPath");

        $result = Process::timeout(120)->run([$pythonPath, $scriptPath, $dxfPath]);

        if (! $result->successful()) {
            Log::channel('vector_conversion')->warning('DxfColorInjector: Python script returned error code '.$result->exitCode().'. '.
                "Please ensure Python 3 is installed and 'ezdxf' package is installed in your environment.", [
                    'command' => "$pythonPath $scriptPath $dxfPath",
                    'output' => $result->output()."\n".$result->errorOutput(),
                ]);

            return [];
        }

        $jsonStr = $result->output();
        $map = json_decode($jsonStr, true);

        if (! $map) {
            Log::channel('vector_conversion')->warning('DxfColorInjector: Failed to decode JSON from Python output', [
                'output' => $jsonStr,
            ]);

            return [];
        }

        return $map;
    }

    /**
     * Get the hex color for a given ACI color code.
     * Can be used externally for consistent color lookups.
     */
    public static function aciToHex(int $aciColor): ?string
    {
        return self::ACI_TO_HEX[$aciColor] ?? null;
    }

    /**
     * Resolve the final display color for a single entity using the DXF color priority chain.
     *
     * Priority order:
     *   1. Entity true color (group 420) → direct RGB hex
     *   2. Entity ACI color (group 62, 1-255) → ACI_TO_HEX lookup
     *   3. BYLAYER (no group 62 or group 62 = 256) → layer's color
     *   4. BYBLOCK (group 62 = 0) → layer's color as fallback
     *   5. Default white (#FFFFFF)
     *
     * @param  array  $entityData  Entity data with keys: color, trueColor, layer, layerColor
     * @param  array<string, array>  $layerColorMap  Layer name → [aci => int, trueColor => int|null]
     * @return string|null Hex color string (e.g. '#FF0000') or null
     */
    private function resolveEntityColor(array $entityData, array $layerColorMap): ?string
    {
        // Priority 1: Entity true color (group 420) — 24-bit RGB integer
        if (isset($entityData['trueColor'])) {
            return $this->trueColorToHex((int) $entityData['trueColor']);
        }

        $aciColor = $entityData['color'] ?? null;

        // Priority 2: Explicit entity ACI color (1-255)
        if ($aciColor !== null && $aciColor >= 1 && $aciColor <= 255) {
            return self::ACI_TO_HEX[$aciColor] ?? '#FFFFFF';
        }

        // Priority 3 & 4: BYLAYER (absent or 256) / BYBLOCK (0) → resolve from layer
        $layerName = $entityData['layer'] ?? '0';
        $layerData = $layerColorMap[$layerName] ?? null;

        if ($layerData !== null) {
            // Layer true color takes priority over layer ACI
            if (isset($layerData['trueColor'])) {
                return $this->trueColorToHex((int) $layerData['trueColor']);
            }

            if (isset($layerData['aci'])) {
                $layerAci = abs((int) $layerData['aci']); // abs() handles frozen/off layers (negative ACI)

                return self::ACI_TO_HEX[$layerAci] ?? '#FFFFFF';
            }
        }

        // Priority 5: Default
        return '#FFFFFF';
    }

    /**
     * Resolve the effective ACI color code for a single entity.
     *
     * Returns the ACI integer that was used to determine the color:
     *   - Entity's own ACI if explicit (1-255)
     *   - Layer's ACI if BYLAYER
     *   - null if true color was used or no ACI can be determined
     *
     * @param  array<string, array>  $layerColorMap
     */
    private function resolveEntityAci(array $entityData, array $layerColorMap): ?int
    {
        // If entity has true color, ACI is not applicable
        if (isset($entityData['trueColor'])) {
            return null;
        }

        $aciColor = $entityData['color'] ?? null;

        // Explicit entity ACI (1-255)
        if ($aciColor !== null && $aciColor >= 1 && $aciColor <= 255) {
            return $aciColor;
        }

        // BYLAYER / BYBLOCK → resolve from layer
        $layerName = $entityData['layer'] ?? '0';
        $layerData = $layerColorMap[$layerName] ?? null;

        if ($layerData !== null) {
            if (isset($layerData['trueColor'])) {
                return null; // Layer uses true color, not ACI
            }
            if (isset($layerData['aci'])) {
                return abs((int) $layerData['aci']);
            }
        }

        return null;
    }

    /**
     * Convert a DXF true color integer (24-bit 0xRRGGBB) to CSS hex string.
     *
     * @param  int  $trueColor  24-bit integer (e.g. 16711680 = 0xFF0000)
     * @return string Hex color string (e.g. '#FF0000')
     */
    private function trueColorToHex(int $trueColor): string
    {
        return sprintf('#%06X', $trueColor & 0xFFFFFF);
    }

    /**
     * Parse the DXF TABLES section to extract layer name → color mapping.
     *
     * Each LAYER record in the TABLES section contains:
     *   - Group 2: Layer name
     *   - Group 62: ACI color (negative = layer is frozen/off, use abs value)
     *   - Group 420: True color (optional, 24-bit RGB integer)
     *
     * @return array<string, array> LayerName => ['aci' => int, 'trueColor' => int|null]
     */
    private function parseDxfLayerColors(string $dxfPath): array
    {
        $handle = fopen($dxfPath, 'r');
        if (! $handle) {
            return [];
        }

        $map = [];
        $inTables = false;
        $inLayerTable = false;
        $parsingLayerRecord = false;
        $currentLayerName = null;
        $currentLayerAci = null;
        $currentLayerTrueColor = null;

        while (($gcLine = fgets($handle)) !== false) {
            $gc = trim($gcLine);
            $valLine = fgets($handle);
            if ($valLine === false) {
                break;
            }
            $val = trim($valLine);

            // Enter TABLES section
            if ($gc === '2' && $val === 'TABLES') {
                $inTables = true;

                continue;
            }

            // Leave TABLES section when we reach ENTITIES or another SECTION
            if ($inTables && $gc === '2' && ($val === 'ENTITIES' || $val === 'BLOCKS' || $val === 'OBJECTS')) {
                // Save last layer if any
                if ($parsingLayerRecord && $currentLayerName !== null) {
                    $map[$currentLayerName] = [
                        'aci' => $currentLayerAci !== null ? (int) $currentLayerAci : null,
                        'trueColor' => $currentLayerTrueColor !== null ? (int) $currentLayerTrueColor : null,
                    ];
                }
                break;
            }

            if (! $inTables) {
                continue;
            }

            // Detect LAYER table start (group 2 = LAYER after TABLE entity)
            if ($gc === '2' && $val === 'LAYER') {
                $inLayerTable = true;

                continue;
            }

            // End of LAYER table
            if ($inLayerTable && $gc === '0' && $val === 'ENDTAB') {
                // Save last layer if any
                if ($parsingLayerRecord && $currentLayerName !== null) {
                    $map[$currentLayerName] = [
                        'aci' => $currentLayerAci !== null ? (int) $currentLayerAci : null,
                        'trueColor' => $currentLayerTrueColor !== null ? (int) $currentLayerTrueColor : null,
                    ];
                }
                $inLayerTable = false;
                $parsingLayerRecord = false;

                continue;
            }

            if (! $inLayerTable) {
                continue;
            }

            // New LAYER record
            if ($gc === '0' && $val === 'LAYER') {
                // Save previous layer record
                if ($parsingLayerRecord && $currentLayerName !== null) {
                    $map[$currentLayerName] = [
                        'aci' => $currentLayerAci !== null ? (int) $currentLayerAci : null,
                        'trueColor' => $currentLayerTrueColor !== null ? (int) $currentLayerTrueColor : null,
                    ];
                }
                $parsingLayerRecord = true;
                $currentLayerName = null;
                $currentLayerAci = null;
                $currentLayerTrueColor = null;

                continue;
            }

            if (! $parsingLayerRecord) {
                continue;
            }

            // Layer name (group 2)
            if ($gc === '2') {
                $currentLayerName = $val;
            }

            // Layer ACI color (group 62)
            if ($gc === '62') {
                $currentLayerAci = $val;
            }

            // Layer true color (group 420)
            if ($gc === '420') {
                $currentLayerTrueColor = $val;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse the DXF ENTITIES section to extract per-entity data including
     * handle, layer name, ACI color, and true color.
     *
     * Returns a richer map than the old parseDxfEntityColors():
     *   EntityHandle => ['color' => int|null, 'trueColor' => int|null, 'layer' => string|null]
     *
     * The DXF format uses paired lines:
     *   - Group code 0: Entity type
     *   - Group code 5: Entity handle
     *   - Group code 8: Layer name
     *   - Group code 62: ACI color (absent = BYLAYER, 0 = BYBLOCK, 256 = BYLAYER)
     *   - Group code 420: True color (24-bit RGB integer)
     *
     * @return array<string, array> EntityHandle (uppercase) => entity data array
     */
    private function parseDxfEntityData(string $dxfPath): array
    {
        $handle = fopen($dxfPath, 'r');
        if (! $handle) {
            return [];
        }

        $map = [];
        $inEntities = false;
        $inEntity = false;
        $currentHandle = null;
        $currentColor = null;
        $currentTrueColor = null;
        $currentLayer = null;

        // Entity types we care about
        $entityTypes = [
            'LINE', 'LWPOLYLINE', 'POLYLINE', 'POINT', 'CIRCLE', 'ARC',
            'ELLIPSE', 'SPLINE', 'INSERT', 'TEXT', 'MTEXT', 'SOLID',
            'TRACE', 'HATCH', '3DFACE', '3DSOLID', 'ATTRIB', 'ATTDEF',
            'DIMENSION', 'LEADER', 'MLINE', 'REGION', 'BODY', 'SHAPE',
        ];

        while (($groupCodeLine = fgets($handle)) !== false) {
            $groupCode = trim($groupCodeLine);

            // Read the value line
            $valueLine = fgets($handle);
            if ($valueLine === false) {
                break;
            }
            $value = trim($valueLine);

            // Track section
            if ($groupCode === '2' && $value === 'ENTITIES') {
                $inEntities = true;

                continue;
            }

            if (! $inEntities) {
                continue;
            }

            // End of ENTITIES section
            if ($groupCode === '0' && $value === 'ENDSEC') {
                // Save last entity if any
                if ($inEntity && $currentHandle !== null) {
                    $map[strtoupper($currentHandle)] = [
                        'color' => $currentColor !== null ? (int) $currentColor : null,
                        'trueColor' => $currentTrueColor !== null ? (int) $currentTrueColor : null,
                        'layer' => $currentLayer,
                    ];
                }
                break;
            }

            // New entity or sub-entity
            if ($groupCode === '0') {
                // Save previous entity
                if ($inEntity && $currentHandle !== null) {
                    $map[strtoupper($currentHandle)] = [
                        'color' => $currentColor !== null ? (int) $currentColor : null,
                        'trueColor' => $currentTrueColor !== null ? (int) $currentTrueColor : null,
                        'layer' => $currentLayer,
                    ];
                }

                if (in_array($value, $entityTypes, true)) {
                    $inEntity = true;
                    $currentHandle = null;
                    $currentColor = null;
                    $currentTrueColor = null;
                    $currentLayer = null;
                } elseif ($value === 'VERTEX' || $value === 'SEQEND') {
                    // Part of POLYLINE, don't reset
                } else {
                    $inEntity = false;
                    $currentHandle = null;
                    $currentColor = null;
                    $currentTrueColor = null;
                    $currentLayer = null;
                }

                continue;
            }

            if (! $inEntity) {
                continue;
            }

            // Entity handle (group code 5)
            if ($groupCode === '5' && $currentHandle === null) {
                $currentHandle = $value;
            }

            // Layer name (group code 8)
            if ($groupCode === '8' && $currentLayer === null) {
                $currentLayer = $value;
            }

            // ACI color (group code 62) — only capture the first one per entity
            if ($groupCode === '62' && $currentColor === null) {
                $currentColor = $value;
            }

            // True color (group code 420) — 24-bit RGB integer
            if ($groupCode === '420' && $currentTrueColor === null) {
                $currentTrueColor = $value;
            }
        }

        fclose($handle);

        return $map;
    }
}
