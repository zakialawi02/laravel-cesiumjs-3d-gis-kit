<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vector layers are imported into dynamic PostGIS tables (DynamicTableService).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }

        Schema::create('source_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('original_name')->comment('Original filename at upload time');
            $table->string('stored_name')->comment('Randomized filename used in storage');
            $table->string('file_type', 20)->comment('tif | tiff | geotiff | shapefile | geojson | dxf | dwg | str');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->text('storage_path')->comment('Path relative to the geodata disk');
            $table->string('checksum_sha256', 64);
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('uploading')->comment('uploading | uploaded | validating | ready | invalid');
            $table->timestamps();
        });

        Schema::create('conversion_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('layer_type', 20)->comment('terrain | vector | imagery');
            $table->foreignUlid('source_file_id')->constrained('source_files')->restrictOnDelete();
            $table->foreignUlid('ortho_file_id')->nullable()->constrained('source_files')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index()->comment('queued | processing | done | failed');
            $table->string('conversion_tool', 50)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversion_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('job_id')->constrained('conversion_jobs')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('stdout_log')->nullable();
            $table->text('stderr_log')->nullable();
            $table->unsignedBigInteger('output_size')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'attempt']);
        });

        Schema::create('layer_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 150)->unique();
            $table->string('color', 9)->default('#888888');
            $table->string('icon_path')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('layers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignUlid('job_id')->unique()->constrained('conversion_jobs')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('layer_type', 20)->comment('terrain | vector | imagery');
            $table->string('status', 20)->default('pending')->index()->comment('pending | queued | processing | published | process_failed | archived');
            $table->foreignUlid('layer_category_id')->nullable()->constrained('layer_categories')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('terrain_details', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('layer_id')->unique()->constrained('layers')->cascadeOnDelete();
            $table->foreignUlid('source_file_id')->constrained('source_files')->restrictOnDelete();
            $table->foreignUlid('ortho_file_id')->nullable()->constrained('source_files')->nullOnDelete();
            $table->boolean('has_ortho')->default(false);
            $table->text('ortho_tiles_path')->nullable();
            $table->integer('ortho_min_zoom')->nullable();
            $table->integer('ortho_max_zoom')->nullable();
            $table->json('native_bounding_box')->nullable();
            $table->string('srs', 50)->nullable();
            $table->string('output_type', 20)->default('terrain');
            $table->text('tileset_path')->nullable();
            $table->text('tileset_url')->nullable();
            $table->date('acquisition_date')->nullable();
        });

        Schema::create('vector_details', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('layer_id')->unique()->constrained('layers')->cascadeOnDelete();
            $table->foreignUlid('source_file_id')->constrained('source_files')->restrictOnDelete();
            $table->json('native_bounding_box')->nullable();
            $table->string('srs', 50)->nullable();
            $table->string('source_format', 20)->nullable();
            $table->string('source_epsg', 50)->nullable();
            $table->string('geometry_type', 30)->nullable();
            $table->unsignedInteger('feature_count')->default(0);
            $table->date('acquisition_date')->nullable();
            $table->string('physical_table_name')->nullable();
            $table->string('output_type', 20)->default('geojson');
            $table->text('output_path')->nullable();
        });

        Schema::create('upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->json('chunks');
            $table->string('status', 20)->default('uploading');
            $table->foreignUlid('source_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('conversion_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('completion_hash', 64)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('vector_details');
        Schema::dropIfExists('terrain_details');
        Schema::dropIfExists('layers');
        Schema::dropIfExists('layer_categories');
        Schema::dropIfExists('conversion_logs');
        Schema::dropIfExists('conversion_jobs');
        Schema::dropIfExists('source_files');
    }
};
