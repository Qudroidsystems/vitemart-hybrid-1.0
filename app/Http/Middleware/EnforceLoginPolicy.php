<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\Services\LoginPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends sessions that are outside allowed hours, were force-logged-out by an
 * admin, hit the end-of-day logout, or have been idle too long.
 */
class EnforceLoginPolicy
{
    public function __construct(protected LoginPolicy $policy)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user || $request->routeIs('logout')) {
            return $next($request);
        }

        $session = $request->session();
        $loginAt = (int) $session->get('login_at', 0);
        if (!$loginAt) {
            // Session that pre-dates this feature (or "remember me" login).
            $loginAt = time();
            $session->put('login_at', $loginAt);
        }

        $reason = $this->policy->sessionEndReason($user, $loginAt, $session->get('last_seen'));

        if ($reason) {
            AuditLogger::log('force_logout', $reason, 'security', $user, null, null, $user);
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $reason, 'logged_out' => true], 401);
            }
            return redirect()->route('login')->withErrors(['email' => $reason]);
        }

        // Don't count background polling as activity.
        if (!$request->headers->has('X-Background-Poll')) {
            $session->put('last_seen', time());
        }

        return $next($request);
    }
}
