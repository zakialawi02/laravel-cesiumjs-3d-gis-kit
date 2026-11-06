<?php

namespace App\Models;

use App\Enums\SourceFileStatus;
use App\Enums\SourceFileType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceFile extends Model
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'original_name',
        'stored_name',
        'file_type',
        'mime_type',
        'file_size',
        'storage_path',
        'checksum_sha256',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_type' => SourceFileType::class,
        'status' => SourceFileStatus::class,
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get the conversion jobs that use this source file as the primary source.
     */
    public function conversionJobs(): HasMany
    {
        return $this->hasMany(ConversionJob::class, 'source_file_id');
    }

    /**
     * Get the conversion jobs that use this source file as an orthophoto.
     */
    public function orthoConversionJobs(): HasMany
    {
        return $this->hasMany(ConversionJob::class, 'ortho_file_id');
    }
}
