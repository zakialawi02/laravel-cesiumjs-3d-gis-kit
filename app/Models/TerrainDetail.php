<?php

namespace App\Models;

use App\Enums\TerrainOutputType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerrainDetail extends Model
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
        'ortho_file_id',
        'has_ortho',
        'native_bounding_box',
        'output_type',
        'tileset_path',
        'tileset_url',
        'acquisition_date',
        'srs',
        'ortho_tiles_path',
        'ortho_min_zoom',
        'ortho_max_zoom',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'ortho_tiles_url',
        'has_ortho_tiles',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'output_type' => TerrainOutputType::class,
        'has_ortho' => 'boolean',
        'native_bounding_box' => 'array',
        'acquisition_date' => 'date:Y-m-d',
    ];

    /**
     * Get the layer that owns these details.
     */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class, 'layer_id');
    }

    /**
     * Get the source file for this terrain.
     */
    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'source_file_id');
    }

    /**
     * Get the ortho file for this terrain.
     */
    public function orthoFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'ortho_file_id');
    }

    /**
     * Get the full tileset URL.
     */
    public function getTilesetUrlAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return url($value);
    }

    /**
     * Get the ortho tiles serve URL pattern.
     */
    public function getOrthoTilesUrlAttribute(): ?string
    {
        if (empty($this->ortho_tiles_path)) {
            return null;
        }

        // Extract the layerId from the ortho_tiles_path (e.g., "xyz-tiles/01ktdv6dhg199c0d5cmca83we5_ortho")
        $layerId = basename($this->ortho_tiles_path);

        return url("geodata/ortho/{$layerId}/{z}/{x}/{y}.png");
    }

    /**
     * Get whether the terrain has ortho tiles.
     */
    public function getHasOrthoTilesAttribute(): bool
    {
        return ! empty($this->ortho_tiles_path);
    }
}
