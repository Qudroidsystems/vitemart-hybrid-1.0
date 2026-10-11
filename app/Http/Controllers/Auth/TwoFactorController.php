<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Security\Totp;
use App\Services\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    /** Step 2 of sign-in. */
    public function challenge(Request $request)
    {
        $user = $request->user();
        if (!TwoFactor::enabled($user) || $request->session()->get(TwoFactor::SESSION_KEY)) {
            return redirect()->intended('/dashboard');
        }
        return view('auth.two-factor-challenge', ['user' => $user]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $user = $request->user();

        $result = TwoFactor::attempt($user, $request->input('code'));
        if (!$result) {
            AuditLogger::log('mfa_failed', 'Wrong two-step sign-in code', 'security', $user, null, ['ip' => $request->ip()], $user);
            return back()->withErrors(['code' => 'That code is not right. Try the newest code in your authenticator app.']);
        }

        $request->session()->regenerate();
        $request->session()->put(TwoFactor::SESSION_KEY, true);
        AuditLogger::log('mfa_passed', $result === 'recovery' ? 'Signed in with a recovery code' : 'Two-step sign-in passed',
            'security', $user, null, ['method' => $result], $user);

        if ($result === 'recovery') {
            $left = count(TwoFactor::recoveryHashes($user));
            session()->flash('warning', "You used a recovery code. {$left} left – create new ones in Two-step sign-in.");
        }
        return redirect()->intended('/dashboard');
    }

    /** Set-up page (also used to manage it once enabled). */
    public function setup(Request $request)
    {
        $user = $request->user();
        if (TwoFactor::enabled($user) && !$request->session()->get(TwoFactor::SESSION_KEY)) {
            return redirect()->route('mfa.challenge');
        }
        $data = ['user' => $user, 'enabled' => TwoFactor::enabled($user), 'required' => TwoFactor::required($user)];

        if (!$data['enabled']) {
            $secret = $request->session()->get('mfa_setup_secret');
            if (!$secret) {
                $secret = Totp::generateSecret();
                $request->session()->put('mfa_setup_secret', $secret);
            }
            $issuer = config('app.name', 'ViteMart');
            $uri = Totp::uri($secret, $user->email ?: $user->username, $issuer);
            $data += ['secret' => trim(chunk_split($secret, 4, ' ')), 'qr' => TwoFactor::qrSvg($uri), 'uri' => $uri];
        }
        $data['recovery'] = session('mfa_recovery_codes');
        $data['recoveryLeft'] = count(TwoFactor::recoveryHashes($user));

        return view('auth.two-factor-setup', $data);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);
        $user = $request->user();
        $secret = $request->session()->get('mfa_setup_secret');
        if (!$secret || Totp::verify($secret, $request->input('code')) === null) {
            return back()->withErrors(['code' => 'That code did not match. Check the phone clock and try the newest code.']);
        }

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();
        $codes = TwoFactor::newRecoveryCodes($user);

        $request->session()->forget('mfa_setup_secret');
        $request->session()->put(TwoFactor::SESSION_KEY, true);
        AuditLogger::log('mfa_enabled', 'Turned on two-step sign-in', 'security', $user, null, null, $user);

        return redirect()->route('mfa.setup')->with('mfa_recovery_codes', $codes)
            ->with('success', 'Two-step sign-in is on. Save your recovery codes now.');
    }

    public function regenerate(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Wrong password.']);
        }
        $codes = TwoFactor::newRecoveryCodes($user);
        AuditLogger::log('mfa_recovery_regenerated', 'Created new recovery codes', 'security', $user, null, null, $user);
        return redirect()->route('mfa.setup')->with('mfa_recovery_codes', $codes);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Wrong password.']);
        }
        if (TwoFactor::required($user)) {
            return back()->withErrors(['password' => 'Two-step sign-in is required for your role and cannot be turned off.']);
        }
        TwoFactor::disable($user);
        AuditLogger::log('mfa_disabled', 'Turned off two-step sign-in', 'security', $user, null, null, $user);
        return redirect()->route('mfa.setup')->with('success', 'Two-step sign-in turned off.');
    }

    /** Super Admin: reset a user who lost their phone and recovery codes. */
    public function reset(Request $request, User $user)
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);
        TwoFactor::disable($user);
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLogger::log('mfa_reset', "Reset two-step sign-in for {$user->email}", 'security', $user, null, null);
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Two-step sign-in reset. They will set it up again at next sign-in.'])
            : back()->with('success', 'Two-step sign-in reset.');
    }
}
