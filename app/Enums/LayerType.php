<?php

namespace App\Enums;

enum LayerType: string
{
    case TERRAIN = 'terrain';
    case VECTOR = 'vector';
    case IMAGERY = 'imagery';
}
