<?php

namespace App\Services\Conversion;

class DwgImportService
{
    public function __construct(protected GdalService $gdalService) {}

    /**
     * Konversi DWG/DXF ke WGS84 GeoJSON.
     * Karena DWG/DXF umumnya tidak punya metadata CRS terikat, sourceEpsg WAJIB disertakan.
     * Menggunakan flag -dim 3 (melalui GdalService) untuk mempertahankan fitur 3D.
     *
     * @param  string  $inputPath  Absolute path to .dwg/.dxf file
     * @param  string  $outputPath  Absolute path to output .geojson file
     * @param  string  $sourceEpsg  Source CRS (e.g. 'EPSG:32748')
     *
     * @throws \Exception
     */
    public function process(string $inputPath, string $outputPath, string $sourceEpsg): bool
    {
        // GdalService sudah dikonfigurasi untuk mempertahankan dimensi 3D dan presisi koordinat.
        $this->gdalService->reprojectToWgs84($inputPath, $outputPath, $sourceEpsg);

        return true;
    }
}
