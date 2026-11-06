<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'chunks' => 'array',
            'file_size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
