<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * A `redirect` query parameter is only honoured when it points back into this application;
     * anything else (another host, protocol-relative or scheme tricks) is stripped from the URL.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->query->has('redirect')) {
            $redirectPath = $this->internalRedirectPath($request, $request->query('redirect'));

            if ($redirectPath === null) {
                return redirect()->route('login');
            }

            $request->session()->put('url.intended', $redirectPath);
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request (username or email).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $intendedPath = $this->internalRedirectPath($request, $request->session()->pull('url.intended'));

        $request->session()->regenerate();

        return redirect()->to($intendedPath ?? route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Reduce a redirect target to a local path, or null when it leaves this application.
     */
    private function internalRedirectPath(Request $request, mixed $target): ?string
    {
        if (! is_string($target) || $target === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $target)) {
            return null;
        }

        $parts = parse_url($target);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $allowedOrigins = array_filter([
                $request->getSchemeAndHttpHost(),
                rtrim((string) config('app.url'), '/'),
            ]);
            $targetOrigin = ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

            if (! in_array(strtolower($targetOrigin), array_map('strtolower', $allowedOrigins), true)) {
                return null;
            }
        } elseif (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return null;
        }

        $path = '/'.ltrim($parts['path'] ?? '', '/');

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
