<?php

namespace App\Enums;

enum JobStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => __('Queued'),
            self::PROCESSING => __('Processing'),
            self::DONE => __('Done'),
            self::FAILED => __('Failed'),
        };
    }
}
