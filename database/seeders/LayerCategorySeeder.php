<?php

namespace Database\Seeders;

use App\Models\LayerCategory;
use Illuminate\Database\Seeder;

class LayerCategorySeeder extends Seeder
{
    /**
     * Seed the default layer categories. "Other" is the fallback for uncategorized uploads.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Terrain & Orthophoto', 'slug' => 'terrain-orthophoto', 'color' => '#0ea5e9', 'sort_order' => 1],
            ['name' => 'Design', 'slug' => 'desing', 'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'Boundary', 'slug' => 'boundary', 'color' => '#10b981', 'sort_order' => 3],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#6b7280', 'sort_order' => 999],
        ];

        foreach ($categories as $category) {
            LayerCategory::query()->firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
