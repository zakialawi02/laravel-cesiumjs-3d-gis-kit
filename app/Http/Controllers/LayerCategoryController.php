<?php

namespace App\Http\Controllers;

use App\Models\LayerCategory;
use Illuminate\Http\JsonResponse;

class LayerCategoryController extends Controller
{
    /**
     * List layer categories for the upload form.
     */
    public function list(): JsonResponse
    {
        $categories = LayerCategory::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (LayerCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'icon_url' => $category->icon_url,
            ]);

        return response()->json(['success' => true, 'categories' => $categories]);
    }
}
