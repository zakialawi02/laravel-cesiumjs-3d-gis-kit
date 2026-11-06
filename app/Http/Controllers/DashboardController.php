<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Enums\LayerType;
use App\Models\ConversionJob;
use App\Models\Layer;
use App\Models\LayerCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Role-aware overview: admins see every layer and the processing pipeline,
     * engineering users see published layers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $layers = Layer::query()->when(! $isAdmin, fn ($query) => $query->where('status', LayerStatus::PUBLISHED));

        $stats = [
            'layers' => (clone $layers)->count(),
            'terrain_layers' => (clone $layers)->where('layer_type', LayerType::TERRAIN)->count(),
            'vector_layers' => (clone $layers)->where('layer_type', LayerType::VECTOR)->count(),
            'categories' => LayerCategory::count(),
        ];

        if ($isAdmin) {
            $stats['active_jobs'] = ConversionJob::whereIn('status', [JobStatus::QUEUED, JobStatus::PROCESSING])->count();
            $stats['failed_jobs'] = ConversionJob::where('status', JobStatus::FAILED)->count();
            $stats['users'] = User::count();
        }

        return view('pages.dashboard.index', [
            'isAdmin' => $isAdmin,
            'stats' => $stats,
            'recentLayers' => (clone $layers)->with('category:id,name,color')->latest()->take(5)->get(),
            'recentJobs' => $isAdmin
                ? ConversionJob::with(['sourceFile:id,original_name', 'layer:id,job_id,name'])->latest()->take(5)->get()
                : collect(),
        ]);
    }
}
