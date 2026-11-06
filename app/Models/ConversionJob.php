<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\LayerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConversionJob extends Model
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'layer_type',
        'source_file_id',
        'ortho_file_id',
        'status',
        'conversion_tool',
        'attempts',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'layer_type' => LayerType::class,
        'status' => JobStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Get the source file for this job.
     */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'source_file_id');
    }

    /**
     * Get the ortho file for this job.
     */
    public function orthoFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'ortho_file_id');
    }

    /**
     * Get the logs for this job.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ConversionLog::class, 'job_id');
    }

    /**
     * Get the layer produced by this job.
     */
    public function layer(): HasOne
    {
        return $this->hasOne(Layer::class, 'job_id');
    }
}
