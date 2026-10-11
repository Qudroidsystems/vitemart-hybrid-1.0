<?php

namespace App\Services\Security;

/**
 * Time-based one-time passwords (RFC 6238, SHA-1, 6 digits, 30 s) – works with
 * Google Authenticator, Microsoft Authenticator, Authy, 1Password …
 */
class Totp
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return static::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string
    {
        $counter = intdiv($time ?? time(), $period);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), static::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts the current code and one step either side (clock drift).
     * Returns the matched time-step so callers can refuse a replay of the same code.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        $time ??= time();
        for ($i = -$window; $i <= $window; $i++) {
            $t = $time + $i * 30;
            if (hash_equals(static::code($secret, $t), $code)) {
                return intdiv($t, 30);
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $data));
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
