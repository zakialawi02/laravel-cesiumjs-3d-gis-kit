<?php

namespace App\Services\Upload;

use Illuminate\Support\Facades\File;
use ZipArchive;

class ShapefileArchive
{
    public function extract(string $path, string $destination): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Unable to open shapefile archive.');
        }
        try {
            $maximum = config('geodata.upload.zip_max_uncompressed_mb') * 1024 * 1024;
            if ($zip->numFiles < 1 || $zip->numFiles > config('geodata.upload.zip_max_entries')) {
                throw new \RuntimeException('Archive contains too many entries or is empty.');
            }
            $entries = [];
            $seen = [];
            $total = 0;
            $shapefiles = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new \RuntimeException('Unable to inspect archive entry.');
                }
                $name = str_replace('\\', '/', $stat['name']);
                if ($name === '' || str_starts_with($name, '/') || preg_match('/[\x00-\x1f\x7f:<>"|?*]/', $name)
                    || in_array('..', explode('/', $name), true) || in_array('.', explode('/', $name), true)) {
                    throw new \RuntimeException('Unsafe archive entry path.');
                }
                foreach (explode('/', rtrim($name, '/')) as $segment) {
                    if ($segment === '' || rtrim($segment, ' .') !== $segment
                        || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $segment)) {
                        throw new \RuntimeException('Unsafe archive entry name.');
                    }
                }
                $attributes = 0;
                $operatingSystem = 0;
                $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if ($operatingSystem === 3 && ! in_array($type, [0, 0100000, 0040000], true)) {
                    throw new \RuntimeException('Archive links and special files are not allowed.');
                }
                if (($stat['encryption_method'] ?? 0) !== 0) {
                    throw new \RuntimeException('Encrypted archive entries are not supported.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($extension, ['shp', 'shx', 'dbf', 'prj', 'cpg', 'sbn', 'sbx', 'qix', 'xml'], true)) {
                    throw new \RuntimeException('Archive contains an unsupported file type.');
                }
                $key = strtolower($name);
                if (isset($seen[$key])) {
                    throw new \RuntimeException('Duplicate archive entry.');
                }
                $seen[$key] = true;
                $total += $stat['size'];
                if ($total > $maximum || $stat['size'] > max(1, $stat['comp_size']) * config('geodata.upload.zip_max_ratio')) {
                    throw new \RuntimeException('Archive exceeds decompression limits.');
                }
                $entries[] = ['name' => $name, 'original' => $stat['name'], 'size' => $stat['size'], 'crc' => $stat['crc']];
                if ($extension === 'shp') {
                    $shapefiles[] = $name;
                }
            }
            if (count($shapefiles) !== 1) {
                throw new \RuntimeException('Provide exactly one shapefile dataset per archive.');
            }
            $base = strtolower(substr($shapefiles[0], 0, -4));
            foreach (['shx', 'dbf', 'prj'] as $extension) {
                if (! isset($seen[$base.'.'.$extension])) {
                    throw new \RuntimeException('Shapefile archive requires matching SHP, SHX, DBF and PRJ files.');
                }
            }
            app(UploadSessionService::class)->ensureDiskSpace($total);
            File::ensureDirectoryExists($destination, 0700);
            $writtenTotal = 0;
            foreach ($entries as $entry) {
                $outputPath = $destination.'/'.$entry['name'];
                File::ensureDirectoryExists(dirname($outputPath), 0700);
                $input = $zip->getStream($entry['original']);
                $output = fopen($outputPath, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    throw new \RuntimeException('Unable to extract archive entry.');
                }
                $size = 0;
                $crc = hash_init('crc32b');
                try {
                    while (! feof($input)) {
                        $buffer = fread($input, 65536);
                        if ($buffer === false) {
                            throw new \RuntimeException('Unable to decompress archive entry.');
                        }
                        $size += strlen($buffer);
                        $writtenTotal += strlen($buffer);
                        if ($size > $entry['size'] || $writtenTotal > $maximum) {
                            throw new \RuntimeException('Archive exceeds decompression limits.');
                        }
                        if (fwrite($output, $buffer) !== strlen($buffer)) {
                            throw new \RuntimeException('Unable to write extracted file.');
                        }
                        hash_update($crc, $buffer);
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if ($size !== $entry['size'] || hash_final($crc) !== sprintf('%08x', $entry['crc'])) {
                    throw new \RuntimeException('Archive entry failed integrity verification.');
                }
            }

            return $destination.'/'.$shapefiles[0];
        } finally {
            $zip->close();
        }
    }
}
