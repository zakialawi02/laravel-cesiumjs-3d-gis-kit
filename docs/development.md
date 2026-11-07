# Development Guide

How to run Laravel CesiumJS 3D GIS Kit locally, how the code is organised, and the conventions to follow when changing it.

- [1. Prerequisites](#1-prerequisites)
- [2. First-time setup](#2-first-time-setup)
- [3. Running the app](#3-running-the-app)
- [4. Configuration reference](#4-configuration-reference)
- [5. Project structure](#5-project-structure)
- [6. Working on the code](#6-working-on-the-code)
- [7. Testing and formatting](#7-testing-and-formatting)
- [8. Troubleshooting](#8-troubleshooting)

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3+ | Extensions: `pdo_pgsql`, `pgsql`, `pdo_sqlite` (tests), `zip`, `fileinfo`, `mbstring`, `openssl`. |
| Composer | 2.x | |
| Node.js | 20+ (24 tested) | npm comes with it. |
| PostgreSQL | 14+ | With the **PostGIS** extension available (the first migration runs `CREATE EXTENSION IF NOT EXISTS postgis`). |
| Docker | Engine / Desktop with Compose v2 | Runs the GDAL and Cesium Terrain Builder converters. Only needed to convert uploads. |

Windows users can use [Laragon](https://laragon.org) (PHP + PostgreSQL) with Docker Desktop; Linux and macOS work the same with native packages.

## 2. First-time setup

```bash
git clone <your-fork-url> laravel-cesiumjs-3d-gis-kit
cd laravel-cesiumjs-3d-gis-kit
```

Create an empty database (PostGIS must be installable in it):

```bash
createdb cesium_core
```

Copy the environment file and set your database credentials:

```bash
cp .env.example .env
```

Run the setup script. It installs Composer and npm dependencies, generates the app key, migrates, seeds and builds the frontend:

```bash
composer run setup
```

Pull the converter images once (about 2 GB, used only when converting uploads):

```bash
docker compose -f docker-compose.tools.yml pull
```

The seeder creates two local accounts:

| Email | Username | Password | Role |
|---|---|---|---|
| `admin@mail.com` | `admin` | `admin` | Admin |
| `engineering@mail.com` | `engineering` | `engineering` | Engineering |

> These passwords are for local development only. The seeder does nothing when users already exist, so re-seeding never resets a changed password. Change them before anyone else can reach the app.

To start over with an empty database:

```bash
php artisan migrate:fresh --seed
```

## 3. Running the app

```bash
composer run dev
```

This starts three processes side by side:

| Name | Command | Purpose |
|---|---|---|
| `server` | `php artisan serve` | App on <http://localhost:8000> |
| `queue` | `php artisan queue:work --queue=upload,conversion,default --tries=2 --timeout=7200 --memory=512` | Assembles uploads and runs conversions |
| `vite` | `npm run dev` | Hot-reloading CSS/JS on port 5173 |

Without the queue worker, uploads stay in "queued" forever. Docker must be running before you upload anything.

The scheduler (stale upload cleanup) is not part of `composer run dev`; run it when you need it:

```bash
php artisan schedule:work
```

### Trying the pipeline

1. Log in as `admin`, open the **Data** panel (`D`) and upload a single-band DEM GeoTIFF (optionally with an orthophoto GeoTIFF), or a GeoJSON, zipped Shapefile, DXF, DWG or Surpac STR file. CAD and STR files need the source EPSG code.
2. Watch the job in **Active Uploads**, or tail the worker output.
3. Open the **Layers** panel (`L`) and toggle the new layer on.
4. Use the ruler button on the right toolbar for distance, area and circle measures, or **Vol/Profile** for cut & fill and elevation profiles (these need an active terrain).

## 4. Configuration reference

Everything below lives in `.env` (see `.env.example`).

| Variable | Default | Meaning |
|---|---|---|
| `APP_NAME` | `3D WebGIS Core` | Shown in the page title and header. |
| `DB_CONNECTION` | `pgsql` | Must be PostgreSQL; vector layers are PostGIS tables. |
| `QUEUE_CONNECTION` | `database` | Any Laravel queue driver works; the worker must listen to `upload,conversion,default`. |
| `DB_QUEUE_RETRY_AFTER` | `7500` | Must be larger than the longest job timeout (7200 s for terrain). |
| `SESSION_DRIVER` | `database` | |
| `MAIL_SMTP_ON` | `false` | Password-reset emails are only offered when `true`. |
| `GEODATA_MAX_UPLOAD_MB` | `1000` | Maximum size of one uploaded file. |
| `GEODATA_UPLOAD_SESSION_HOURS` | `24` | How long an unfinished upload can be resumed. |
| `GEODATA_UPLOAD_MAX_SESSIONS` | `4` | Concurrent upload sessions per user. |
| `GEODATA_UPLOAD_MAX_INFLIGHT_MB` | `2000` | Total in-flight upload size per user. |
| `GEODATA_MIN_FREE_DISK_MB` | `512` | Uploads are refused below this free space. |
| `GEODATA_ZIP_MAX_UNCOMPRESSED_MB` | `2000` | Shapefile ZIP bomb guard (also: max 1000 entries, max ratio 100). |
| `GEODATA_DOCKER_COMPOSE_FILE` | `docker-compose.tools.yml` | Converter definitions. |
| `GEODATA_DOCKER_COMPOSE_BIN` | `docker compose` | Use `docker-compose` for Compose v1. |
| `GEODATA_CONVERTER_USER` | `1000:1000` | UID:GID the converters run as; match the PHP worker user on Linux. |
| `GEODATA_CONVERTER_MEMORY` / `GEODATA_CONVERTER_CPUS` | `4g` / `2` | Resource limits per converter container. |
| `GEODATA_TERRAIN_MIN_ZOOM` / `GEODATA_TERRAIN_MAX_ZOOM` | `0` / `15` | Terrain tile pyramid. |
| `GEODATA_ENABLE_PYTHON_COLOR_PARSER` | `false` | Optional host-side `ezdxf` DXF color parser. Keep off unless you need it. |
| `GIS_UTM_ZONE` / `GIS_UTM_HEMISPHERE` | `49` / `N` | UTM zone used for coordinate display and measurements. |

## 5. Project structure

```text
app/
├── Console/Commands/CleanupStaleUploads.php   # geodata:cleanup-stale (scheduled)
├── Enums/                                     # UserRole, LayerStatus, JobStatus, LayerType, …
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                              # login, logout, password reset
│   │   ├── Dashboard/                         # layer management, layer categories
│   │   ├── GeodataUploadController.php        # chunked upload API
│   │   ├── GeodataLayerController.php         # layer lists, tiles, vector GeoJSON
│   │   ├── GeodataController.php              # layer deletion
│   │   └── IconController.php                 # category icons behind auth
│   ├── Middleware/RoleCheck.php
│   └── Requests/
├── Jobs/                                      # AssembleGeodataUpload, ProcessGeotiffTerrain, ProcessVectorLayer, CleanupLayerFilesJob
├── Models/
├── Services/
│   ├── Conversion/                            # GDAL/CTB wrappers, DXF/DWG/SHP/STR importers
│   ├── Database/                              # dynamic PostGIS tables, spatial import
│   └── Upload/                                # sessions, assembly, validation, safe ZIP/GeoJSON readers
└── Traits/HasRoles.php                        # canUploadData(), canManageData(), …
config/geodata.php                             # upload limits, converter settings, UTM zone
docker-compose.tools.yml                       # hardened GDAL + CTB converter services
resources/
├── css/app.css                                # Tailwind 4 theme + map/analysis panel styles
├── js/                                        # see docs/architecture.md#6-frontend-architecture
└── views/
    ├── components/                            # Blade UI components (modal, buttons, cards, …)
    ├── layouts/                               # app (dashboard), app-front (map) and guest (auth)
    └── pages/
        ├── dashboard/                         # overview, layers, layer categories, profile
        └── map/                               # map page, panels (layers, data, analysis, help)
vite.config.js                                 # Vite entries + plugin copying Cesium static assets to public/cesium
```

## 6. Working on the code

### Conventions

- **Backend**: follow the Laravel conventions in `CLAUDE.md`/`AGENTS.md`. Use `php artisan make:*` for new classes, form requests for validation, named routes with `route()`, and explicit return types.
- **Frontend**: vanilla JavaScript only. Do not add jQuery, Alpine.js or Preline. Reuse the helpers in `ui-interactions.js` (`data-modal`, `data-accordion`, `window.openModal` / `window.closeModal`).
- **Escaping**: when rendering JSON into HTML on the client, wrap values with `window.escapeHtml`, and colors / icon classes with `window.safeColor` / `window.safeIconClass`.
- **UI text** is English.
- **Dependencies**: discuss before adding Composer or npm packages. The kit deliberately avoids heavy front-end frameworks.

### Common tasks

**Add a new vector input format.** Accept the extension in `config/geodata.php` (`allowed_extensions`) and in `UploadCompletionService` (vector allow-list), add a branch in `ProcessVectorLayer::convertToWgs84GeoJson()` that produces WGS84 GeoJSON (preferably through `GdalService` inside the `gdal` container), and add the type to `SourceFileType`.

**Change the terrain pipeline.** Edit `TerrainConversionService`. Every external tool call goes through `runDockerCommand()`, so new tools must exist in the `gdal` or `ctb` images.

**Add a map tool.** Add the button to the tool rail in `resources/views/pages/map/partials/map-controls.blade.php` (class `map-tool`; add `data-map-tool aria-pressed="false"` when it toggles a mode, so `applyBtnStyle()` drives its pressed state), the behaviour to `CesiumMapController` (or a new module imported by it), and wire the click in the `DOMContentLoaded` block at the end of `cesium-map.js`. Use `CesiumMapDrawer` for interactive drawing and the shared drawing control bar (`showDrawingControlBar(text, context)`). The map chrome is styled in the "Map workspace" section of `resources/css/app.css`.

**Add a dashboard page.** Create a controller under `app/Http/Controllers/Dashboard`, a form request under `app/Http/Requests/Dashboard`, a view under `resources/views/pages/dashboard`, register the route in the `dashboard.` group in `routes/web.php`, and add the nav item in `resources/views/layouts/app.blade.php`.

## 7. Testing and formatting

Format PHP before committing:

```bash
vendor/bin/pint --dirty
```

Run the feature tests:

```bash
php artisan test --compact --testsuite=Feature
```

Tests run against an in-memory SQLite database (see `phpunit.xml`), so PostGIS-specific code paths are skipped there. Some feature tests inherited from the starter skeleton still target features this kit removed (registration, email verification, account deletion, default-password seeding) and currently fail; they are candidates for cleanup or rewrite.

Build the frontend as production does:

```bash
npm run build
```

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| Upload stays "queued" | The queue worker is not running, or listens to the wrong queues. Use `composer run dev` or `php artisan queue:work --queue=upload,conversion,default`. |
| Conversion fails immediately with a Docker error | Start Docker Desktop / the Docker daemon, run `docker compose -f docker-compose.tools.yml pull`, and check `GEODATA_DOCKER_COMPOSE_BIN`. |
| Converted files are owned by root or cannot be deleted (Linux) | Set `GEODATA_CONVERTER_USER` to the PHP worker's `uid:gid`. |
| `could not open extension control file ... postgis` | Install PostGIS for your PostgreSQL version, then re-run the migrations. |
| Globe is blank or Cesium widgets are unstyled | `public/cesium` is missing or stale. Delete `public/cesium` and run `npm run build`. |
| `Unable to locate file in Vite manifest` | Run `npm run build`, or keep `npm run dev` running. |
| Pages load assets from `localhost:5173` after stopping Vite | A stale `public/hot` file is left behind; delete it. |
| "Too many login attempts" while testing | Wait a minute, or clear the cache with `php artisan cache:clear`. |
| Measure or Vol/Profile results look flat | Turn on a terrain layer first; without one, heights come from the ellipsoid. |
