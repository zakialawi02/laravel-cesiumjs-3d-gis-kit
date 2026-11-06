<?php

namespace App\Models;

use App\Enums\VectorOutputType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VectorDetail extends Model
{
    use HasFactory, HasUlids;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'layer_id',
        'source_file_id',
        'native_bounding_box',
        'output_type',
        'output_path',
        'srs',
        'source_format',
        'source_epsg',
        'geometry_type',
        'feature_count',
        'acquisition_date',
        'physical_table_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'output_type' => VectorOutputType::class,
        'acquisition_date' => 'date:Y-m-d',
        'native_bounding_box' => 'array',
    ];

    /**
     * Get the layer that owns these details.
     */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class, 'layer_id');
    }

    /**
     * Get the source file for this vector.
     */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'source_file_id');
    }
}
