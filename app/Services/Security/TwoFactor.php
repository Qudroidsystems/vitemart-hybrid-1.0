<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Two-step sign-in for the people who can change prices, stock and money across branches.
 */
class TwoFactor
{
    public const SESSION_KEY = 'mfa_passed';

    public static function enabled(User $user): bool
    {
        return !empty($user->two_factor_secret) && !empty($user->two_factor_confirmed_at);
    }

    /** Must this user set up two-step sign-in before using the system? */
    public static function required(User $user): bool
    {
        $enforce = config('vitemart.mfa_enforce');
        if ($enforce === null) {
            $enforce = Install::isMultiBranch();   // default: on for HQ / branch installs, off for a single store
        }
        if (!$enforce) {
            return false;
        }
        $roles = (array) config('vitemart.mfa_required_roles', []);
        return $roles && $user->hasAnyRole($roles);
    }

    public static function secret(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }
        try {
            return Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Check a 6-digit code (refusing a code that was already used) or a recovery code. */
    public static function attempt(User $user, string $input): bool|string
    {
        $input = trim($input);
        $secret = static::secret($user);
        if (!$secret) {
            return false;
        }

        if (preg_match('/^\d[\d\s]{4,}\d$/', $input)) {
            $step = Totp::verify($secret, $input);
            if ($step === null) {
                return false;
            }
            $key = "mfa_last_step_{$user->id}";
            if ((int) Cache::get($key, 0) >= $step) {
                return false; // replay
            }
            Cache::put($key, $step, 120);
            return 'totp';
        }

        // Recovery code
        $codes = static::recoveryHashes($user);
        $normal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        foreach ($codes as $i => $hash) {
            if (Hash::check($normal, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes)))])->saveQuietly();
                return 'recovery';
            }
        }
        return false;
    }

    /** @return string[] plain codes, shown once */
    public static function newRecoveryCodes(User $user, int $count = 8): array
    {
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(5) . '-' . Str::random(5));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'W', 'L' => 'V']);
            $plain[] = $code;
            $hashes[] = Hash::make(str_replace('-', '', $code));
        }
        $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashes))])->saveQuietly();
        return $plain;
    }

    public static function recoveryHashes(User $user): array
    {
        if (!$user->two_factor_recovery_codes) {
            return [];
        }
        try {
            return json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->saveQuietly();
    }

    public static function qrSvg(string $uri): string
    {
        try {
            return (new \Milon\Barcode\DNS2D())->getBarcodeSVG($uri, 'QRCODE', 5, 5);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
