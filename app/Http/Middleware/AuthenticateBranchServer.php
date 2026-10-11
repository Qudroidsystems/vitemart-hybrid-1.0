<?php

namespace App\Http\Middleware;

use App\Models\BranchInstallation;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\Signature;
use Closure;
use Illuminate\Http\Request;

/**
 * HQ only: accepts a request from an enrolled branch server (token + HMAC signature).
 * Sets $request->attributes 'installation' and 'branch'.
 */
class AuthenticateBranchServer
{
    public function handle(Request $request, Closure $next)
    {
        if (!Install::isCentral()) {
            return $this->deny('not_central', 'This server is not a headquarters installation.', 404);
        }

        $uid = (string) $request->header('X-Installation');
        $token = (string) $request->bearerToken();
        $ts = (int) $request->header('X-Timestamp');
        $sig = (string) $request->header('X-Signature');

        $inst = $uid ? BranchInstallation::with('branch')->where('uid', $uid)->first() : null;
        if (!$inst || !$token || !hash_equals($inst->token_hash, hash('sha256', $token))) {
            return $this->deny('unauthenticated', 'Unknown branch server or bad token.', 401);
        }
        if ($inst->status !== 'active') {
            return $this->deny('revoked', 'This branch server has been revoked. Enroll it again.', 401);
        }
        if (!Signature::verify((string) $inst->hmac_secret, $request->method(), '/' . $request->path(), $request->getContent(), $ts, $sig)) {
            return $this->deny('bad_signature', 'Request signature invalid or clock skew above 5 minutes.', 401);
        }
        if ($inst->branch?->status === 'closed') {
            return $this->deny('branch_closed', 'This branch is closed.', 403);
        }

        $request->attributes->set('installation', $inst);
        $request->attributes->set('branch', $inst->branch);

        $inst->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip(),
            'app_version' => $request->header('X-App-Version') ?: $inst->app_version])->saveQuietly();

        return $next($request);
    }

    protected function deny(string $code, string $message, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
