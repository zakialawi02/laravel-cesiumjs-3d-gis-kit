<?php

namespace App\Models;

use App\Http\Controllers\IconController;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LayerCategory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name', 'slug', 'description', 'color', 'icon_path', 'sort_order',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function ($cat) {
            $cat->slug = $cat->slug ?: Str::slug($cat->name);
        });
    }

    /**
     * Get the full URL for the icon.
     */
    public function getIconUrlAttribute(): ?string
    {
        return IconController::urlFor($this->icon_path);
    }

    /**
     * Get the layers for the category.
     */
    public function layers(): HasMany
    {
        return $this->hasMany(Layer::class, 'layer_category_id');
    }
}
