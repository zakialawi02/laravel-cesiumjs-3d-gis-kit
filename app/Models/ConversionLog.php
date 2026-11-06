<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionLog extends Model
{
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'attempt',
        'started_at',
        'finished_at',
        'exit_code',
        'stdout_log',
        'stderr_log',
        'output_size',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempt' => 'integer',
        'exit_code' => 'integer',
        'output_size' => 'integer',
    ];

    /**
     * Get the job that owns this log.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ConversionJob::class, 'job_id');
    }
}
