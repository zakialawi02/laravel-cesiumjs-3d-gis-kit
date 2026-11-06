<?php

namespace App\Enums;

enum SourceFileStatus: string
{
    case UPLOADING = 'uploading';
    case UPLOADED = 'uploaded';
    case VALIDATING = 'validating';
    case READY = 'ready';
    case INVALID = 'invalid';
}
