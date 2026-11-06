<?php

namespace App\Enums;

enum SourceFileType: string
{
    case LAS = 'las';
    case LAZ = 'laz';
    case OBJ = 'obj';
    case GLB = 'glb';
    case GLTF = 'gltf';
    case TIF = 'tif';
    case TIFF = 'tiff';
    case GEOTIFF = 'geotiff';
    case SHAPEFILE = 'shapefile';
    case GEOJSON = 'geojson';
    case DXF = 'dxf';
    case DWG = 'dwg';
    case STR = 'str';
}
