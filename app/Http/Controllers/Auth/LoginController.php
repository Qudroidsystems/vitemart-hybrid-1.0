<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginPolicy;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Allow login with either email or username.
     */
    protected function credentials(Request $request)
    {
        $login = $request->input($this->username());
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        return [$field => $login, 'password' => $request->input('password')];
    }

    /**
     * Runs after the password is verified – enforce login hours / account status.
     */
    protected function authenticated(Request $request, $user)
    {
        $policy = app(LoginPolicy::class);

        if ($reason = $policy->denialReason($user)) {
            AuditLogger::log('login_blocked', $reason, 'security', $user, null, ['email' => $user->email], $user);
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([$this->username() => [$reason]]);
        }

        $request->session()->put('login_at', time());
        $request->session()->put('last_seen', time());

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        // One active session per user
        if (\App\Models\SystemSetting::get('single_session', false) && !LoginPolicy::isSuperAdmin($user)) {
            DB::table('sessions')->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
        }

        AuditLogger::log('login', 'Logged in', 'security', $user, null, ['ip' => $request->ip()], $user);

        // Cashiers go straight to the till.
        if (!$user->can('dashboard') && $user->can('View pos')) {
            return redirect()->intended(route('pos.index'));
        }

        return redirect()->intended($this->redirectPath());
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $login = $request->input($this->username());
        $user = User::where('email', $login)->orWhere('username', $login)->first();
        AuditLogger::log('login_failed', 'Failed login attempt for "' . mb_substr((string) $login, 0, 100) . '"', 'security',
            $user, null, ['login' => $login], null);

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            AuditLogger::log('logout', 'Logged out', 'security', $user, null, null, $user);
        }

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->wantsJson() ? response()->noContent() : redirect('/login');
    }
}
