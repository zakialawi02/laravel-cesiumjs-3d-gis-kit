<?php

namespace App\Services\Upload;

/** Stream a FeatureCollection, keeping at most one feature in memory. */
class GeoJsonFeatureReader
{
    public function features(string $path): \Generator
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read GeoJSON.');
        }
        try {
            // Parse the root as JSON tokens, locating the top-level features array.
            $buffer = '';
            $depth = 0;
            $inString = false;
            $escaped = false;
            $foundFeatures = false;
            $inFeatures = false;
            $feature = '';
            $featureDepth = 0;
            $expectFeature = true;
            $afterFeature = false;
            $hasFeature = false;
            while (! feof($stream)) {
                $block = fread($stream, 65536);
                if ($block === false) {
                    throw new \RuntimeException('Unable to read GeoJSON.');
                }
                for ($i = 0, $length = strlen($block); $i < $length; $i++) {
                    $char = $block[$i];
                    if ($inFeatures) {
                        if ($featureDepth === 0) {
                            if (ctype_space($char)) {
                                continue;
                            }
                            if ($char === ']' && ! $afterFeature) {
                                if ($hasFeature) {
                                    throw new \RuntimeException('Trailing comma in GeoJSON features.');
                                }
                                $inFeatures = false;
                                $depth--;

                                continue;
                            }
                            if ($char === ',' && $afterFeature) {
                                $afterFeature = false;
                                $expectFeature = true;

                                continue;
                            }
                            if ($char === ']' && $afterFeature) {
                                $inFeatures = false;
                                $depth--;

                                continue;
                            }
                            if ($char !== '{' || ! $expectFeature || $afterFeature) {
                                throw new \RuntimeException('Invalid GeoJSON feature list.');
                            }
                            $feature = '{';
                            $featureDepth = 1;
                            $inString = false;
                            $escaped = false;
                            $expectFeature = false;

                            continue;
                        }
                        $feature .= $char;
                        if (strlen($feature) > 16 * 1024 * 1024) {
                            throw new \RuntimeException('A GeoJSON feature exceeds the 16 MiB processing limit.');
                        }
                        if ($inString) {
                            if ($escaped) {
                                $escaped = false;
                            } elseif ($char === '\\') {
                                $escaped = true;
                            } elseif ($char === '"') {
                                $inString = false;
                            }
                        } elseif ($char === '"') {
                            $inString = true;
                        } elseif ($char === '{' || $char === '[') {
                            $featureDepth++;
                        } elseif ($char === '}' || $char === ']') {
                            $featureDepth--;
                        }
                        if ($featureDepth === 0) {
                            $decoded = json_decode($feature, true, 128, JSON_THROW_ON_ERROR);
                            if (($decoded['type'] ?? null) !== 'Feature' || ! array_key_exists('geometry', $decoded)) {
                                throw new \RuntimeException('Invalid GeoJSON feature.');
                            }
                            if ((! is_null($decoded['geometry']) && ! is_array($decoded['geometry']))
                                || (isset($decoded['properties']) && ! is_array($decoded['properties']))) {
                                throw new \RuntimeException('Invalid GeoJSON geometry or properties.');
                            }
                            yield $decoded;
                            $feature = '';
                            $afterFeature = true;
                            $hasFeature = true;
                        }

                        continue;
                    }
                    $buffer .= $char;
                    if (strlen($buffer) > 1024 * 1024) {
                        throw new \RuntimeException('GeoJSON root metadata exceeds the processing limit.');
                    }
                    if ($inString) {
                        if ($escaped) {
                            $escaped = false;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '"') {
                            $inString = false;
                        }

                        continue;
                    }
                    if ($char === '"') {
                        $inString = true;
                    } elseif ($char === '{' || $char === '[') {
                        $depth++;
                        if ($depth === 2 && $char === '[' && preg_match('/"features"\s*:\s*\[$/s', $buffer)) {
                            if ($foundFeatures) {
                                throw new \RuntimeException('Duplicate GeoJSON features array.');
                            }
                            $foundFeatures = true;
                            $inFeatures = true;
                            // Replace streamed content with [] for final root JSON validation.
                            $buffer .= ']';
                        }
                    } elseif ($char === '}' || $char === ']') {
                        $depth--;
                    }
                }
            }
            $root = json_decode($buffer, true, 128, JSON_THROW_ON_ERROR);
            if ($inFeatures || $featureDepth !== 0 || ! $foundFeatures || $depth !== 0
                || ($root['type'] ?? null) !== 'FeatureCollection' || ! is_array($root['features'] ?? null)) {
                throw new \RuntimeException('Expected a valid GeoJSON FeatureCollection.');
            }
        } finally {
            fclose($stream);
        }
    }
}
