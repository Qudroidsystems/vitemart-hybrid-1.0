<?php

namespace App\Services\Sync;

use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a branch record turns out to be the same thing as a head-office record (same name, phone, barcode …)
 * the branch takes head office's uid.  Events already queued still carry the old uid, so head office is told
 * "old uid = new uid" and translates them.
 */
class UidAliases
{
    public static function adopted(string $table, ?string $oldUid, string $newUid): void
    {
        if (!$oldUid || $oldUid === $newUid || !Schema::hasTable('sync_outbox')) {
            return;
        }
        if (Install::isBranch()) {
            Outbox::recordEntity('uid_alias', $oldUid, ['table' => $table, 'alias_uid' => $oldUid, 'target_uid' => $newUid], 'upsert', true);
        } elseif (Install::isCentral()) {
            static::store($table, $oldUid, $newUid, null, 'adopted');
        }
    }

    public static function store(string $table, string $alias, string $target, ?int $branchId, string $reason): void
    {
        DB::table('sync_uid_aliases')->updateOrInsert(['entity' => $table, 'alias_uid' => $alias],
            ['target_uid' => $target, 'branch_id' => $branchId, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
    }
}
