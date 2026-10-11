<?php

namespace App\Services\MultiBranch;

/**
 * Request signing between a branch server and HQ.
 *
 *   X-Installation: <installation uid>
 *   Authorization:  Bearer <token>
 *   X-Timestamp:    unix seconds
 *   X-Signature:    hex HMAC-SHA256(secret, "<ts>\n<METHOD>\n<path>\n<sha256(body)>")
 */
class Signature
{
    public const MAX_SKEW = 300;

    public static function make(string $secret, string $method, string $path, string $body, int $timestamp): string
    {
        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $base = $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $body);
        return hash_hmac('sha256', $base, $secret);
    }

    public static function verify(string $secret, string $method, string $path, string $body, int $timestamp, string $signature): bool
    {
        if (abs(time() - $timestamp) > self::MAX_SKEW) {
            return false;
        }
        return hash_equals(self::make($secret, $method, $path, $body, $timestamp), $signature);
    }
}
