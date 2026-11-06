<?php

namespace App\Models;

use App\Enums\LayerStatus;
use App\Enums\LayerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Layer extends Model
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'job_id',
        'name',
        'slug',
        'layer_type',
        'status',
        'layer_category_id',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'layer_type' => LayerType::class,
        'status' => LayerStatus::class,
        'published_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'formatted_size',
    ];

    /**
     * Get the user who owns/uploaded this layer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the job that produced this layer.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ConversionJob::class, 'job_id');
    }

    /**
     * Get the category for this layer.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(LayerCategory::class, 'layer_category_id');
    }

    /**
     * Get the category for this layer (backward compatibility alias).
     */
    public function vectorCategory(): BelongsTo
    {
        return $this->category();
    }

    /**
     * Get the terrain details for this layer.
     */
    public function terrainDetail(): HasOne
    {
        return $this->hasOne(TerrainDetail::class, 'layer_id');
    }

    /**
     * Get the vector details for this layer.
     */
    public function vectorDetail(): HasOne
    {
        return $this->hasOne(VectorDetail::class, 'layer_id');
    }

    /**
     * Get the total size of files associated with this layer in bytes.
     */
    public function getTotalSizeAttribute(): int
    {
        $size = 0;
        if ($this->job) {
            if ($this->job->sourceFile) {
                $size += $this->job->sourceFile->file_size;
            }
            if ($this->job->orthoFile) {
                $size += $this->job->orthoFile->file_size;
            }
        }

        return $size;
    }

    /**
     * Get the human-readable formatted total size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->total_size;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
