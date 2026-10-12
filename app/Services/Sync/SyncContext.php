<?php

namespace App\Services\Sync;

/** While sync data is being applied, local change tracking (outbox / dirty marks / audit) is switched off. */
class SyncContext
{
    protected static int $depth = 0;
    protected static array $deferred = [];

    /** Run after sync data has been applied – outside the context, so its own changes are tracked normally. */
    public static function defer(callable $fn): void
    {
        if (static::$depth === 0) {
            $fn();
            return;
        }
        static::$deferred[] = $fn;
    }

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
            if (static::$depth === 0 && static::$deferred) {
                $todo = static::$deferred;
                static::$deferred = [];
                foreach ($todo as $fn) {
                    try {
                        $fn();
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }
    }
}
