<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Gives a model a permanent, globally unique ULID in `uid`.
 * The integer `id` stays private to each database; only `uid` crosses servers.
 */
trait HasUid
{
    public static function bootHasUid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::ulid();
            }
        });
    }

    public static function findByUid(?string $uid): ?static
    {
        return $uid ? static::where('uid', $uid)->first() : null;
    }

    public static function idForUid(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }
        static $cache = [];
        $key = static::class . ':' . $uid;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = static::where('uid', $uid)->value('id');
        }
        return $cache[$key];
    }
}
