<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Services\AuditLogger;
use App\Services\MultiBranch\DocumentNumber;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock transfers between branches (and from the head-office warehouse).
 *
 *   requested ─approve→ approved ─dispatch→ dispatched ─receive→ received ─resolve→ closed
 *        └─reject / cancel                                  (no difference) ───────→ closed
 *
 * Each step happens on the server of the branch doing it.  Stock effects are posted on the server
 * they belong to, exactly once (stock_transfer_postings):
 *   sending branch   approve → reserve · dispatch → store ➝ transit · received → transit cleared
 *   receiving branch dispatched → incoming · receive → into store · closed → incoming cleared
 */
class TransferService
{
    public function __construct(protected StockService $stock, protected InventoryBalances $balances, protected LocationService $locations)
    {
    }

    protected function who(): string
    {
        $u = auth()->user();
        return $u ? trim($u->first_name . ' ' . $u->last_name) : 'system';
    }

    protected function here(): ?int
    {
        return Install::branchId();
    }

    /**
     * @param array $lines [{product_id, variation_id?, pack_id?, qty (in packs)}]
     */
    public function create(int $fromBranchId, int $toBranchId, array $lines, ?string $notes = null, bool $approveNow = false): StockTransfer
    {
        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages(['to_branch_id' => 'Choose two different branches.']);
        }
        $here = $this->here();
        if (!Install::isCentral() && !Install::isStandalone() && !in_array($here, [$fromBranchId, $toBranchId], true)) {
            throw ValidationException::withMessages(['from_branch_id' => 'A branch can only create transfers to or from itself.']);
        }
        $rows = $this->cleanLines($lines);
        if (!$rows) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product with a quantity.']);
        }

        $t = DB::transaction(function () use ($fromBranchId, $toBranchId, $rows, $notes, $approveNow, $here) {
            $code = Branch::whereKey($here ?: $fromBranchId)->value('code');
            $t = StockTransfer::create([
                'number' => DocumentNumber::next('transfer', $here, $code),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => 'requested',
                'notes' => $notes,
                'requested_by_name' => $this->who(),
                'requested_at' => now(),
            ]);
            foreach ($rows as $r) {
                StockTransferLine::create($r + ['stock_transfer_id' => $t->id]);
            }
            return $t;
        });
        AuditLogger::log('transfer_requested', "Transfer {$t->number}: {$t->fromBranch->code} → {$t->toBranch->code}, " . count($rows) . ' item(s)', 'inventory');

        if ($approveNow) {
            return $this->approve($t->fresh('lines'));
        }
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    protected function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $product = Product::find($l['product_id'] ?? null);
            $qty = (float) ($l['qty'] ?? 0);
            if (!$product || $qty <= 0) {
                continue;
            }
            if (Install::isMultiBranch() && $product->owner !== 'central') {
                throw ValidationException::withMessages(['lines' => "“{$product->title}” is only in this branch's own catalogue. Offer it to head office first – only catalogue items can move between branches."]);
            }
            $pack = !empty($l['pack_id']) ? ProductUnit::where('product_id', $product->id)->find($l['pack_id']) : null;
            $per = $pack ? max(0.001, (float) $pack->quantity_per_unit) : 1.0;
            $variationId = !empty($l['variation_id']) ? (int) $l['variation_id'] : null;
            $cost = $variationId ? (\App\Models\ProductVariation::find($variationId)?->effective_cost ?? 0) : (float) $product->cost_price;
            $out[] = [
                'product_id' => $product->id, 'product_variation_id' => $variationId, 'product_unit_id' => $pack?->id,
                'qty_per_unit' => $per, 'requested_qty' => round($qty * $per, 3), 'unit_cost' => $cost,
            ];
        }
        return $out;
    }

    protected function canActFor(int $branchId): bool
    {
        return Install::isStandalone() || $this->here() === $branchId;
    }

    protected function bump(StockTransfer $t, array $attrs): StockTransfer
    {
        $t->forceFill($attrs + ['version' => $t->version + 1])->save();
        return $t;
    }

    public function approve(StockTransfer $t, array $qtys = []): StockTransfer
    {
        if ($t->status !== 'requested') {
            throw ValidationException::withMessages(['status' => 'Only a requested transfer can be approved.']);
        }
        if (!Install::isCentral() && !$this->canActFor($t->from_branch_id)) {
            throw ValidationException::withMessages(['status' => 'The sending branch (or head office) approves this transfer.']);
        }
        DB::transaction(function () use ($t, $qtys) {
            foreach ($t->lines as $l) {
                $q = array_key_exists($l->uid, $qtys) ? max(0, (float) $qtys[$l->uid]) * $l->qty_per_unit : $l->requested_qty;
                $l->update(['approved_qty' => round($q, 3)]);
            }
            $this->bump($t, ['status' => 'approved', 'approved_by_name' => $this->who(), 'approved_at' => now()]);
            $this->syncEffects($t->fresh('lines'));
        });
        AuditLogger::log('transfer_approved', "Transfer {$t->number} approved", 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    public function reject(StockTransfer $t, ?string $reason): StockTransfer
    {
        if ($t->status !== 'requested') {
            throw ValidationException::withMessages(['status' => 'Only a requested transfer can be rejected.']);
        }
        $this->bump($t, ['status' => 'rejected', 'cancel_reason' => $reason, 'closed_by_name' => $this->who(), 'closed_at' => now()]);
        AuditLogger::log('transfer_rejected', "Transfer {$t->number} rejected: {$reason}", 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    public function cancel(StockTransfer $t, ?string $reason): StockTransfer
    {
        if (!in_array($t->status, ['requested', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => 'A transfer that is already on the way can\'t be cancelled – receive it (even as zero) instead.']);
        }
        DB::transaction(function () use ($t, $reason) {
            $this->bump($t, ['status' => 'cancelled', 'cancel_reason' => $reason, 'closed_by_name' => $this->who(), 'closed_at' => now()]);
            $this->syncEffects($t->fresh('lines'));
        });
        AuditLogger::log('transfer_cancelled', "Transfer {$t->number} cancelled: {$reason}", 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    /** Sending branch: goods leave the store and sit in "in transit" until the other branch receives them. */
    public function dispatch(StockTransfer $t, array $qtys = [], ?string $carrier = null): StockTransfer
    {
        if ($t->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Approve the transfer before dispatching it.']);
        }
        if (!$this->canActFor($t->from_branch_id)) {
            throw ValidationException::withMessages(['status' => 'Only the sending branch can dispatch.']);
        }
        DB::transaction(function () use ($t, $qtys, $carrier) {
            $from = $this->locations->storeFor($t->from_branch_id);
            $transit = $this->locations->typed($t->from_branch_id, 'transit');
            // release the reservation first, so the stock check sees it as available to this transfer
            $this->releaseReservation($t, 'consumed');
            $any = false;
            foreach ($t->lines as $l) {
                $q = array_key_exists($l->uid, $qtys) ? max(0, (float) $qtys[$l->uid]) * $l->qty_per_unit : (float) ($l->approved_qty ?? $l->requested_qty);
                $q = round($q, 3);
                $l->update(['dispatched_qty' => $q]);
                if ($q > 0) {
                    $any = true;
                    $this->stock->transfer($l->product_id, $l->product_variation_id, $from->id, $transit->id, $q,
                        ['reference_number' => $t->number, 'reason' => 'Transfer to ' . $t->toBranch->code, 'unit_cost' => $l->unit_cost]);
                }
            }
            if (!$any) {
                throw ValidationException::withMessages(['lines' => 'Nothing to dispatch – enter at least one quantity.']);
            }
            $this->posted($t, 'dispatch');
            $this->bump($t, ['status' => 'dispatched', 'dispatched_by_name' => $this->who(), 'dispatched_at' => now(), 'carrier' => $carrier ?: $t->carrier]);
            $this->syncEffects($t->fresh('lines'));
        });
        AuditLogger::log('transfer_dispatched', "Transfer {$t->number} dispatched to {$t->toBranch->code}", 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    /** Receiving branch: count what arrived.  Differences become discrepancies to resolve. */
    public function receive(StockTransfer $t, array $qtys = [], ?string $note = null): StockTransfer
    {
        if ($t->status !== 'dispatched') {
            throw ValidationException::withMessages(['status' => 'This transfer is not on the way.']);
        }
        if (!$this->canActFor($t->to_branch_id)) {
            throw ValidationException::withMessages(['status' => 'Only the receiving branch can receive it.']);
        }
        DB::transaction(function () use ($t, $qtys, $note) {
            $to = $this->locations->storeFor($t->to_branch_id);
            $diff = false;
            foreach ($t->lines as $l) {
                $q = array_key_exists($l->uid, $qtys) ? max(0, (float) $qtys[$l->uid]) * $l->qty_per_unit : (float) $l->dispatched_qty;
                $q = round($q, 3);
                $d = round($q - (float) $l->dispatched_qty, 3);
                $l->update(['received_qty' => $q, 'discrepancy_qty' => $d, 'discrepancy_status' => abs($d) > 0.0005 ? 'open' : 'none']);
                $diff = $diff || abs($d) > 0.0005;
                if ($q > 0) {
                    $this->stock->record(['product_id' => $l->product_id, 'variation_id' => $l->product_variation_id, 'location_id' => $to->id,
                        'direction' => 'in', 'type' => 'transfer_in', 'quantity' => $q, 'unit_cost' => $l->unit_cost,
                        'reference_type' => 'transfer', 'reference_number' => $t->number, 'reason' => 'Transfer from ' . $t->fromBranch->code]);
                }
            }
            $this->posted($t, 'receive');
            $this->bump($t, ['status' => $diff ? 'received' : 'closed', 'has_discrepancy' => $diff, 'received_by_name' => $this->who(),
                'received_at' => now(), 'notes' => trim(($t->notes ? $t->notes . "\n" : '') . ($note ?? '')) ?: null]
                + ($diff ? [] : ['closed_at' => now(), 'closed_by_name' => $this->who()]));
            $this->syncEffects($t->fresh('lines'));
        });
        AuditLogger::log('transfer_received', "Transfer {$t->number} received" . ($t->fresh()->has_discrepancy ? ' with differences' : ''), 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    /**
     * Settle a difference: written_off (loss accepted), charged (to a person – note who), found (more turned up:
     * the receiving branch books it in).
     */
    public function resolve(StockTransfer $t, StockTransferLine $l, string $resolution, ?string $note = null, ?float $foundQty = null): StockTransfer
    {
        if ($l->stock_transfer_id !== $t->id || $l->discrepancy_status !== 'open') {
            throw ValidationException::withMessages(['line' => 'Nothing to resolve on this line.']);
        }
        if (!in_array($resolution, ['written_off', 'charged', 'found'], true)) {
            throw ValidationException::withMessages(['resolution' => 'Unknown resolution.']);
        }
        if ($resolution === 'found') {
            if (!$this->canActFor($t->to_branch_id)) {
                throw ValidationException::withMessages(['resolution' => 'The receiving branch books in stock that turned up.']);
            }
            $foundQty = round(max(0, (float) $foundQty) * $l->qty_per_unit, 3);
            if ($foundQty <= 0) {
                throw ValidationException::withMessages(['found_qty' => 'Enter how many turned up.']);
            }
        }
        DB::transaction(function () use ($t, $l, $resolution, $note, $foundQty) {
            if ($resolution === 'found') {
                $to = $this->locations->storeFor($t->to_branch_id);
                $this->stock->record(['product_id' => $l->product_id, 'variation_id' => $l->product_variation_id, 'location_id' => $to->id,
                    'direction' => 'in', 'type' => 'transfer_in', 'quantity' => $foundQty, 'unit_cost' => $l->unit_cost,
                    'reference_type' => 'transfer', 'reference_number' => $t->number, 'reason' => 'Transfer difference found']);
                $l->received_qty = round((float) $l->received_qty + $foundQty, 3);
                $l->discrepancy_qty = round($l->received_qty - (float) $l->dispatched_qty, 3);
            }
            $l->discrepancy_status = $resolution === 'found' && abs($l->discrepancy_qty) > 0.0005 && $l->discrepancy_qty < 0 ? 'open' : $resolution;
            $l->discrepancy_note = mb_substr(trim(($l->discrepancy_note ? $l->discrepancy_note . ' · ' : '') . $this->who() . ': ' . ($note ?? $resolution)), 0, 255);
            $l->save();
            $open = $t->lines()->where('discrepancy_status', 'open')->exists();
            $this->bump($t, $open ? [] : ['status' => 'closed', 'closed_at' => now(), 'closed_by_name' => $this->who()]);
            $this->syncEffects($t->fresh('lines'));
        });
        AuditLogger::log('transfer_discrepancy', "Transfer {$t->number}: " . ($l->product->title ?? '') . " difference {$resolution}" . ($note ? " – {$note}" : ''), 'inventory');
        DocumentSync::share('transfer', $t);
        return $t->fresh('lines');
    }

    // ------------------------------------------------------------------ effects on this server

    protected function isPosted(StockTransfer $t, string $step): bool
    {
        return DB::table('stock_transfer_postings')->where('transfer_uid', $t->uid)->where('step', $step)->exists();
    }

    protected function posted(StockTransfer $t, string $step): void
    {
        DB::table('stock_transfer_postings')->insertOrIgnore(['transfer_uid' => $t->uid, 'step' => $step, 'created_at' => now()]);
    }

    protected function releaseReservation(StockTransfer $t, string $as): void
    {
        if ($this->isPosted($t, 'reserve') && !$this->isPosted($t, 'release')) {
            $this->balances->release('transfer', $t->uid, $as);
            $this->posted($t, 'release');
        }
    }

    /** Bring this server's stock in line with the transfer's status (safe to call any number of times). */
    public function syncEffects(StockTransfer $t): void
    {
        $here = $this->here();
        if (!$here) {
            return;
        }
        $s = $t->status;
        $t->loadMissing('lines', 'fromBranch', 'toBranch');

        if ($here === $t->from_branch_id) {
            if ($s === 'approved' && !$this->isPosted($t, 'reserve')) {
                foreach ($t->lines as $l) {
                    if (($q = (float) ($l->approved_qty ?? 0)) > 0) {
                        $this->balances->reserve($l->product_id, $l->product_variation_id, $q, 'transfer', $t->uid, $here, true);
                    }
                }
                $this->posted($t, 'reserve');
            }
            if (in_array($s, ['dispatched', 'received', 'closed', 'cancelled', 'rejected'], true)) {
                $this->releaseReservation($t, in_array($s, ['cancelled', 'rejected'], true) ? 'released' : 'consumed');
            }
            if (in_array($s, ['received', 'closed'], true) && $this->isPosted($t, 'dispatch') && !$this->isPosted($t, 'clear_transit')) {
                $transit = $this->locations->typed($here, 'transit');
                foreach ($t->lines as $l) {
                    if (($q = (float) $l->dispatched_qty) > 0) {
                        $this->stock->record(['product_id' => $l->product_id, 'variation_id' => $l->product_variation_id, 'location_id' => $transit->id,
                            'direction' => 'out', 'type' => 'transfer', 'quantity' => $q, 'allow_negative' => true, 'unit_cost' => $l->unit_cost,
                            'reference_type' => 'transfer', 'reference_number' => $t->number, 'reason' => 'Received by ' . $t->toBranch->code]);
                    }
                }
                $this->posted($t, 'clear_transit');
            }
        }

        if ($here === $t->to_branch_id) {
            if (in_array($s, ['dispatched', 'received', 'closed'], true) && !$this->isPosted($t, 'incoming')) {
                foreach ($t->lines as $l) {
                    if (($q = (float) $l->dispatched_qty) > 0) {
                        $this->balances->setIncoming($here, $l->product_id, $l->product_variation_id, $q);
                    }
                }
                $this->posted($t, 'incoming');
            }
            if (in_array($s, ['received', 'closed'], true) && $this->isPosted($t, 'incoming') && !$this->isPosted($t, 'incoming_done')) {
                foreach ($t->lines as $l) {
                    if (($q = (float) $l->dispatched_qty) > 0) {
                        $this->balances->setIncoming($here, $l->product_id, $l->product_variation_id, -$q);
                    }
                }
                $this->posted($t, 'incoming_done');
            }
        }
    }
}
