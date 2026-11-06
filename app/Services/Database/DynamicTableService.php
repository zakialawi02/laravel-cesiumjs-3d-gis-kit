<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DynamicTableService
{
    /**
     * Create a physical PostGIS table for a vector layer.
     * Table name: vector_{layer_ulid}
     */
    public function createTable(string $layerId, array $attributes = []): string
    {
        // Strict validation — reject entirely if not safe
        $sanitizedId = Str::of($layerId)->replace('-', '_')->replaceMatches('/[^a-z0-9_]/i', '')->lower()->toString();
        if (empty($sanitizedId) || strlen($sanitizedId) > 63) {
            throw new \InvalidArgumentException("Invalid layerId for table creation: {$layerId}");
        }

        $tableName = $this->generateTableName($sanitizedId);

        // Final safety check on generated table name
        if (! preg_match('/^vector_[a-z0-9_]{1,60}$/', $tableName)) {
            throw new \InvalidArgumentException("Generated table name failed validation: {$tableName}");
        }

        if (Schema::hasTable($tableName)) {
            Log::warning("DynamicTableService: Table {$tableName} already exists. Dropping for fresh import.");
            $this->dropTable($tableName);
        }

        $attrSql = '';
        $reserved = ['id', 'geometry', 'created_at', 'updated_at'];

        if (empty($attributes)) {
            $attrSql = '"properties" TEXT,';
        } else {
            foreach ($attributes as $attr) {
                // Sanitize attribute name for SQL
                $safeAttr = Str::of($attr)->replaceMatches('/[^a-z0-9_]/i', '_')->lower()->toString();

                // Reject attributes that are empty or too long after sanitization
                if (empty($safeAttr) || strlen($safeAttr) > 63) {
                    Log::warning("DynamicTableService: Skipping invalid attribute: {$attr}");

                    continue;
                }

                // Skip if reserved or prefix it
                if (in_array($safeAttr, $reserved)) {
                    $safeAttr = 'attr_'.$safeAttr;
                }

                // Quote identifier for PostgreSQL
                $attrSql .= "\"{$safeAttr}\" TEXT,";
            }
        }

        // Use properly quoted identifiers
        $quotedTableName = "\"{$tableName}\"";

        DB::statement("CREATE TABLE {$quotedTableName} (
            id BIGSERIAL PRIMARY KEY,
            geometry geometry(GeometryZ, 4326),
            {$attrSql}
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $idxName = 'idx_'.substr($tableName, 0, 50).'_geom';
        DB::statement("CREATE INDEX \"{$idxName}\" ON {$quotedTableName} USING GIST (geometry)");

        Log::info("DynamicTableService: Created physical table {$tableName}");

        return $tableName;
    }

    /**
     * Drop a physical PostGIS table.
     */
    public function dropTable(string $tableName): bool
    {
        if (empty($tableName)) {
            return false;
        }

        // Validate table name format before dropping
        if (! preg_match('/^vector_[a-z0-9_]{1,60}$/', $tableName)) {
            Log::warning("DynamicTableService: Refused to drop table with invalid name: {$tableName}");

            return false;
        }

        Schema::dropIfExists($tableName);
        Log::info("DynamicTableService: Dropped physical table {$tableName}");

        return true;
    }

    /**
     * Generate table name from layer ID.
     */
    public function generateTableName(string $layerId): string
    {
        // Sanitize ULID just in case (though ULIDs are usually alphanumeric)
        $sanitizedId = Str::of($layerId)->replace('-', '_')->lower();

        return "vector_{$sanitizedId}";
    }
}
