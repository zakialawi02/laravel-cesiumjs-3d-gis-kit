<?php

use App\Http\Controllers\Dashboard\LayerCategoryController as DashboardLayerCategoryController;
use App\Http\Controllers\Dashboard\LayerController as DashboardLayerController;
use App\Http\Controllers\Dashboard\UserController as DashboardUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeodataController;
use App\Http\Controllers\GeodataLayerController;
use App\Http\Controllers\GeodataUploadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\LayerCategoryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Dashboard & own profile (both roles)
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');

    Route::prefix('geodata')->group(function (): void {
        // Tile & data serving
        Route::get('terrain/{layerId}/{z}/{x}/{y}.terrain', [GeodataLayerController::class, 'serveTerrainTile'])
            ->where(['layerId' => '[0-9A-Za-z]{26}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,9}', 'y' => '[0-9]{1,9}'])->name('terrain.tile');
        Route::get('terrain/{layerId}/layer.json', [GeodataLayerController::class, 'serveTerrainMetadata'])
            ->where('layerId', '[0-9A-Za-z]{26}')->name('terrain.layer');
        Route::get('ortho/{layerId}/{z}/{x}/{y}.png', [GeodataLayerController::class, 'serveOrthoTile'])
            ->where(['layerId' => '[0-9A-Za-z]{26}_ortho', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,9}', 'y' => '[0-9]{1,9}'])->name('ortho.tile');
        Route::get('vector/{layer}/data.json', [GeodataLayerController::class, 'getVectorGeoJson'])->name('vector.geojson');

        // Layer listing
        Route::get('layers/all', [GeodataLayerController::class, 'listAllLayers'])->name('layers.all');
        Route::get('layers/terrain', [GeodataLayerController::class, 'listTerrainLayers'])->name('layers.terrain');
        Route::get('layer-categories', [LayerCategoryController::class, 'list'])->name('layer-categories.list');

        // Upload pipeline & layer management (admin only)
        Route::middleware('role:admin')->group(function (): void {
            Route::get('layers/recent-uploads', [GeodataLayerController::class, 'listRecentUploads'])->name('layers.recent-uploads');
            Route::delete('layers/{layer}', [GeodataController::class, 'destroy'])->name('layers.destroy');

            Route::post('upload/sessions', [GeodataUploadController::class, 'start'])->middleware('throttle:upload-start')->name('upload.start');
            Route::get('upload/sessions/{identifier}', [GeodataUploadController::class, 'session'])->middleware('throttle:upload-status')->name('upload.session');
            Route::delete('upload/sessions/{identifier}', [GeodataUploadController::class, 'cancel'])->middleware('throttle:upload-start')->name('upload.cancel');
            Route::post('upload/chunk', [GeodataUploadController::class, 'uploadChunk'])->middleware('throttle:upload-chunks')->name('upload.chunk');
            Route::post('upload/assemble-raster', [GeodataUploadController::class, 'assembleRaster'])->middleware('throttle:upload-start')->name('upload.assemble-raster');
            Route::post('upload/assemble-vector', [GeodataUploadController::class, 'assembleVector'])->middleware('throttle:upload-start')->name('upload.assemble-vector');
            Route::get('upload/status/{job}', [GeodataUploadController::class, 'status'])->middleware('throttle:upload-status')->name('upload.status');
        });
    });

    // Uploaded layer-category icons, served behind auth instead of a public storage link
    Route::get('geodata/icons/{path}', [IconController::class, 'show'])->where('path', IconController::PATH_PATTERN)->name('icons.show');

    // Dashboard data management (admin only)
    Route::prefix('dashboard')->name('dashboard.')->middleware('role:admin')->group(function (): void {
        Route::get('layers', [DashboardLayerController::class, 'index'])->name('layers.index');
        Route::get('layers/{layer}', [DashboardLayerController::class, 'show'])->name('layers.show');
        Route::put('layers/{layer}', [DashboardLayerController::class, 'update'])->name('layers.update');
        Route::patch('layers/{layer}/archive', [DashboardLayerController::class, 'archive'])->name('layers.archive');
        Route::patch('layers/{layer}/restore', [DashboardLayerController::class, 'restore'])->name('layers.restore');

        Route::resource('layer-categories', DashboardLayerCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['layer-categories' => 'category']);

        Route::resource('users', DashboardUserController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/auth.php';
