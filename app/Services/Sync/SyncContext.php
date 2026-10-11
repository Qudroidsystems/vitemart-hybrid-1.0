<?php

namespace App\Services\Sync;

/** While sync data is being applied, local change tracking (outbox / dirty marks / audit) is switched off. */
class SyncContext
{
    protected static int $depth = 0;

    public static function applying(): bool
    {
        return static::$depth > 0;
    }

    public static function run(callable $fn)
    {
        static::$depth++;
        try {
            return \App\Services\AuditLogger::mute($fn);
        } finally {
            static::$depth--;
        }
    }
}
