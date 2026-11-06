<?php

namespace App\Enums;

enum LayerStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case PROCESS_FAILED = 'process_failed';
    case ARCHIVED = 'archived';
}
