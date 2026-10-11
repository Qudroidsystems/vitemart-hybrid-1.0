<?php

namespace App\Http\Controllers;

use App\Models\RoleLoginRule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginPolicy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Super-admin control over who can be logged in and when.
 */
class SecurityController extends Controller
{
    public function __construct(protected LoginPolicy $policy)
    {
        $this->middleware('permission:Manage login control|View active sessions|Force logout users', ['only' => ['index']]);
        $this->middleware('permission:Manage login control', ['only' => ['updateSettings', 'updateRoleRule', 'updateUserRule', 'scheduleLogout', 'setPin']]);
        $this->middleware('permission:Force logout users', ['only' => ['logoutAll', 'logoutUser', 'endSession']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Login Control & Sessions';

        $settings = [];
        foreach (['login_hours_enabled', 'login_start_time', 'login_end_time', 'login_days', 'auto_logout_enabled',
                     'auto_logout_time', 'idle_timeout_minutes', 'single_session', 'maintenance_lock', 'maintenance_message',
                     'scheduled_logout_at', 'sessions_valid_after', 'adjustment_requires_approval', 'block_sale_below_cost',
                     'max_discount_percent', 'require_open_shift', 'allow_negative_stock', 'barcode_prefix',
                     'scale_barcode_enabled', 'scale_barcode_mode'] as $k) {
            $settings[$k] = SystemSetting::get($k);
        }

        $lifetime = (int) config('session.lifetime', 120);
        $sessions = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')
            ->where('sessions.last_activity', '>=', now()->subMinutes($lifetime)->timestamp)
            ->orderByDesc('sessions.last_activity')
            ->get(['sessions.id', 'sessions.user_id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity',
                   'users.first_name', 'users.last_name', 'users.email', 'users.last_login_at']);

        $roles = Role::orderBy('name')->get();
        $roleRules = RoleLoginRule::all()->keyBy('role_id');

        $users = User::with('roles:id,name')
            ->whereHas('roles')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'is_active', 'login_rule', 'login_start_time', 'login_end_time',
                   'login_days', 'last_login_at', 'last_login_ip', 'pos_pin', 'max_discount_percent']);

        $onlineIds = $sessions->pluck('user_id')->unique()->all();
        $currentSession = $request->session()->getId();
        $days = LoginPolicy::DAYS;

        $recentEvents = \App\Models\AuditLog::where('module', 'security')
            ->whereIn('event', ['login', 'logout', 'login_failed', 'login_blocked', 'force_logout'])
            ->latest('created_at')->limit(20)->get();

        return view('security.index', compact('pagetitle', 'settings', 'sessions', 'roles', 'roleRules', 'users',
            'onlineIds', 'currentSession', 'days', 'recentEvents'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'login_hours_enabled' => 'nullable|boolean',
            'login_start_time' => 'nullable|date_format:H:i',
            'login_end_time' => 'nullable|date_format:H:i',
            'login_days' => 'nullable|array',
            'login_days.*' => 'integer|between:1,7',
            'auto_logout_enabled' => 'nullable|boolean',
            'auto_logout_time' => 'nullable|date_format:H:i',
            'idle_timeout_minutes' => 'nullable|integer|min:0|max:1440',
            'single_session' => 'nullable|boolean',
            'maintenance_lock' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:255',
            'adjustment_requires_approval' => 'nullable|boolean',
            'block_sale_below_cost' => 'nullable|boolean',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'require_open_shift' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'barcode_prefix' => 'nullable|digits_between:2,3',
            'scale_barcode_enabled' => 'nullable|boolean',
            'scale_barcode_mode' => 'nullable|in:weight,price',
            'section' => 'nullable|string',
        ]);

        $section = $data['section'] ?? 'login';
        unset($data['section']);

        $booleans = [
            'login' => ['login_hours_enabled', 'auto_logout_enabled', 'single_session', 'maintenance_lock'],
            'operations' => ['adjustment_requires_approval', 'block_sale_below_cost', 'require_open_shift', 'allow_negative_stock', 'scale_barcode_enabled'],
        ][$section] ?? [];
        foreach ($booleans as $b) {
            $data[$b] = $request->boolean($b);
        }
        if ($section === 'login') {
            $data['login_days'] = array_map('intval', $request->input('login_days', []));
            $data['idle_timeout_minutes'] = (int) ($data['idle_timeout_minutes'] ?? 0);
        }
        if (isset($data['max_discount_percent'])) {
            $data['max_discount_percent'] = (float) $data['max_discount_percent'];
        }

        $old = [];
        foreach (array_keys($data) as $k) {
            $old[$k] = SystemSetting::get($k);
        }
        SystemSetting::setMany($data);
        AuditLogger::log('settings', 'Updated ' . $section . ' settings', 'security', ['SystemSetting', $section, ucfirst($section) . ' settings'], $old, $data);

        return back()->with('success', 'Settings saved.');
    }

    public function updateRoleRule(Request $request, Role $role)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'start_time' => 'nullable|required_if:enabled,1|date_format:H:i',
            'end_time' => 'nullable|required_if:enabled,1|date_format:H:i',
            'days' => 'nullable|array',
        ]);

        $old = RoleLoginRule::where('role_id', $role->id)->first()?->toArray();
        $rule = RoleLoginRule::updateOrCreate(['role_id' => $role->id], [
            'enabled' => $request->boolean('enabled'),
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'days' => array_map('intval', $data['days'] ?? []),
        ]);
        AuditLogger::log('settings', "Login hours for role {$role->name} " . ($rule->enabled ? 'set' : 'disabled'), 'security',
            $role, $old, $rule->toArray());

        return back()->with('success', "Login hours for {$role->name} saved.");
    }

    public function updateUserRule(Request $request, User $user)
    {
        $data = $request->validate([
            'is_active' => 'nullable|boolean',
            'login_rule' => 'required|in:inherit,custom,always,blocked',
            'login_start_time' => 'nullable|required_if:login_rule,custom|date_format:H:i',
            'login_end_time' => 'nullable|required_if:login_rule,custom|date_format:H:i',
            'login_days' => 'nullable|array',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($user->id === auth()->id() && (!$request->boolean('is_active') || $data['login_rule'] === 'blocked')) {
            return back()->with('error', 'You cannot block or disable your own account.');
        }

        $user->update([
            'is_active' => $request->boolean('is_active'),
            'login_rule' => $data['login_rule'],
            'login_start_time' => $data['login_start_time'] ?? null,
            'login_end_time' => $data['login_end_time'] ?? null,
            'login_days' => array_map('intval', $data['login_days'] ?? []),
            'max_discount_percent' => $data['max_discount_percent'] ?? null,
        ]);

        // A disabled / blocked user is signed out immediately.
        if (!$user->is_active || $user->login_rule === 'blocked') {
            $this->policy->logoutUser($user);
        }

        return back()->with('success', "Login settings for {$user->first_name} saved.");
    }

    public function logoutAll(Request $request)
    {
        $count = $this->policy->logoutEveryone($request->user());
        AuditLogger::log('force_logout', "Logged out all users ({$count} active sessions ended)", 'security');

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => "All users have been logged out ({$count} sessions)."])
            : back()->with('success', "All users have been logged out ({$count} sessions).");
    }

    public function logoutUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Use the normal logout to sign yourself out.');
        }
        $count = $this->policy->logoutUser($user);
        AuditLogger::log('force_logout', "Logged out {$user->first_name} {$user->last_name} ({$count} sessions)", 'security', $user);

        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => "{$user->first_name} has been logged out."])
            : back()->with('success', "{$user->first_name} has been logged out.");
    }

    public function endSession(Request $request, string $session)
    {
        if ($session === $request->session()->getId()) {
            return back()->with('error', 'That is your current session.');
        }
        $row = DB::table('sessions')->where('id', $session)->first();
        DB::table('sessions')->where('id', $session)->delete();
        if ($row && $row->user_id) {
            User::whereKey($row->user_id)->update(['remember_token' => null]);
        }
        AuditLogger::log('force_logout', 'Ended a session' . ($row ? " from {$row->ip_address}" : ''), 'security',
            $row?->user_id ? User::find($row->user_id) : null);

        return back()->with('success', 'Session ended.');
    }

    public function scheduleLogout(Request $request)
    {
        $data = $request->validate([
            'scheduled_logout_at' => 'nullable|date|after:now',
            'clear' => 'nullable|boolean',
        ]);

        if ($request->boolean('clear')) {
            SystemSetting::set('scheduled_logout_at', null);
            AuditLogger::log('settings', 'Cancelled scheduled logout', 'security');
            return back()->with('success', 'Scheduled logout cancelled.');
        }

        $at = Carbon::parse($data['scheduled_logout_at']);
        SystemSetting::set('scheduled_logout_at', $at->toDateTimeString());
        AuditLogger::log('settings', 'Scheduled logout of all users at ' . $at->format('d M Y h:i A'), 'security');

        return back()->with('success', 'Everyone will be logged out at ' . $at->format('d M Y, h:i A') . '.');
    }

    /** Supervisor PIN used to authorise discounts / price overrides / voids at the till. */
    public function setPin(Request $request, User $user)
    {
        $data = $request->validate(['pin' => 'nullable|digits_between:4,6']);
        $user->forceFill(['pos_pin' => $data['pin'] ? Hash::make($data['pin']) : null])->save();
        AuditLogger::log('settings', ($data['pin'] ? 'Set' : 'Removed') . " supervisor PIN for {$user->first_name}", 'security', $user);

        return back()->with('success', 'Supervisor PIN ' . ($data['pin'] ? 'saved' : 'removed') . '.');
    }
}
