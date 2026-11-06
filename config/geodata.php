<?php

$utmZone = (int) env('GIS_UTM_ZONE', 49);
$utmHemi = strtoupper(env('GIS_UTM_HEMISPHERE', 'N')) === 'S' ? 'S' : 'N';
$utmIsSouth = $utmHemi === 'S';
$utmEpsgNum = ($utmIsSouth ? 32700 : 32600) + $utmZone;
$utmCentralMeridian = ($utmZone * 6) - 183;
$utmFalseNorthing = $utmIsSouth ? 10000000.0 : 0.0;

return [
    /*
    |--------------------------------------------------------------------------
    | Geodata Processing Max Upload Size
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum file size (in megabytes) that a user
    | can upload for processing.
    |
    */
    'max_upload_mb' => env('GEODATA_MAX_UPLOAD_MB', 1000),
    'enable_python_color_parser' => (bool) env('GEODATA_ENABLE_PYTHON_COLOR_PARSER', false),

    /*
    |--------------------------------------------------------------------------
    | Docker Compose File
    |--------------------------------------------------------------------------
    |
    | The path to the docker-compose file that contains the conversion tools.
    |
    */
    'docker_compose_file' => env('GEODATA_DOCKER_COMPOSE_FILE', 'docker-compose.tools.yml'),

    /*
    |--------------------------------------------------------------------------
    | Docker Compose Binary/Command
    |--------------------------------------------------------------------------
    |
    | The command used to run docker compose. It can be 'docker compose' or
    | 'docker-compose'. Defaults to 'docker compose'.
    |
    */
    'docker_compose_bin' => env('GEODATA_DOCKER_COMPOSE_BIN', 'docker compose'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | List of file extensions allowed for upload and processing.
    |
    */
    'allowed_extensions' => ['tif', 'tiff', 'geotiff', 'geojson', 'dxf', 'dwg', 'str', 'zip'],

    'upload' => [
        'chunk_bytes' => 2 * 1024 * 1024,
        'session_hours' => (int) env('GEODATA_UPLOAD_SESSION_HOURS', 24),
        'max_active_sessions' => (int) env('GEODATA_UPLOAD_MAX_SESSIONS', 4),
        'max_inflight_mb' => (int) env('GEODATA_UPLOAD_MAX_INFLIGHT_MB', 2000),
        'min_free_disk_mb' => (int) env('GEODATA_MIN_FREE_DISK_MB', 512),
        'zip_max_entries' => 1000,
        'zip_max_uncompressed_mb' => (int) env('GEODATA_ZIP_MAX_UNCOMPRESSED_MB', 2000),
        'zip_max_ratio' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Terrain Configuration
    |--------------------------------------------------------------------------
    |
    */
    'utm' => [
        'zone' => $utmZone,
        'hemisphere' => $utmHemi,
        'epsg' => "EPSG:{$utmEpsgNum}",
        'label' => "UTM Zone {$utmZone}{$utmHemi}",
        'def' => "+proj=utm +zone={$utmZone} ".($utmIsSouth ? '+south ' : '').'+datum=WGS84 +units=m +no_defs +type=crs',
        'prj' => 'PROJCS["WGS_84_UTM_zone_'.$utmZone.$utmHemi.'",'.
            'GEOGCS["GCS_WGS_1984",'.
            'DATUM["D_WGS_1984",'.
            'SPHEROID["WGS_1984",6378137.0,298.257223563]],'.
            'PRIMEM["Greenwich",0.0],'.
            'UNIT["Degree",0.0174532925199433]],'.
            'PROJECTION["Transverse_Mercator"],'.
            'PARAMETER["False_Easting",500000.0],'.
            'PARAMETER["False_Northing",'.number_format($utmFalseNorthing, 1, '.', '').'],'.
            'PARAMETER["Central_Meridian",'.number_format($utmCentralMeridian, 1, '.', '').'],'.
            'PARAMETER["Scale_Factor",0.9996],'.
            'PARAMETER["Latitude_Of_Origin",0.0],'.
            'UNIT["Meter",1.0]]',
    ],

    'terrain' => [
        'max_zoom' => env('GEODATA_TERRAIN_MAX_ZOOM', 15),
        'min_zoom' => env('GEODATA_TERRAIN_MIN_ZOOM', 0),
    ],
];
