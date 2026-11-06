<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GdalService
{
    public ?string $jobId = null;

    public function setJobId(string $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * Reproject any geo format to WGS84 GeoJSON using Docker GDAL.
     *
     * @param  string  $inputPath  Absolute path on host to input file
     * @param  string  $outputPath  Absolute path on host to output .geojson file
     * @param  string|null  $sourceEpsg  Optional source CRS (e.g. 'EPSG:23836')
     *
     * @throws \Exception
     */
    public function reprojectToWgs84(string $inputPath, string $outputPath, ?string $sourceEpsg = null): bool
    {
        $inputDir = dirname($inputPath);
        $outputDir = dirname($outputPath);

        $inputFilename = basename($inputPath);
        $outputFilename = basename($outputPath);

        // Map host paths to container paths
        $dockerInputPath = "/data/input/{$inputFilename}";
        $dockerOutputPath = "/data/output/{$outputFilename}";

        $ogrArgs = [
            'ogr2ogr',
            '--config',
            'SHAPE_RESTORE_SHX',
            'YES',
            '-f',
            'GeoJSON',
        ];

        if (! empty($sourceEpsg)) {
            $ogrArgs[] = '-s_srs';
            $ogrArgs[] = $sourceEpsg;
        }

        $ogrArgs = array_merge($ogrArgs, [
            '-t_srs',
            'EPSG:4326',
            '-dim',
            '3',
            '-lco',
            'COORDINATE_PRECISION=8',
            '-lco',
            'WRITE_BBOX=YES',
            $dockerOutputPath,
            $dockerInputPath,
        ]);

        // Build the command arguments array for Process::run()
        // Support both 'docker compose' (V2 plugin) and 'docker-compose' (V1)
        $dockerComposeBin = config('geodata.docker_compose_bin', 'docker compose');
        $dockerComposeArgs = explode(' ', $dockerComposeBin);

        $arguments = array_merge($dockerComposeArgs, [
            '-f',
            config('geodata.docker_compose_file', 'docker-compose.tools.yml'),
            'run',
            '--rm',
            '-v',
            "{$inputDir}:/data/input:ro",
            '-v',
            "{$outputDir}:/data/output",
        ]);

        if ($this->jobId) {
            $arguments[] = '-l';
            $arguments[] = "conversion_job_id={$this->jobId}";
        }

        $arguments[] = 'gdal';
        $arguments = array_merge($arguments, $ogrArgs);

        Log::info('Executing GDAL Reprojection via Process Facade', [
            'source_epsg' => $sourceEpsg,
            'input' => $inputPath,
            'output' => $outputPath,
        ]);

        $result = Process::path(base_path())->timeout(7200)->run($arguments);

        if (! $result->successful()) {
            $errorMsg = $result->errorOutput();
            Log::error('GDAL conversion failed: '.$errorMsg);

            if (str_contains($errorMsg, 'libopencad') && str_contains($errorMsg, 'does not support this version')) {
                throw new \Exception('This version of the DWG format is not supported by GDAL. Please save your DWG file as an AutoCAD 2000 file, or use the DXF format.');
            }

            throw new \Exception("GDAL conversion failed. Output: {$errorMsg}");
        }

        if (! file_exists($outputPath)) {
            throw new \Exception("GDAL reported success but output file not found at: {$outputPath}");
        }

        Log::info('GDAL conversion successful: '.$outputPath);

        return true;
    }
}
