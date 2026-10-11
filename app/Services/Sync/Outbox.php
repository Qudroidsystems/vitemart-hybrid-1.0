<?php

namespace App\Services\Sync;

use App\Models\Product;
use App\Models\User;
use App\Services\Catalogue\ProductPayload;
use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Branch side: every sale, refund, stock movement, shift, customer … is written to sync_outbox in the
 * same database transaction as the change itself, then pushed to head office in order.
 * Nothing is lost if the internet is down – it waits in the outbox.
 */
class Outbox
{
    /** Record a model change (called from model events). */
    public static function record($model, string $op = 'upsert'): void
    {
        if (!Install::isBranch() || SyncContext::applying()) {
            return;
        }
        $entity = Registry::upEntityFor(get_class($model));
        if (!$entity || empty($model->uid)) {
            return;
        }
        if ($entity === 'product' && ($model->owner ?? 'branch') === 'central') {
            return; // head office's products travel the other way
        }
        static::recordEntity($entity, $model->uid, static::payloadFor($entity, $model), $op);
    }

    public static function payloadFor(string $entity, $model): array
    {
        $def = Registry::UP[$entity];
        if ($entity === 'product') {
            return ProductPayload::export($model->fresh(['packs.unit', 'variations', 'category', 'brand']));
        }
        if ($entity === 'user') {
            return [
                'uid' => $model->uid, 'first_name' => $model->first_name, 'last_name' => $model->last_name,
                'username' => $model->username, 'email' => $model->email, 'phone_number' => $model->phone_number,
                'password' => $model->getAttributes()['password'] ?? $model->password,
                'roles' => method_exists($model, 'getRoleNames') ? $model->getRoleNames()->all() : [],
            ];
        }
        $row = DB::table($def['table'])->where('id', $model->getKey())->first();
        $payload = RowCodec::encode($def, $row ?? $model->getAttributes(), true);
        if ($entity === 'customer') {
            $payload['__points'] = (int) DB::table('customer_points')->where('customer_id', $model->getKey())->value('points');
        }
        return $payload;
    }

    public static function recordEntity(string $entity, string $uid, array $payload, string $op = 'upsert', bool $force = false): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Several saves of the same record before it is sent → send the latest only.
        $pending = DB::table('sync_outbox')->where('entity', $entity)->where('entity_uid', $uid)
            ->where('status', 'pending')->where('attempts', 0)->orderByDesc('id')->first();
        if ($pending && $op === 'upsert' && $pending->op === 'upsert') {
            DB::table('sync_outbox')->where('id', $pending->id)->update(['payload' => $json, 'event_uid' => (string) Str::ulid()]);
            return;
        }
        if ($entity === 'uid_alias') {
            // events waiting for this record can go again straight away
            DB::table('sync_outbox')->where('status', 'pending')->where('attempts', '>', 0)->update(['next_attempt_at' => null]);
        }
        DB::table('sync_outbox')->insert([
            'event_uid' => (string) Str::ulid(),
            'entity' => $entity,
            'entity_uid' => $uid,
            'op' => $op,
            'payload' => $json,
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    /**
     * Send pending events in order.  Returns [sent, failed].
     */
    public static function push(int $maxBatches = 20, bool $quick = false): array
    {
        if (!Install::isBranch()) {
            return [0, 0];
        }
        $size = (int) config('vitemart.sync.batch_size', 500);
        $maxAttempts = (int) config('vitemart.sync.max_attempts', 20);
        $sent = 0;
        $failed = 0;
        $client = app(CentralClient::class)->quick($quick);

        for ($b = 0; $b < $maxBatches; $b++) {
            $rows = DB::table('sync_outbox')->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                ->orderBy('id')->limit($size)->get();
            if ($rows->isEmpty()) {
                break;
            }
            $events = $rows->map(fn ($r) => [
                'event_uid' => $r->event_uid, 'entity' => $r->entity, 'entity_uid' => $r->entity_uid,
                'op' => $r->op, 'payload' => json_decode($r->payload, true), 'created_at' => $r->created_at,
            ])->all();

            $res = $client->post('/api/v1/sync/push', ['events' => $events]);
            if (!$res->successful()) {
                SyncState::set('last_push_error', ['at' => now()->toIso8601String(), 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
                break;
            }
            $results = collect($res->json('results', []))->keyBy('event_uid');
            foreach ($rows as $r) {
                $out = $results[$r->event_uid] ?? ['status' => 'retry', 'error' => 'No answer for this event'];
                if (in_array($out['status'], ['applied', 'duplicate'], true)) {
                    DB::table('sync_outbox')->where('id', $r->id)->where('event_uid', $r->event_uid)
                        ->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                    $sent++;
                } else {
                    $attempts = $r->attempts + 1;
                    DB::table('sync_outbox')->where('id', $r->id)->where('event_uid', $r->event_uid)->update([
                        'attempts' => $attempts,
                        'last_error' => mb_substr((string) ($out['error'] ?? 'failed'), 0, 500),
                        'status' => $attempts >= $maxAttempts || ($out['status'] ?? '') === 'rejected' ? 'dead' : 'pending',
                        'next_attempt_at' => now()->addSeconds(min(3600, 15 * (2 ** min($attempts, 8)))),
                    ]);
                    $failed++;
                }
            }
            SyncState::set('last_push_at', now()->toIso8601String());
            if ($rows->count() < $size) {
                break;
            }
        }
        // Old delivered events are kept a week for troubleshooting.
        DB::table('sync_outbox')->where('status', 'sent')->where('sent_at', '<', now()->subDays(7))->limit(5000)->delete();
        return [$sent, $failed];
    }

    public static function counts(): array
    {
        $c = DB::table('sync_outbox')->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all();
        return ['pending' => (int) ($c['pending'] ?? 0), 'dead' => (int) ($c['dead'] ?? 0), 'sent' => (int) ($c['sent'] ?? 0)];
    }

    /**
     * First connection: queue what this branch already has, oldest first, so head office gets the history.
     * Loyalty point history is not re-sent: each customer's current balance travels with the customer.
     */
    public static function backfill(?string $since = null, ?callable $progress = null): int
    {
        $n = 0;
        $order = ['stock_location', 'cash_register', 'user', 'customer', 'product', 'pos_shift', 'pos_cash_movement', 'order',
            'order_item', 'order_payment', 'refund', 'stock_adjustment', 'stock_adjustment_item', 'stock'];
        foreach ($order as $entity) {
            $def = Registry::UP[$entity];
            $q = DB::table($def['table'])->orderBy('id');
            if ($since && isset(RowCodec::columns($def['table'])['created_at']) && !in_array($entity, ['stock_location', 'cash_register', 'user', 'customer', 'product'], true)) {
                $q->where('created_at', '>=', $since);
            }
            if ($entity === 'product') {
                $q->where('owner', '!=', 'central');
            }
            $q->chunk(500, function ($rows) use ($entity, $def, &$n) {
                DB::transaction(function () use ($rows, $entity, $def, &$n) {
                    foreach ($rows as $row) {
                        if (empty($row->uid)) {
                            continue;
                        }
                        $payload = match ($entity) {
                            'product' => ProductPayload::export(Product::with(['packs.unit', 'variations', 'category', 'brand'])->find($row->id)),
                            'user' => static::payloadFor('user', User::find($row->id)),
                            default => RowCodec::encode($def, $row, true),
                        };
                        if ($entity === 'customer') {
                            $payload['__points'] = (int) DB::table('customer_points')->where('customer_id', $row->id)->value('points');
                        }
                        static::recordEntity($entity, $row->uid, $payload);
                        $n++;
                    }
                });
            });
            if ($progress) {
                $progress($entity, $n);
            }
        }
        return $n;
    }
}
