<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a database row into something another server understands (uids instead of ids) and back.
 * Rows are written with the query builder, so no model events fire while applying sync data.
 */
class RowCodec
{
    protected static array $columns = [];
    protected static array $idCache = [];
    protected static array $uidCache = [];

    public static function flush(): void
    {
        static::$idCache = [];
        static::$uidCache = [];
    }

    public static function columns(string $table): array
    {
        return static::$columns[$table] ??= array_flip(Schema::getColumnListing($table));
    }

    public static function tableOf(string $class): string
    {
        return (new $class())->getTable();
    }

    /** uid of a row by id. */
    public static function uidOf(string $class, $id): ?string
    {
        if ($id === null || $id === '' || $id === 0 || $id === '0') {
            return null;
        }
        $key = $class . ':' . $id;
        if (!array_key_exists($key, static::$uidCache)) {
            static::$uidCache[$key] = DB::table(static::tableOf($class))->where('id', $id)->value('uid');
        }
        return static::$uidCache[$key];
    }

    /** id of a row by uid (following aliases on head office). */
    public static function idOf(string $class, ?string $uid)
    {
        if (!$uid) {
            return null;
        }
        $key = $class . ':' . $uid;
        if (!array_key_exists($key, static::$idCache)) {
            $table = static::tableOf($class);
            $id = DB::table($table)->where('uid', $uid)->value('id');
            if ($id === null && Schema::hasTable('sync_uid_aliases')) {
                $target = DB::table('sync_uid_aliases')->where('entity', $table)->where('alias_uid', $uid)->value('target_uid');
                $id = $target ? DB::table($table)->where('uid', $target)->value('id') : null;
            }
            if ($id === null) {
                return null; // don't cache misses: the row may arrive later in the same batch
            }
            static::$idCache[$key] = $id;
        }
        return static::$idCache[$key];
    }

    public static function forgetId(string $class, string $uid): void
    {
        unset(static::$idCache[$class . ':' . $uid]);
    }

    /** A database row → payload. */
    public static function encode(array $def, array|object $row, bool $up = true): array
    {
        $row = (array) $row;
        $out = [];
        $refs = $def['refs'] ?? [];
        $skip = array_flip(array_merge($def['skip'] ?? [], ['two_factor_secret', 'two_factor_recovery_codes', 'remember_token']));
        foreach ($row as $col => $val) {
            if (isset($skip[$col])) {
                continue;
            }
            if ($col === 'id' && empty($def['keep_id'])) {
                continue;
            }
            if ($col === 'branch_id' && $up && !isset($refs['branch_id'])) {
                continue; // the receiving side knows which branch sent it
            }
            if (isset($refs[$col])) {
                $out[$col . '__uid'] = static::uidOf($refs[$col], $val);
                continue;
            }
            if (in_array($col, $def['null'] ?? [], true)) {
                $out[$col] = null;
                continue;
            }
            $out[$col] = $val;
        }
        return $out;
    }

    /**
     * Payload → row values for this server.
     * @throws MissingReference when a referenced row hasn't arrived yet
     */
    public static function decode(array $def, array $payload): array
    {
        $cols = static::columns($def['table']);
        $out = [];
        foreach ($def['refs'] ?? [] as $col => $class) {
            $uid = $payload[$col . '__uid'] ?? null;
            unset($payload[$col . '__uid']);
            if (!isset($cols[$col])) {
                continue;
            }
            if ($uid === null) {
                $out[$col] = null;
                continue;
            }
            $id = static::idOf($class, $uid);
            if ($id === null) {
                throw new MissingReference(class_basename($class), $uid);
            }
            $out[$col] = $id;
        }
        foreach ($payload as $col => $val) {
            if (isset($cols[$col]) && !str_ends_with($col, '__uid')) {
                $out[$col] = is_array($val) ? json_encode($val) : $val;
            }
        }
        if (empty($def['keep_id'])) {
            unset($out['id']);
        }
        return $out;
    }

    /**
     * Insert or update by uid.  If no row has this uid but one matches a natural key (same phone, same name…),
     * that row is adopted (head office: alias; branch: takes the incoming uid).
     *
     * @return array{0: mixed, 1: bool} id, created
     */
    public static function upsert(array $def, array $values, string $mode = 'adopt', ?int $branchId = null): array
    {
        $table = $def['table'];
        $uid = $values['uid'];
        $existing = DB::table($table)->where('uid', $uid)->first();

        if (!$existing && !empty($def['natural'])) {
            foreach ($def['natural'] as $key) {
                $cols = (array) $key;
                if (array_filter($cols, fn ($c) => !isset($values[$c]) || $values[$c] === '' || $values[$c] === null)) {
                    continue;
                }
                $q = DB::table($table);
                foreach ($cols as $c) {
                    $q->where($c, $values[$c]);
                }
                if (!($match = $q->first())) {
                    continue;
                }
                $label = implode('+', $cols);
                if ($mode === 'alias') {
                    // Head office keeps its own record; remember that the branch's uid means this one.
                    DB::table('sync_uid_aliases')->updateOrInsert(
                        ['entity' => $table, 'alias_uid' => $uid],
                        ['target_uid' => $match->uid, 'branch_id' => $branchId, 'reason' => "same {$label}", 'updated_at' => now(), 'created_at' => now()]
                    );
                    return [$match->id, false];
                }
                // Branch: our record becomes head office's record (and head office learns the old uid).
                DB::table($table)->where('id', $match->id)->update(['uid' => $uid]);
                UidAliases::adopted($table, $match->uid, $uid);
                $existing = DB::table($table)->where('id', $match->id)->first();
                break;
            }
        }

        if ($existing) {
            $upd = $values;
            unset($upd['uid']);
            if (empty($def['keep_id'])) {
                unset($upd['id']);
            }
            if ($upd) {
                DB::table($table)->where('id', $existing->id)->update($upd);
            }
            return [$existing->id, false];
        }

        if (empty($def['keep_id'])) {
            unset($values['id']);
            $id = DB::table($table)->insertGetId($values);
        } else {
            DB::table($table)->insert($values);
            $id = $values['id'];
        }
        return [$id, true];
    }
}
