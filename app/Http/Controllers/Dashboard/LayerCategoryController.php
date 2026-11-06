<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveLayerCategoryRequest;
use App\Models\Layer;
use App\Models\LayerCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LayerCategoryController extends Controller
{
    private const PROTECTED_SLUG = 'other';

    private const ICON_DIR = 'icons/layer-categories';

    /**
     * List categories with their layer counts.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        return view('pages.dashboard.layer-categories.index', [
            'categories' => LayerCategory::query()
                ->withCount('layers')
                ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'search' => $search,
            'protectedSlug' => self::PROTECTED_SLUG,
        ]);
    }

    public function store(SaveLayerCategoryRequest $request): RedirectResponse
    {
        $category = new LayerCategory($request->safe()->only(['name', 'color', 'description']));
        $category->sort_order = (int) $request->validated('sort_order', 0);
        $category->slug = $this->uniqueSlug($category->name);

        if ($request->hasFile('icon')) {
            $category->icon_path = $request->file('icon')->store(self::ICON_DIR, 'public');
        }

        $category->save();

        return back()->with('success', "Category \"{$category->name}\" created.");
    }

    public function update(SaveLayerCategoryRequest $request, LayerCategory $category): RedirectResponse
    {
        $category->fill($request->safe()->only(['name', 'color', 'description']));
        $category->sort_order = (int) $request->validated('sort_order', 0);

        if ($request->boolean('remove_icon') || $request->hasFile('icon')) {
            $this->deleteIcon($category->icon_path);
            $category->icon_path = $request->hasFile('icon') ? $request->file('icon')->store(self::ICON_DIR, 'public') : null;
        }

        $category->save();

        return back()->with('success', "Category \"{$category->name}\" updated.");
    }

    /**
     * Delete a category; its layers move to the protected "Other" category in the same transaction.
     */
    public function destroy(LayerCategory $category): RedirectResponse
    {
        abort_if($category->slug === self::PROTECTED_SLUG, 422, 'The default "Other" category cannot be deleted.');

        $moved = DB::transaction(function () use ($category): int {
            $otherId = LayerCategory::query()->where('slug', self::PROTECTED_SLUG)->value('id');
            $moved = Layer::query()->where('layer_category_id', $category->id)->update(['layer_category_id' => $otherId]);
            $category->delete();

            return $moved;
        });

        $this->deleteIcon($category->icon_path);

        return back()->with('success', "Category \"{$category->name}\" deleted".($moved ? " and {$moved} layer(s) moved to Other." : '.'));
    }

    /**
     * Only delete files this controller stored (server-generated names inside the icon folder).
     */
    private function deleteIcon(?string $path): void
    {
        if ($path !== null && preg_match('#^'.self::ICON_DIR.'/[A-Za-z0-9]{1,64}\.(png|webp)$#', $path) === 1) {
            Storage::disk('public')->delete($path);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($i = 2; LayerCategory::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
