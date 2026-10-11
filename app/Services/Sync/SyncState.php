<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Small key/value store for sync bookkeeping (cursor, last push / pull, last error). */
class SyncState
{
    public static function get(string $key, $default = null)
    {
        if (!Schema::hasTable('sync_state')) {
            return $default;
        }
        $v = DB::table('sync_state')->where('key', $key)->value('value');
        return $v === null ? $default : json_decode($v, true);
    }

    public static function set(string $key, $value): void
    {
        DB::table('sync_state')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now()]);
    }

    public static function all(): array
    {
        return Schema::hasTable('sync_state')
            ? DB::table('sync_state')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all()
            : [];
    }
}
