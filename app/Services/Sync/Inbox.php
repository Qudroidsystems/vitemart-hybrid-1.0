<?php

namespace App\Services\Sync;

use App\Models\Branch;
use App\Models\BranchInstallation;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalogue\ProductPayload;
use App\Services\Inventory\InventoryBalances;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Head office side: applies events pushed by a branch server.
 * Each event is applied at most once (sync_inbox.event_uid) and in its own savepoint,
 * so one bad event doesn't block the rest of the batch.
 */
class Inbox
{
    protected array $touchedProducts = [];
    protected array $touchedCustomers = [];

    public function __construct(protected InventoryBalances $balances, protected StockService $stock)
    {
    }

    /** @return array<int, array{event_uid:string, status:string, error?:string}> */
    public function receive(Branch $branch, ?BranchInstallation $inst, array $events): array
    {
        $results = [];
        RowCodec::flush();

        // "old uid = new uid" notices first, so the rest of the batch can use them.
        usort($events, fn ($a, $b) => (($b['entity'] ?? '') === 'uid_alias') <=> (($a['entity'] ?? '') === 'uid_alias'));

        SyncContext::run(function () use ($branch, $inst, $events, &$results) {
            foreach ($events as $e) {
                $uid = (string) ($e['event_uid'] ?? '');
                if (strlen($uid) !== 26 || !isset(Registry::UP[$e['entity'] ?? ''])) {
                    $results[] = ['event_uid' => $uid, 'status' => 'rejected', 'error' => 'Unknown event type ' . ($e['entity'] ?? '?')];
                    continue;
                }
                $seen = DB::table('sync_inbox')->where('event_uid', $uid)->first();
                if ($seen && $seen->status === 'applied') {
                    $results[] = ['event_uid' => $uid, 'status' => 'duplicate'];
                    continue;
                }
                try {
                    DB::transaction(fn () => $this->apply($branch, $e['entity'], (array) $e['payload'], $e['op'] ?? 'upsert'));
                    $status = ['event_uid' => $uid, 'status' => 'applied'];
                } catch (MissingReference $m) {
                    $status = ['event_uid' => $uid, 'status' => 'retry', 'error' => $m->getMessage()];
                } catch (\Illuminate\Validation\ValidationException $v) {
                    $status = ['event_uid' => $uid, 'status' => 'retry', 'error' => collect($v->errors())->flatten()->first()];
                } catch (\Throwable $t) {
                    report($t);
                    $status = ['event_uid' => $uid, 'status' => 'retry', 'error' => mb_substr(class_basename($t) . ': ' . $t->getMessage(), 0, 480)];
                }
                $results[] = $status;
                DB::table('sync_inbox')->updateOrInsert(['event_uid' => $uid], [
                    'branch_id' => $branch->id, 'branch_installation_id' => $inst?->id,
                    'entity' => $e['entity'], 'entity_uid' => (string) ($e['entity_uid'] ?? ''),
                    'status' => $status['status'] === 'applied' ? 'applied' : 'failed',
                    'error' => $status['error'] ?? null,
                    'attempts' => DB::raw('attempts + ' . ($seen ? 1 : 0)),
                    'updated_at' => now(), 'created_at' => $seen->created_at ?? now(),
                ]);
            }
        });

        // Keep the head-office stock figures right and tell branches about loyalty balances.
        foreach (array_keys($this->touchedProducts) as $pid) {
            $this->stock->refreshCache($pid);
        }
        foreach (array_keys($this->touchedCustomers) as $uid) {
            Feed::mark('customer', $uid);
        }
        $inst?->forceFill(['last_push_at' => now()])->saveQuietly();
        return $results;
    }

    public function apply(Branch $branch, string $entity, array $payload, string $op): void
    {
        $def = Registry::UP[$entity];
        match ($entity) {
            'uid_alias' => UidAliases::store((string) $payload['table'], (string) $payload['alias_uid'], (string) $payload['target_uid'], $branch->id, 'adopted at branch'),
            'transfer' => $this->applyDocument('transfer', $payload),
            'purchase_order' => $this->applyDocument('purchase_order', $payload),
            'product' => $this->applyProduct($branch, $payload),
            'user' => $this->applyUser($branch, $payload),
            'customer' => $this->applyCustomer($branch, $def, $payload),
            default => $this->applyRow($branch, $entity, $def, $payload),
        };
    }

    protected function applyRow(Branch $branch, string $entity, array $def, array $payload): void
    {
        $values = RowCodec::decode($def, $payload);
        if (isset(RowCodec::columns($def['table'])['branch_id'])) {
            $values['branch_id'] = $branch->id;
        }
        if ($entity === 'stock_location' && !empty($values['code'])) {
            // keep codes readable at head office: "IKJ-MAIN"
            $values['code'] = str_starts_with($values['code'], $branch->code . '-') ? $values['code'] : $branch->code . '-' . $values['code'];
            $values['is_default'] = 0;
        }
        [$id, $created] = RowCodec::upsert($def, $values, 'alias', $branch->id);

        if ($entity === 'stock' && $created) {
            $signed = in_array($values['type'], StockService::IN_TYPES, true) ? (float) $values['quantity'] : -(float) $values['quantity'];
            $this->balances->apply((int) $values['stock_location_id'], (int) $values['product_id'], $values['product_variation_id'] ?? null, $signed);
            $this->touchedProducts[(int) $values['product_id']] = true;
        }
        if ($entity === 'customer_point_tx' && $created) {
            $cid = (int) $values['customer_id'];
            $delta = (int) ($values['points_earned'] ?? 0) - (int) ($values['points_redeemed'] ?? 0);
            $row = DB::table('customer_points')->where('customer_id', $cid)->first();
            if ($row) {
                DB::table('customer_points')->where('id', $row->id)->update(['points' => DB::raw('points + ' . $delta), 'updated_at' => now()]);
            } else {
                DB::table('customer_points')->insert(['uid' => (string) Str::ulid(), 'customer_id' => $cid, 'points' => max(0, $delta), 'points_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('customers')->where('id', $cid)->update(['loyalty_points' => DB::table('customer_points')->where('customer_id', $cid)->value('points')]);
            $this->touchedCustomers[DB::table('customers')->where('id', $cid)->value('uid')] = true;
        }
    }

    protected function applyCustomer(Branch $branch, array $def, array $payload): void
    {
        $points = $payload['__points'] ?? null;
        unset($payload['__points']);
        $values = RowCodec::decode($def, $payload);
        $existing = DB::table('customers')->where('uid', $values['uid'])->first();
        if (!$existing) {
            $values['branch_id'] = $branch->id;
            $values['origin_branch_id'] = $branch->id;
        } else {
            unset($values['branch_id'], $values['loyalty_points']);
            // last change wins for the profile
            if ($existing->updated_at && !empty($values['updated_at']) && $existing->updated_at > $values['updated_at']) {
                return;
            }
        }
        unset($values['outstanding_balance']); // money owed is tracked per branch for now
        [$id, $created] = RowCodec::upsert($def, $values, 'alias', $branch->id);
        if ($created && $points !== null) {
            DB::table('customer_points')->updateOrInsert(['customer_id' => $id],
                ['uid' => (string) Str::ulid(), 'points' => max(0, (int) $points), 'points_value' => 0, 'updated_at' => now(), 'created_at' => now()]);
            DB::table('customers')->where('id', $id)->update(['loyalty_points' => max(0, (int) $points)]);
        }
        $this->touchedCustomers[DB::table('customers')->where('id', $id)->value('uid')] = true;
    }

    /** Transfers / purchase orders: keep the copy that is further along, pass it on to the other branch, post our own stock effects. */
    protected function applyDocument(string $entity, array $payload): void
    {
        [$doc, $changed] = $entity === 'transfer'
            ? \App\Services\Inventory\DocumentPayload::importTransfer($payload)
            : \App\Services\Inventory\DocumentPayload::importPurchaseOrder($payload);
        if ($changed) {
            Feed::mark($entity, $doc->uid);
            if ($entity === 'transfer') {
                SyncContext::defer(fn () => app(\App\Services\Inventory\TransferService::class)->syncEffects($doc->fresh('lines')));
            }
        }
    }

    /** A product the branch created itself: head office keeps it as that branch's local product. */
    protected function applyProduct(Branch $branch, array $payload): void
    {
        $existing = Product::where('uid', $payload['uid'] ?? '')->first();
        if ($existing && $existing->owner === 'central') {
            return; // already adopted into the catalogue
        }
        // Same barcode as a product head office already has → treat the branch's product as that one.
        if (!$existing && !empty($payload['barcode']) && ($row = DB::table('product_barcodes')->where('barcode', $payload['barcode'])->first())) {
            $target = DB::table('products')->where('id', $row->product_id)->value('uid');
            DB::table('sync_uid_aliases')->updateOrInsert(['entity' => 'products', 'alias_uid' => $payload['uid']],
                ['target_uid' => $target, 'branch_id' => $branch->id, 'reason' => 'same barcode ' . $payload['barcode'], 'created_at' => now(), 'updated_at' => now()]);
            foreach (['packs' => 'product_unit', 'variations' => 'product_variations'] as $k => $table) {
                foreach ($payload[$k] ?? [] as $child) {
                    if (!empty($child['barcode']) && ($r = DB::table('product_barcodes')->where('barcode', $child['barcode'])->first())) {
                        $col = $k === 'packs' ? 'product_unit_id' : 'product_variation_id';
                        if ($r->{$col}) {
                            $t = DB::table($table)->where('id', $r->{$col})->value('uid');
                            DB::table('sync_uid_aliases')->updateOrInsert(['entity' => $table, 'alias_uid' => $child['uid']],
                                ['target_uid' => $t, 'branch_id' => $branch->id, 'reason' => 'same barcode', 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
            }
            return;
        }
        // Barcodes clashing with another product are dropped from head office's copy.
        $payload = ProductPayload::stripConflicts($payload);
        ProductPayload::import($payload, 'branch', $branch->id);
    }

    /** Staff created at a branch: head office adopts them (same email/username = same person). */
    protected function applyUser(Branch $branch, array $p): void
    {
        $existing = User::where('uid', $p['uid'])->first()
            ?? User::where('email', $p['email'])->orWhere('username', $p['username'])->first();
        if ($existing) {
            if ($existing->uid !== $p['uid']) {
                DB::table('sync_uid_aliases')->updateOrInsert(['entity' => 'users', 'alias_uid' => $p['uid']],
                    ['target_uid' => $existing->uid, 'branch_id' => $branch->id, 'reason' => 'same email', 'created_at' => now(), 'updated_at' => now()]);
            }
            return;
        }
        $u = new User();
        $u->forceFill([
            'uid' => $p['uid'], 'first_name' => $p['first_name'] ?? '', 'last_name' => $p['last_name'] ?? '',
            'username' => $p['username'], 'email' => $p['email'], 'phone_number' => $p['phone_number'] ?? null,
            'password' => $p['password'] ?? Hash::make(Str::random(32)), 'branch_id' => $branch->id, 'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $u->saveQuietly();
        foreach ($p['roles'] ?? [] as $r) {
            if (Role::where('name', $r)->where('guard_name', 'web')->exists()) {
                $u->assignRole($r);
            }
        }
        Feed::mark('user', $u->uid);
    }
}
