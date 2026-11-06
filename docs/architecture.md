# Architecture

This document describes how Laravel CesiumJS 3D GIS Kit is put together: the moving parts, how data flows from an uploaded file to a 3D layer on the globe, and the main design decisions.

- [1. High-level overview](#1-high-level-overview)
- [2. Components](#2-components)
- [3. Data model](#3-data-model)
- [4. Lifecycles](#4-lifecycles)
- [5. Key flows (sequence diagrams)](#5-key-flows-sequence-diagrams)
- [6. Frontend architecture](#6-frontend-architecture)
- [7. Storage layout](#7-storage-layout)
- [8. Security model](#8-security-model)
- [9. Design decisions and limits](#9-design-decisions-and-limits)

## 1. High-level overview

The kit is a single Laravel application with three runtime roles:

1. **Web** (PHP-FPM or `php artisan serve`): renders Blade pages, accepts chunked uploads, and serves terrain tiles, orthophoto tiles and vector GeoJSON.
2. **Queue worker** (`php artisan queue:work`): assembles uploaded chunks and runs the conversion pipelines. It starts short-lived, network-less **Docker containers** (GDAL and Cesium Terrain Builder) for the heavy geoprocessing.
3. **Browser**: a CesiumJS globe driven by vanilla JavaScript. It also runs the measurement and terrain analysis tools entirely client-side.

```mermaid
flowchart LR
    user([Admin / Engineering user])

    subgraph Browser
        ui[Blade pages + vanilla JS]
        cesium[CesiumJS globe<br/>measure & analysis tools]
    end

    subgraph Server["Laravel application"]
        web[Web: controllers<br/>auth, upload API, tile & data API]
        worker[Queue worker<br/>assembly + conversion jobs]
        scheduler[Scheduler<br/>geodata:cleanup-stale]
    end

    subgraph Converters["Docker (network: none)"]
        gdal[GDAL<br/>gdalwarp · ogr2ogr · gdal2tiles]
        ctb[Cesium Terrain Builder<br/>ctb-tile]
    end

    db[(PostgreSQL + PostGIS)]
    disk[(Local storage<br/>private / geodata / public)]

    user --> ui
    ui <--> web
    cesium <--> web
    web <--> db
    web <--> disk
    worker <--> db
    worker <--> disk
    worker -->|docker compose run| gdal
    worker -->|docker compose run| ctb
    gdal <--> disk
    ctb <--> disk
    scheduler --> db
    scheduler --> disk
```

What a user can do:

| Role | Map | Upload data | Manage layers & categories (dashboard) |
|---|---|---|---|
| **Admin** | View layers, measure, analyse | Yes | Yes |
| **Engineering** | View published layers, measure, analyse | No | No |

## 2. Components

### 2.1 Backend (Laravel)

| Layer | Location | Responsibility |
|---|---|---|
| Routes | `routes/web.php`, `routes/auth.php`, `routes/console.php` | All routes sit behind `auth`; uploads, deletion and the data-management dashboard additionally require `role:admin`. |
| Controllers | `app/Http/Controllers` | `HomeController` (map page), `GeodataUploadController` (chunked upload API), `GeodataLayerController` (layer lists, tile and GeoJSON serving), `GeodataController` (layer deletion), `IconController` (category icons behind auth), `Dashboard\*` (layer and category management), `ProfileController`, `Auth\*`. |
| Form requests | `app/Http/Requests` | Validation and authorization for login, profile and dashboard forms. |
| Middleware | `app/Http/Middleware/RoleCheck.php` | `role:admin,engineering` style route guards. |
| Upload services | `app/Services/Upload` | `UploadSessionService` (quotas, sessions, chunk bookkeeping), `UploadFileValidator`, `FileAssemblyService` (stitch chunks, checksum), `ShapefileArchive` (safe ZIP extraction), `GeoJsonFeatureReader` (streaming GeoJSON reader), `UploadCompletionService` (creates source file, job and layer records). |
| Conversion services | `app/Services/Conversion` | `TerrainConversionService` (gdalwarp → ctb-tile → gdal2tiles), `GdalService` (`ogr2ogr` wrapper), `ShpImportService`, `DwgImportService`, `SurpacStrParserService`, `DxfColorInjector`. |
| Database services | `app/Services/Database` | `DynamicTableService` (one PostGIS table per vector layer), `SpatialImportService` (bulk feature import). |
| Jobs | `app/Jobs` | `AssembleGeodataUpload` (queue `upload`), `ProcessGeotiffTerrain` and `ProcessVectorLayer` (extend `BaseConversionJob`), `CleanupLayerFilesJob` (queue `default`). |
| Console | `app/Console/Commands/CleanupStaleUploads.php` | Removes expired upload sessions and their chunks; scheduled every 30 minutes. |
| Models | `app/Models` | `User`, `Layer`, `LayerCategory`, `ConversionJob`, `ConversionLog`, `SourceFile`, `TerrainDetail`, `VectorDetail`, `UploadSession`. |
| Enums | `app/Enums` | `UserRole`, `LayerType`, `LayerStatus`, `JobStatus`, `SourceFileStatus`, `SourceFileType`, … |

### 2.2 Conversion toolchain

The worker never runs GDAL or CTB on the host. It calls `docker compose -f docker-compose.tools.yml run --rm <service> <command>`. Both services share the same hardening anchor:

- `network_mode: none`, `read_only: true`, `cap_drop: [ALL]`, `no-new-privileges`
- memory, CPU and PID limits (`GEODATA_CONVERTER_MEMORY`, `GEODATA_CONVERTER_CPUS`)
- they run as `GEODATA_CONVERTER_USER` so output files are owned by the PHP user
- only `storage/app/geodata`, `storage/app/public/terrain-tiles` and `storage/app/public/xyz-tiles` are mounted

| Service | Image | Used for |
|---|---|---|
| `gdal` | `ghcr.io/osgeo/gdal:ubuntu-full-latest` | `gdalwarp` (reproject DEM/orthophoto to EPSG:4326), `gdal2tiles.py` (orthophoto XYZ tiles), `ogr2ogr` (vector formats → WGS84 GeoJSON) |
| `ctb` | `tumgis/ctb-quantized-mesh:latest` | `ctb-tile` (quantized-mesh terrain tiles and `layer.json`) |

### 2.3 Frontend

Server-rendered Blade with Tailwind CSS 4 and **vanilla JavaScript** (no jQuery, Alpine.js or Preline). Vite bundles three entries: `resources/css/app.css`, `resources/js/app.js` (all pages) and `resources/js/map.js` (map page). See [section 6](#6-frontend-architecture).

## 3. Data model

```mermaid
erDiagram
    USERS ||--o{ LAYERS : uploads
    USERS ||--o{ UPLOAD_SESSIONS : owns
    USERS ||--o{ SESSIONS : "logged in as"
    LAYER_CATEGORIES ||--o{ LAYERS : groups
    CONVERSION_JOBS ||--|| LAYERS : produces
    CONVERSION_JOBS ||--o{ CONVERSION_LOGS : records
    SOURCE_FILES ||--o{ CONVERSION_JOBS : "source / ortho"
    UPLOAD_SESSIONS }o--o| CONVERSION_JOBS : "completed into"
    UPLOAD_SESSIONS }o--o| SOURCE_FILES : "assembled into"
    LAYERS ||--o| TERRAIN_DETAILS : "terrain"
    LAYERS ||--o| VECTOR_DETAILS : "vector"
    VECTOR_DETAILS ||--|| VECTOR_TABLE : "physical_table_name"

    USERS {
        uuid id PK
        string username UK
        string email UK
        string role "admin | engineering"
    }
    LAYER_CATEGORIES {
        ulid id PK
        string name
        string slug UK "other = protected default"
        string color
        string icon_path
        int sort_order
    }
    LAYERS {
        ulid id PK
        uuid user_id FK
        ulid job_id FK
        ulid layer_category_id FK
        string layer_type "terrain | vector"
        string status
        timestamp published_at
    }
    CONVERSION_JOBS {
        ulid id PK
        ulid source_file_id FK
        ulid ortho_file_id FK
        string status "queued | processing | done | failed"
        text error_message
    }
    CONVERSION_LOGS {
        ulid id PK
        ulid job_id FK
        int exit_code
        text stdout_log
        text stderr_log
    }
    SOURCE_FILES {
        ulid id PK
        string original_name
        text storage_path "geodata disk"
        string checksum_sha256
        string status
    }
    UPLOAD_SESSIONS {
        uuid id PK
        uuid user_id FK
        json chunks
        string status
        timestamp expires_at
    }
    TERRAIN_DETAILS {
        ulid id PK
        ulid layer_id FK
        text tileset_path
        text ortho_tiles_path
        json native_bounding_box
        date acquisition_date
    }
    VECTOR_DETAILS {
        ulid id PK
        ulid layer_id FK
        string source_epsg
        string geometry_type
        int feature_count
        string physical_table_name
        text output_path
    }
    VECTOR_TABLE {
        bigserial id PK
        geometry geometry "GeometryZ, EPSG:4326, GIST index"
        mixed attribute_columns "one column per GeoJSON property"
    }
```

Notes:

- Every vector layer gets its **own PostGIS table** named `vector_<layer ulid>`, with one sanitized column per GeoJSON property. Table and column names are generated server-side and validated against `^vector_[a-z0-9_]{1,60}$` before any DDL.
- `layers.job_id` is unique: one conversion job produces exactly one layer.
- Deleting a category moves its layers to the protected `other` category in the same transaction.

## 4. Lifecycles

### 4.1 Layer status

```mermaid
stateDiagram-v2
    [*] --> processing: upload completed<br/>(UploadCompletionService)
    processing --> published: conversion finalized
    processing --> process_failed: conversion failed after retries
    published --> archived: admin archives (dashboard)
    archived --> published: admin restores (job done)
    published --> [*]: admin deletes
    archived --> [*]: admin deletes
    process_failed --> [*]: admin deletes
```

Only **published** layers are listed on the map and served by the tile and GeoJSON endpoints. Deleting a layer is refused (HTTP 409) while its conversion is still queued or processing, unless the job has been idle for more than three hours.

### 4.2 Conversion job status

```mermaid
stateDiagram-v2
    [*] --> queued: upload completed
    queued --> processing: worker picks AssembleGeodataUpload
    processing --> done: pipeline finished
    processing --> queued: attempt failed, retry (tries = 2)
    processing --> failed: final attempt failed / timeout
    done --> [*]
    failed --> [*]
```

## 5. Key flows (sequence diagrams)

### 5.1 Chunked upload and completion

Uploads are split into 2 MB chunks so large GeoTIFFs survive flaky connections and can resume. Quotas (`GEODATA_UPLOAD_MAX_SESSIONS`, `GEODATA_UPLOAD_MAX_INFLIGHT_MB`, `GEODATA_MIN_FREE_DISK_MB`) and per-user rate limits (`upload-start`, `upload-chunks`, `upload-status`) are enforced server-side.

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant UI as Browser (chunked-upload.js)
    participant API as GeodataUploadController
    participant Sess as UploadSessionService
    participant Done as UploadCompletionService
    participant DB as PostgreSQL
    participant Q as Queue (upload)

    Admin->>UI: choose file(s) + layer name, category, EPSG
    UI->>API: POST /geodata/upload/sessions
    API->>Sess: check quotas, free disk, extension
    Sess->>DB: insert upload_sessions (expires_at)
    API-->>UI: session id + chunk size
    loop every 2 MB chunk
        UI->>API: POST /geodata/upload/chunk
        API->>Sess: validate index/size, store chunk on private disk
        API-->>UI: received chunks
    end
    UI->>API: POST /geodata/upload/assemble-raster | assemble-vector
    API->>Done: complete(data, type, user)
    Done->>DB: BEGIN
    Done->>DB: source_files, conversion_jobs (queued), layers (processing)
    Done->>Q: dispatch AssembleGeodataUpload (after commit)
    Done->>DB: COMMIT
    API-->>UI: job id
    loop until done / failed
        UI->>API: GET /geodata/upload/status/{job}
        API-->>UI: status (+ layer details when done)
    end
```

Completion is **idempotent**: the request parameters are hashed into `upload_sessions.completion_hash`, so a retried "assemble" call returns the same job, while a call with different parameters is rejected with 409.

### 5.2 Terrain conversion (GeoTIFF DEM + optional orthophoto)

```mermaid
sequenceDiagram
    autonumber
    participant W as Worker: AssembleGeodataUpload
    participant FA as FileAssemblyService
    participant T as ProcessGeotiffTerrain
    participant DC as docker compose run
    participant FS as Storage
    participant DB as PostgreSQL

    W->>DB: job → processing
    W->>FA: assemble chunks → geodata/geotiff/<file>, sha256
    W->>T: handle() (same queue reservation)
    T->>DC: gdal: gdalinfo (validate header)
    T->>DC: gdal: gdalwarp → EPSG:4326 (<file>_wgs84.tif)
    T->>DC: ctb: ctb-tile -f Mesh → public/terrain-tiles/<layer>/
    T->>DC: ctb: ctb-tile -l → layer.json
    T->>T: read bounding box for the camera
    opt orthophoto uploaded
        T->>DC: gdal: gdalwarp orthophoto → EPSG:4326
        T->>DC: gdal: gdal2tiles.py --xyz z10–18 → public/xyz-tiles/<layer>_ortho/
    end
    T->>DB: terrain_details (tileset, bbox, ortho zooms)
    T->>DB: layer → published, job → done, conversion_logs
    T->>FS: delete temporary reprojected files
```

### 5.3 Vector conversion (GeoJSON, zipped Shapefile, DXF, DWG, Surpac STR)

```mermaid
sequenceDiagram
    autonumber
    participant V as ProcessVectorLayer
    participant Conv as GdalService / Shp / Dwg / Str services
    participant DC as docker compose run (gdal)
    participant DT as DynamicTableService
    participant SI as SpatialImportService
    participant DB as PostgreSQL + PostGIS

    V->>DB: vector_details record
    alt Surpac .str
        V->>Conv: parse STR → intermediate GeoJSON (in PHP)
        V->>DC: ogr2ogr → WGS84 GeoJSON (source EPSG required)
    else DXF / DWG
        V->>DC: ogr2ogr -dim 3 → WGS84 GeoJSON (source EPSG required)
    else Shapefile .zip
        V->>Conv: safe-extract ZIP (entry, size and ratio limits)
        V->>DC: ogr2ogr → WGS84 GeoJSON (CRS from .prj)
    else GeoJSON
        V->>DC: ogr2ogr → WGS84 GeoJSON
    end
    opt DXF
        V->>Conv: DxfColorInjector adds ACI colors to features
    end
    V->>V: stream GeoJSON, collect property schema
    V->>DT: CREATE TABLE vector_<ulid> (geom + sanitized columns)
    V->>SI: bulk insert features (ST_Force3DZ(ST_GeomFromGeoJSON))
    V->>DB: feature count, geometry type, bbox
    V->>DB: layer → published, job → done
```

### 5.4 Viewing a layer on the map

```mermaid
sequenceDiagram
    autonumber
    actor User
    participant Map as cesium-map.js
    participant API as GeodataLayerController
    participant DB as PostgreSQL
    participant FS as Public disk

    User->>Map: open map page
    Map->>API: GET /geodata/layers/all
    API->>DB: published layers grouped by category
    API-->>Map: categories → layers
    alt terrain layer toggled on
        Map->>API: GET /geodata/terrain/{id}/layer.json
        loop visible tiles
            Map->>API: GET /geodata/terrain/{id}/{z}/{x}/{y}.terrain
            API->>DB: layer published? (cached 30 s)
            API->>FS: read tile
            API-->>Map: quantized-mesh (gzip)
        end
        opt orthophoto
            Map->>API: GET /geodata/ortho/{id}_ortho/{z}/{x}/{y}.png
        end
    else vector layer toggled on
        Map->>API: GET /geodata/vector/{layer}/data.json
        API->>DB: SELECT *, ST_AsGeoJSON(geometry) FROM vector_<ulid>
        API-->>Map: streamed FeatureCollection
        Map->>Map: build primitives, labels, vertex index for snapping
    end
```

### 5.5 Measurement and terrain analysis (client-side)

The measure tools and the Vol/Profile panel run entirely in the browser. They sample heights from the terrain currently loaded in Cesium; no server round-trip is involved except fetching a comparison terrain list.

```mermaid
sequenceDiagram
    autonumber
    actor User
    participant TB as Measure toolbar
    participant MC as CesiumMapController
    participant D as CesiumMapDrawer
    participant Ces as Cesium terrain provider

    User->>TB: pick a tool (distance, area, circle, Vol/Profile)
    TB->>MC: enable…MeasureMode() / toggleAnalysisMode()
    MC->>D: start drawing (line, polygon, circle, point)
    loop clicks
        User->>D: click map (snaps to vector vertices when close)
    end
    User->>D: Done / double-click
    alt planar measure
        D-->>MC: points → ellipsoid distance / area
    else surface measure / cut & fill / profile
        MC->>Ces: sampleTerrainMostDetailed(grid or line samples)
        Ces-->>MC: heights
        MC->>MC: volumes, profile chart, statistics
    end
    MC-->>User: labels on the globe, results in the panel
```

### 5.6 Deleting a layer

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant API as GeodataController
    participant DB as PostgreSQL
    participant Q as Queue (default)
    participant C as CleanupLayerFilesJob

    Admin->>API: DELETE /geodata/layers/{layer}
    alt conversion queued/processing (not stale)
        API-->>Admin: 409 still processing
    else
        API->>API: derive outputs from layer id<br/>(terrain-tiles, xyz-tiles, vector_<ulid>, geojson, _wgs84 copies)
        API->>DB: transaction: delete layer, job, logs, sessions, source files
        API->>Q: dispatch CleanupLayerFilesJob (after commit)
        API-->>Admin: 200 Layer deleted
        Q->>C: run
        C->>DB: DROP TABLE IF EXISTS vector_<ulid>
        C->>C: delete tile directories and source files
    end
```

## 6. Frontend architecture

| File | Role |
|---|---|
| `resources/js/app.js` | Global helpers (`escapeHtml`, `safeColor`, `safeIconClass`, proj4 + UTM setup, number/date formatting) and UI bootstrapping. |
| `resources/js/ui-interactions.js` | Vanilla replacements for modals (`data-modal`), accordions, dropdowns, theme toggle and the off-canvas sidebar. |
| `resources/js/map.js` | Map page entry; imports the upload pipeline and the Cesium controller. |
| `resources/js/cesium-map.js` | `CesiumMapController`: viewer setup, navigation (compass, zoom, pivot camera, geolocation, coordinate picker), terrain/ortho/vector layers, measurement and Vol/Profile analysis, and the page wiring. |
| `resources/js/cesium-drawer.js` | `CesiumMapDrawer`: interactive point/line/polygon/circle drawing with undo and vertex snapping. |
| `resources/js/cesium-measure.js` | Distance, surface distance, area, surface area and circle measures built on the drawer. |
| `resources/js/upload/*` | Chunked upload client, upload modal wiring and job-status polling. |
| `resources/js/utils/*` | Constants (UTM zone from `config/geodata.php`), CSRF helper, map performance tuning, terrain provider cache, vector vertex index for snapping. |

Map lists (layers, recent uploads) are rendered from JSON with Blade components embedded in JavaScript template literals. Every user-controlled value passes through `window.escapeHtml`, and colors and icon classes are filtered through `safeColor` / `safeIconClass` before they reach `style` or `class` attributes.

Cesium's static assets (`Workers`, `Assets`, `ThirdParty`, `Widgets`) are copied from `node_modules/cesium` into `public/cesium` by the `copy-cesium-assets` plugin in `vite.config.js`, which runs at the start of every `npm run dev` / `npm run build` (skipped when the installed version is already there). `CESIUM_BASE_URL` is set to `/cesium/`.

## 7. Storage layout

| Disk / path | Contents | Public? |
|---|---|---|
| `storage/app/private/temp_chunks/<session>` | In-flight upload chunks | No |
| `storage/app/geodata/geotiff`, `…/vectors`, `…/geojson` | Assembled source files, reprojected copies, converted GeoJSON | No |
| `storage/app/public/terrain-tiles/<layer ulid>` | Quantized-mesh tiles + `layer.json` | Served through `/geodata/terrain/*` (auth + published check) |
| `storage/app/public/xyz-tiles/<layer ulid>_ortho` | Orthophoto PNG tiles | Served through `/geodata/ortho/*` (auth + published check) |
| `storage/app/public/icons/layer-categories` | Category icons (PNG/WebP only) | Served through `/geodata/icons/*` (auth) |

There is no `public/storage` symlink by design: every file goes through an authenticated route. Do not run `php artisan storage:link` in production unless you accept that tiles become reachable without login.

## 8. Security model

- **Authentication**: session-based login with username or email; logins are throttled per identifier and IP, plus a per-IP route limit. Password change and reset invalidate other sessions (`authenticateSessions()`).
- **Authorization**: two roles. Uploads, deletion and the data-management dashboard are `role:admin`; form requests re-check `canManageData()`.
- **Input handling**: upload extensions are allow-listed; ZIP extraction enforces entry count, uncompressed size and compression ratio limits; dynamic SQL identifiers are generated and pattern-checked server-side; feature values are bound parameters.
- **Isolation of untrusted files**: GDAL and CTB parse uploaded files only inside network-less, read-only, capability-dropped containers with resource limits.
- **Serving**: tiles, GeoJSON and icons are only served for published layers to authenticated users; tile routes are constrained to ULIDs and numeric z/x/y.
- **Output encoding**: Blade escaping on the server; `escapeHtml`, `safeColor` and `safeIconClass` on the client.

See [deployment.md](deployment.md#7-security-checklist) for the production checklist.

## 9. Design decisions and limits

| Decision | Why |
|---|---|
| One PostGIS table per vector layer | Keeps original attributes as real columns (queryable, typed) instead of a JSON blob, and lets each layer be dropped cleanly. |
| Converters in Docker, not on the host | GDAL/CTB parse untrusted binary formats; containers give isolation and reproducible tool versions without installing GDAL on the web server. |
| Assembly and conversion in one queue reservation | No hand-off window where a job could be lost between "assembled" and "converting". |
| Client-side measurement and analysis | Uses the exact terrain the user sees, no extra server load, works offline once tiles are cached. |
| Vanilla JS, no jQuery/Alpine/Preline | Smaller bundle, fewer moving parts for a template meant to be forked. |

Known limits:

- Converters must run on the same host as `storage/` (bind mounts are relative to the project).
- Cut & fill and profile need an active terrain layer; very large areas at fine resolution are refused client-side to keep the browser responsive.
- The terrain pipeline expects single-band DEM GeoTIFFs; LAS/LAZ, OBJ and glTF are defined in `SourceFileType` but have no pipeline yet.
