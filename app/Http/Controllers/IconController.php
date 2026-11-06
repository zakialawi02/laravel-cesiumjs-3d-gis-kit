<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IconController extends Controller
{
    /**
     * Folders (under icons/ on the public disk) that may be served, and the accepted file names.
     */
    public const PATH_PATTERN = '(layer-categories)/[A-Za-z0-9]{1,64}\.(png|webp)';

    /**
     * Serve an uploaded icon to authenticated users without exposing the rest of the public disk.
     */
    public function show(string $path): Response
    {
        abort_unless(preg_match('#^'.self::PATH_PATTERN.'$#', $path) === 1, 404);

        $disk = Storage::disk('public');
        $storedPath = 'icons/'.$path;
        abort_unless($disk->exists($storedPath), 404);

        return response($disk->get($storedPath), 200, [
            'Content-Type' => str_ends_with($path, '.webp') ? 'image/webp' : 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Public URL for a stored icon path such as "icons/layer-categories/abc.png", or null when it is not a served icon.
     */
    public static function urlFor(?string $storedPath): ?string
    {
        if ($storedPath === null || ! str_starts_with($storedPath, 'icons/')) {
            return null;
        }

        $path = substr($storedPath, strlen('icons/'));

        return preg_match('#^'.self::PATH_PATTERN.'$#', $path) === 1 ? route('icons.show', ['path' => $path]) : null;
    }
}
