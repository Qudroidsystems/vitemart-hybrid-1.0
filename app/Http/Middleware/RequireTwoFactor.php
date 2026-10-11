<?php

namespace App\Http\Middleware;

use App\Services\Security\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 *  - Signed in with a password but not the code yet → only the challenge page is reachable.
 *  - Role requires two-step sign-in but it isn't set up → only the set-up page is reachable.
 * Covers "remember me" logins too, because it checks the session, not the login request.
 */
class RequireTwoFactor
{
    protected const OPEN_ROUTES = ['mfa.challenge', 'mfa.verify', 'mfa.setup', 'mfa.confirm', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || $request->routeIs(...self::OPEN_ROUTES) || $request->session()->get(TwoFactor::SESSION_KEY)) {
            return $next($request);
        }

        if (TwoFactor::enabled($user)) {
            return $this->stop($request, 'mfa.challenge', 'Enter your two-step sign-in code.');
        }
        if (TwoFactor::required($user)) {
            return $this->stop($request, 'mfa.setup', 'Your role needs two-step sign-in. Set it up to continue.');
        }

        return $next($request);
    }

    protected function stop(Request $request, string $route, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'redirect' => route($route)], 423);
        }
        if ($request->isMethod('GET')) {
            redirect()->setIntendedUrl($request->fullUrl());
        }
        return redirect()->route($route);
    }
}
