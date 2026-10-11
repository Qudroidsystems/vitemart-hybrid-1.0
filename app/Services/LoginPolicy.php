<?php

namespace App\Services;

use App\Models\RoleLoginRule;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a user may log in / stay logged in right now.
 *
 * Order of precedence:
 *   1. Super Admin – always allowed (so the store can never be locked out).
 *   2. Disabled account / maintenance lock – blocked.
 *   3. Per-user rule: always | blocked | custom window.
 *   4. Per-role windows (user is allowed if ANY of their roles allows it).
 *   5. Global store hours.
 */
class LoginPolicy
{
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public static function isSuperAdmin(?User $user): bool
    {
        return $user && method_exists($user, 'hasRole') && $user->hasRole('Super Admin');
    }

    /** Returns null when allowed, otherwise the message to show. */
    public function denialReason(User $user, ?Carbon $at = null): ?string
    {
        $at = $at ?: now();

        if (static::isSuperAdmin($user)) {
            return null;
        }
        if (isset($user->is_active) && !$user->is_active) {
            return 'Your account has been disabled. Please contact the administrator.';
        }
        if (SystemSetting::get('maintenance_lock', false)) {
            return SystemSetting::get('maintenance_message');
        }
        // Multi-branch: staff may only sign in at the branches they are assigned to.
        if (\App\Services\MultiBranch\Install::isMultiBranch() && method_exists($user, 'canWorkAtBranch')
            && !$user->canWorkAtBranch(\App\Services\MultiBranch\Install::branchId())) {
            return 'Your account is not assigned to this branch.';
        }

        $rule = $user->login_rule ?? 'inherit';
        if ($rule === 'always') {
            return null;
        }
        if ($rule === 'blocked') {
            return 'You are not allowed to log in. Please contact the administrator.';
        }
        if ($rule === 'custom') {
            return $this->inWindow($at, $user->login_start_time, $user->login_end_time, $user->login_days)
                ? null
                : $this->windowMessage($user->login_start_time, $user->login_end_time, $user->login_days);
        }

        // Role rules
        if (Schema::hasTable('role_login_rules')) {
            $roleIds = DB::table('model_has_roles')->where('model_type', get_class($user))
                ->where('model_id', $user->id)->pluck('role_id');
            $rules = RoleLoginRule::whereIn('role_id', $roleIds)->where('enabled', true)->get();
            if ($rules->isNotEmpty()) {
                foreach ($rules as $r) {
                    if ($this->inWindow($at, $r->start_time, $r->end_time, $r->days)) {
                        return null;
                    }
                }
                $r = $rules->first();
                return $this->windowMessage($r->start_time, $r->end_time, $r->days);
            }
        }

        // Global store hours
        if (SystemSetting::get('login_hours_enabled', false)) {
            $s = SystemSetting::get('login_start_time');
            $e = SystemSetting::get('login_end_time');
            $d = SystemSetting::get('login_days');
            if (!$this->inWindow($at, $s, $e, $d)) {
                return $this->windowMessage($s, $e, $d);
            }
        }

        return null;
    }

    public function inWindow(Carbon $at, ?string $start, ?string $end, $days): bool
    {
        $days = is_string($days) ? json_decode($days, true) : $days;
        if (is_array($days) && count($days) > 0 && !in_array((int) $at->isoWeekday(), array_map('intval', $days), true)) {
            return false;
        }
        if (!$start || !$end) {
            return true;
        }
        $now = $at->format('H:i');
        if ($start <= $end) {
            return $now >= $start && $now < $end;
        }
        // Overnight window e.g. 20:00 → 06:00
        return $now >= $start || $now < $end;
    }

    public function windowMessage(?string $start, ?string $end, $days): string
    {
        $days = is_string($days) ? json_decode($days, true) : $days;
        $dayText = is_array($days) && count($days) && count($days) < 7
            ? ' on ' . implode(', ', array_map(fn ($d) => self::DAYS[(int) $d] ?? $d, $days))
            : '';
        $fmt = fn ($t) => $t ? Carbon::createFromFormat('H:i', $t)->format('g:i A') : '';
        return "Login is only allowed between {$fmt($start)} and {$fmt($end)}{$dayText}.";
    }

    /**
     * Checks for an already-running session. Returns a reason when it must end.
     */
    public function sessionEndReason(User $user, int $loginAt, ?int $lastSeen = null): ?string
    {
        $validAfter = (int) SystemSetting::get('sessions_valid_after', 0);
        if ($validAfter && $loginAt < $validAfter) {
            return 'You were logged out by an administrator.';
        }

        if ($user->force_logout_at && $loginAt < $user->force_logout_at->timestamp) {
            return 'You were logged out by an administrator.';
        }

        if (static::isSuperAdmin($user)) {
            return null;
        }

        // One-off scheduled logout
        $scheduled = SystemSetting::get('scheduled_logout_at');
        if ($scheduled) {
            $ts = Carbon::parse($scheduled)->timestamp;
            if (time() >= $ts && $loginAt < $ts) {
                return 'The system logged everyone out at the scheduled time.';
            }
        }

        // Daily automatic logout
        if (SystemSetting::get('auto_logout_enabled', false) && ($t = SystemSetting::get('auto_logout_time'))) {
            $todayCut = Carbon::today()->setTimeFromTimeString($t)->timestamp;
            if (time() >= $todayCut && $loginAt < $todayCut) {
                return 'End-of-day automatic logout. Please log in again tomorrow.';
            }
        }

        // Idle timeout
        $idle = (int) SystemSetting::get('idle_timeout_minutes', 0);
        if ($idle > 0 && $lastSeen && (time() - $lastSeen) > $idle * 60) {
            return "You were logged out after {$idle} minutes of inactivity.";
        }

        return $this->denialReason($user);
    }

    // -----------------------------------------------------------------
    // Admin actions
    // -----------------------------------------------------------------

    public function logoutEveryone(?User $except = null): int
    {
        $now = time();
        SystemSetting::set('sessions_valid_after', $now);
        if ($except) {
            // Keep the admin who pressed the button signed in.
            session()->put('login_at', $now + 1);
        }

        $q = DB::table('sessions');
        if ($except) {
            $q->where('id', '!=', session()->getId());
        }
        $count = $q->whereNotNull('user_id')->delete();

        DB::table('users')->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->update(['remember_token' => null]);

        return $count;
    }

    public function logoutUser(User $user): int
    {
        $user->forceFill(['force_logout_at' => now(), 'remember_token' => null])->saveQuietly();
        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
