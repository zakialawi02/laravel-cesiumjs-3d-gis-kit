<?php

namespace App\Services\Database;

use App\Services\Upload\GeoJsonFeatureReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpatialImportService
{
    /**
     * Import WGS84 GeoJSON to PostGIS features table.
     *
     * @param  string  $geojsonPath  Absolute path to WGS84 GeoJSON
     * @param  string  $layerId  ULID of the layer
     * @return array Metadata summary [feature_count, geometry_type, bbox]
     *
     * @throws \Exception
     */
    public function import(string $geojsonPath, string $tableName): array
    {
        // Validate table name format before any SQL operations
        $this->assertValidTableName($tableName);

        $count = 0;
        $geometryType = 'Mixed';

        // Get table columns to ensure consistency in batch inserts
        $columns = Schema::getColumnListing($tableName);
        $reserved = ['id', 'geometry', 'created_at', 'updated_at'];

        $insertData = [];
        $batchBytes = 0;
        foreach (app(GeoJsonFeatureReader::class)->features($geojsonPath) as $f) {
            if ($count === 0) {
                $geometryType = $f['geometry']['type'] ?? 'Unknown';
            }
            $count++;
            // Initialize row with nulls for all table columns
            $row = array_fill_keys($columns, null);

            // Remove 'id' as it's auto-incrementing
            unset($row['id']);

            // Encode geometry to JSON and escape single quotes for SQL literal
            $geomJson = json_encode($f['geometry']);
            $escapedGeomJson = str_replace("'", "''", $geomJson);

            $row['geometry'] = DB::raw("ST_Force3DZ(ST_SetSRID(ST_GeomFromGeoJSON('{$escapedGeomJson}'), 4326))");
            $row['created_at'] = now();
            $row['updated_at'] = now();

            $props = $f['properties'] ?? [];
            if (! empty($props)) {
                foreach ($props as $key => $value) {
                    $safeKey = Str::of($key)->replaceMatches('/[^a-z0-9_]/i', '_')->lower()->toString();

                    if (in_array($safeKey, $reserved)) {
                        $safeKey = 'attr_'.$safeKey;
                    }

                    // Only add if the column exists in the table
                    if (array_key_exists($safeKey, $row)) {
                        if (is_null($value)) {
                            $row[$safeKey] = null;
                        } elseif (is_scalar($value)) {
                            $row[$safeKey] = $value;
                        } else {
                            $row[$safeKey] = json_encode($value);
                        }
                    }
                }
            }

            $insertData[] = $row;
            $batchBytes += strlen(serialize($row));
            if (count($insertData) >= 500 || $batchBytes >= 4 * 1024 * 1024) {
                DB::table($tableName)->insert($insertData);
                $insertData = [];
                $batchBytes = 0;
            }
        }
        if ($insertData) {
            DB::table($tableName)->insert($insertData);
        }

        // Calculate Bounding Box using PostGIS
        $quotedTableName = $this->quoteIdentifier($tableName);
        $bboxResult = DB::selectOne("
            SELECT ST_AsText(ST_Extent(geometry)) as extent
            FROM {$quotedTableName}
        ");

        $bbox = $this->parsePostGisExtent($bboxResult->extent ?? null);

        return [
            'feature_count' => $count,
            'geometry_type' => $geometryType,
            'bbox' => $bbox,
        ];
    }

    /**
     * Validate table name follows expected pattern.
     */
    private function assertValidTableName(string $tableName): void
    {
        if (! preg_match('/^vector_[a-z0-9_]{1,60}$/', $tableName)) {
            throw new \InvalidArgumentException("Invalid table name: {$tableName}");
        }
    }

    /**
     * Safely quote a validated identifier for PostgreSQL.
     */
    private function quoteIdentifier(string $identifier): string
    {
        $this->assertValidTableName($identifier);

        return '"'.str_replace('"', '', $identifier).'"';
    }

    private function parsePostGisExtent(?string $extent): ?array
    {
        if (! $extent) {
            return null;
        }
        // POLYGON((minX minY, minX maxY, maxX maxY, maxX minY, minX minY))
        preg_match_all('/-?\d+\.?\d*/', $extent, $matches);
        $coords = $matches[0];

        if (count($coords) >= 4) {
            return [
                'west' => floatval($coords[0]),
                'south' => floatval($coords[1]),
                'east' => floatval($coords[4]),
                'north' => floatval($coords[5]),
            ];
        }

        return null;
    }

    /**
     * Scan GeoJSON to extract all unique property keys.
     */
    public function getPropertiesSchema(string $geojsonPath): array
    {
        $keys = [];
        foreach (app(GeoJsonFeatureReader::class)->features($geojsonPath) as $feature) {
            if (isset($feature['properties']) && is_array($feature['properties'])) {
                foreach (array_keys($feature['properties']) as $key) {
                    $keys[$key] = true;
                    if (count($keys) > 256) {
                        throw new \RuntimeException('Vector dataset exceeds the 256 attribute limit.');
                    }
                }
            }
        }

        return array_keys($keys);
    }
}
