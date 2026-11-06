<?php

namespace App\Enums;

enum VectorOutputType: string
{
    case MVT = 'mvt';
    case GEOJSON = 'geojson';
    case FLATGEOBUF = 'flatgeobuf';
}
