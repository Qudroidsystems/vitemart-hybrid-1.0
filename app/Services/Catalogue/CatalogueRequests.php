<?php

namespace App\Services\Catalogue;

use App\Models\Branch;
use App\Models\FieldPolicy;
use App\Models\PriceChangeRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductSubmission;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\MultiBranch\CentralClient;
use App\Services\MultiBranch\Install;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who may change a head-office product, and the two request flows between branch and head office:
 *
 *   price change request   branch asks → HQ approves for that branch (branch price list) or for everyone (catalogue)
 *   product submission     branch offers a product it created → HQ adds it to the catalogue with the same uid
 *
 * Branch side keeps its own copy of each request; `push()` sends what is unsent and `pull()` applies decisions.
 */
class CatalogueRequests
{
    public function __construct(protected PriceListResolver $prices, protected PricingService $pricing)
    {
    }

    /** Is this product governed by head office on this server? */
    public static function governed(?Product $product): bool
    {
        return $product && Install::isBranch() && $product->owner === 'central';
    }

    /** Field → rule for the product form. */
    public static function policyFor(?Product $product): array
    {
        return static::governed($product) ? FieldPolicy::map() : [];
    }

    // ==================================================================
    // Branch: edit of a head-office product
    // ==================================================================

    /**
     * Applies "override" fields, turns changed "request" fields into price change requests,
     * and ignores locked fields.  Returns a short summary for the user.
     */
    public function saveAtBranch(Request $request, Product $product): array
    {
        $product->loadMissing('packs.unit', 'variations');
        $policy = FieldPolicy::map();
        $made = 0;
        $applied = [];

        DB::transaction(function () use ($request, $product, $policy, &$made, &$applied) {
            // Override fields
            $over = [];
            if (($policy['reorder_level'] ?? 'locked') === 'override' && $request->has('reorder_level')
                && (float) $request->reorder_level != (float) $product->reorder_level) {
                $over['reorder_level'] = $request->input('reorder_level');
            }
            if (($policy['is_active'] ?? 'locked') === 'override' && $request->has('is_active')
                && $request->boolean('is_active') !== (bool) $product->is_active) {
                $over['is_active'] = $request->boolean('is_active');
            }
            if ($over) {
                $product->update($over);
                foreach (array_keys($over) as $f) {
                    DB::table('product_local_overrides')->insertOrIgnore(['product_id' => $product->id, 'field' => $f, 'created_at' => now(), 'updated_at' => now()]);
                }
                $applied = array_keys($over);
            }

            // Request fields: product level
            $reason = $request->input('change_reason') ?: 'Changed on the product form';
            foreach (['price', 'wholesale_price', 'promo_price'] as $f) {
                if (($policy[$f] ?? 'locked') !== 'request' || !$request->filled($f)) {
                    continue;
                }
                $now = $this->currentValue($product, null, null, $f);
                $new = round((float) $request->input($f), 2);
                if ($now === null || abs($now - $new) > 0.004) {
                    $this->request($product, null, null, $f, $new, $reason, $request->input('promo_starts_at'), $request->input('promo_ends_at'));
                    $made++;
                }
            }
            // Variations
            if (($policy['price'] ?? 'locked') === 'request') {
                foreach ((array) $request->input('variations', []) as $row) {
                    $v = !empty($row['id']) ? $product->variations->firstWhere('id', (int) $row['id']) : null;
                    if (!$v || !isset($row['price']) || $row['price'] === '') {
                        continue;
                    }
                    $now = $this->currentValue($product, $v, null, 'price');
                    $new = round((float) $row['price'], 2);
                    if ($now === null || abs($now - $new) > 0.004) {
                        $this->request($product, $v, null, 'price', $new, $reason);
                        $made++;
                    }
                }
            }
            // Packs
            if (($policy['pack_price'] ?? 'locked') === 'request') {
                foreach ((array) $request->input('packs', []) as $row) {
                    $u = !empty($row['id']) ? $product->packs->firstWhere('id', (int) $row['id']) : null;
                    if (!$u || !isset($row['price']) || $row['price'] === '') {
                        continue;
                    }
                    $now = $this->currentValue($product, null, $u, 'price');
                    $new = round((float) $row['price'], 2);
                    if ($now === null || abs($now - $new) > 0.004) {
                        $this->request($product, null, $u, 'price', $new, $reason);
                        $made++;
                    }
                }
            }
        });

        if ($made) {
            $this->pushQuietly();
        }

        $parts = [];
        if ($applied) {
            $parts[] = 'Saved for this branch.';
        }
        if ($made) {
            $parts[] = "{$made} price change request" . ($made > 1 ? 's' : '') . ' sent to head office for approval.';
        }
        return ['requests' => $made, 'applied' => $applied, 'message' => $parts ? implode(' ', $parts) : 'Nothing to change – other details are managed by head office.'];
    }

    /** The price this branch sells at now (branch list, else catalogue). */
    public function currentValue(Product $product, ?ProductVariation $v, ?ProductUnit $u, string $field): ?float
    {
        $item = $this->prices->item($product->id, $v?->id, $u?->id);
        if ($item && $item->{$field} !== null) {
            return (float) $item->{$field};
        }
        $model = $u ?? $v ?? $product;
        return $model->{$field} !== null ? (float) $model->{$field} : null;
    }

    public function request(Product $product, ?ProductVariation $v, ?ProductUnit $u, string $field, float $value, ?string $reason = null,
                            $promoStart = null, $promoEnd = null): PriceChangeRequest
    {
        if (!array_key_exists($field, PriceChangeRequest::FIELDS)) {
            throw ValidationException::withMessages(['field' => 'Only prices can be requested.']);
        }
        if ($value < 0) {
            throw ValidationException::withMessages(['value' => 'Price cannot be negative.']);
        }
        $label = $product->title . ($v ? ' – ' . $v->label : '') . ($u ? ' – ' . ($u->unit->name ?? 'Pack') . ' of ' . (float) $u->quantity_per_unit : '');

        // A newer request for the same thing replaces the older pending one.
        PriceChangeRequest::where('product_id', $product->id)->where('field', $field)
            ->where('variation_uid', $v?->uid)->where('pack_uid', $u?->uid)
            ->where('status', 'pending')->where('sent', false)
            ->update(['status' => 'cancelled', 'decision_note' => 'Replaced by a newer request']);

        $user = auth()->user();
        $r = PriceChangeRequest::create([
            'branch_id' => Install::branchId(),
            'product_id' => $product->id,
            'product_uid' => $product->uid,
            'variation_uid' => $v?->uid,
            'pack_uid' => $u?->uid,
            'item_label' => mb_substr($label, 0, 250),
            'field' => $field,
            'current_value' => $this->currentValue($product, $v, $u, $field),
            'requested_value' => $value,
            'promo_starts_at' => $field === 'promo_price' ? ($promoStart ?: null) : null,
            'promo_ends_at' => $field === 'promo_price' ? ($promoEnd ?: null) : null,
            'reason' => $reason ? mb_substr($reason, 0, 250) : null,
            'status' => 'pending',
            'requested_by' => $user?->id,
            'requested_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
        ]);
        AuditLogger::log('price_change_requested', "Asked head office to change {$label} " . PriceChangeRequest::FIELDS[$field] . ' to ' . number_format($value, 2),
            'pricing', $product, ['value' => $r->current_value], ['value' => $value]);
        return $r;
    }

    /** Branch: offer a locally created product to the head-office catalogue. */
    public function submit(Product $product, ?string $note = null): ProductSubmission
    {
        if (!Install::isBranch()) {
            throw ValidationException::withMessages(['product' => 'Only a branch server submits products to head office.']);
        }
        if ($product->owner === 'central') {
            throw ValidationException::withMessages(['product' => 'This product is already in the head-office catalogue.']);
        }
        $user = auth()->user();
        $s = ProductSubmission::updateOrCreate(
            ['product_uid' => $product->uid, 'status' => 'pending', 'sent' => false],
            [
                'branch_id' => Install::branchId(),
                'title' => $product->title,
                'barcode' => $product->barcode,
                'payload' => ProductPayload::export($product),
                'submitted_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
                'note' => $note,
            ]
        );
        $product->forceFill(['central_status' => 'submitted'])->save();
        AuditLogger::log('product_submitted', "Sent {$product->title} to head office for the catalogue", 'products', $product);
        $this->pushQuietly();
        return $s;
    }

    // ==================================================================
    // Branch ↔ head office transport
    // ==================================================================

    public function pushQuietly(): void
    {
        try {
            $this->push(true);
        } catch (\Throwable $e) {
            // Stays unsent; the scheduler retries every minute.
        }
    }

    /** Branch: send unsent requests and submissions. Returns how many were delivered. */
    public function push(bool $quick = false): int
    {
        if (!Install::isBranch()) {
            return 0;
        }
        $n = 0;
        $client = app(CentralClient::class)->quick($quick);

        $reqs = PriceChangeRequest::where('sent', false)->where('status', 'pending')->limit(200)->get();
        if ($reqs->isNotEmpty()) {
            $client->post('/api/v1/branch/price-requests', ['requests' => $reqs->map->toSync()->all()])->throw();
            PriceChangeRequest::whereIn('id', $reqs->pluck('id'))->update(['sent' => true]);
            $n += $reqs->count();
        }

        $subs = ProductSubmission::where('sent', false)->where('status', 'pending')->limit(50)->get();
        if ($subs->isNotEmpty()) {
            $client->post('/api/v1/branch/product-submissions', ['submissions' => $subs->map(fn ($s) => [
                'uid' => $s->uid, 'product_uid' => $s->product_uid, 'title' => $s->title, 'barcode' => $s->barcode,
                'payload' => $s->payload, 'submitted_by_name' => $s->submitted_by_name, 'note' => $s->note,
            ])->all()])->throw();
            ProductSubmission::whereIn('id', $subs->pluck('id'))->update(['sent' => true]);
            $n += $subs->count();
        }
        return $n;
    }

    /** Branch: fetch head-office decisions made since the last pull and apply them. */
    public function pull(): int
    {
        if (!Install::isBranch()) {
            return 0;
        }
        $since = SystemSetting::get('catalogue_decisions_cursor');
        $res = app(CentralClient::class)->get('/api/v1/branch/decisions', $since ? ['since' => $since] : [])->throw()->json();
        $n = 0;
        foreach ($res['price_requests'] ?? [] as $d) {
            $n += $this->applyPriceDecision($d) ? 1 : 0;
        }
        foreach ($res['submissions'] ?? [] as $d) {
            $n += $this->applySubmissionDecision($d) ? 1 : 0;
        }
        if (!empty($res['cursor'])) {
            SystemSetting::set('catalogue_decisions_cursor', $res['cursor']);
        }
        return $n;
    }

    public function applyPriceDecision(array $d): bool
    {
        $r = PriceChangeRequest::where('uid', $d['uid'])->first();
        if (!$r || $r->applied) {
            return false;
        }
        $r->fill([
            'status' => $d['status'], 'approved_value' => $d['approved_value'] ?? null, 'scope' => $d['scope'] ?? null,
            'decided_by_name' => $d['decided_by_name'] ?? null, 'decided_at' => $d['decided_at'] ?? now(),
            'decision_note' => $d['decision_note'] ?? null,
        ]);
        if ($r->status === 'approved') {
            $this->applyApprovedPrice($r, Install::branch());
        }
        $r->applied = in_array($r->status, ['approved', 'rejected'], true);
        $r->save();
        return true;
    }

    public function applySubmissionDecision(array $d): bool
    {
        $s = ProductSubmission::where('uid', $d['uid'])->first();
        if (!$s || $s->applied) {
            return false;
        }
        $s->fill(['status' => $d['status'], 'decided_by_name' => $d['decided_by_name'] ?? null,
            'decided_at' => $d['decided_at'] ?? now(), 'decision_note' => $d['decision_note'] ?? null, 'applied' => true])->save();
        if ($p = Product::where('uid', $s->product_uid)->first()) {
            $p->forceFill($s->status === 'approved'
                ? ['owner' => 'central', 'central_status' => 'published']
                : ['central_status' => 'local'])->save();
        }
        return true;
    }

    // ==================================================================
    // Head office
    // ==================================================================

    /** HQ: store requests received from a branch server (idempotent by uid). */
    public function receivePriceRequests(Branch $branch, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if (PriceChangeRequest::where('uid', $row['uid'])->exists()) {
                continue;
            }
            PriceChangeRequest::create([
                'uid' => $row['uid'],
                'branch_id' => $branch->id,
                'product_id' => Product::where('uid', $row['product_uid'])->value('id'),
                'product_uid' => $row['product_uid'],
                'variation_uid' => $row['variation_uid'] ?? null,
                'pack_uid' => $row['pack_uid'] ?? null,
                'item_label' => mb_substr((string) ($row['item_label'] ?? ''), 0, 250),
                'field' => in_array($row['field'] ?? '', array_keys(PriceChangeRequest::FIELDS), true) ? $row['field'] : 'price',
                'current_value' => $row['current_value'] ?? null,
                'requested_value' => (float) $row['requested_value'],
                'promo_starts_at' => $row['promo_starts_at'] ?? null,
                'promo_ends_at' => $row['promo_ends_at'] ?? null,
                'reason' => isset($row['reason']) ? mb_substr((string) $row['reason'], 0, 250) : null,
                'requested_by_name' => isset($row['requested_by_name']) ? mb_substr((string) $row['requested_by_name'], 0, 120) : null,
                'status' => 'pending',
                'sent' => true,
            ]);
            $n++;
        }
        return $n;
    }

    public function receiveSubmissions(Branch $branch, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if (ProductSubmission::where('uid', $row['uid'])->exists()) {
                continue;
            }
            ProductSubmission::create([
                'uid' => $row['uid'],
                'branch_id' => $branch->id,
                'product_uid' => $row['product_uid'],
                'title' => mb_substr((string) $row['title'], 0, 250),
                'barcode' => $row['barcode'] ?? null,
                'payload' => $row['payload'],
                'submitted_by_name' => isset($row['submitted_by_name']) ? mb_substr((string) $row['submitted_by_name'], 0, 120) : null,
                'note' => isset($row['note']) ? mb_substr((string) $row['note'], 0, 250) : null,
                'status' => 'pending',
                'sent' => true,
            ]);
            $n++;
        }
        return $n;
    }

    /** HQ: approve a branch's price request.  scope = branch (only that branch) | all (catalogue price). */
    public function approvePrice(PriceChangeRequest $r, ?float $value = null, string $scope = 'branch', ?string $note = null): PriceChangeRequest
    {
        if ($r->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'This request was already decided.']);
        }
        if (!$r->product_id) {
            throw ValidationException::withMessages(['request' => 'The product is not in the head-office catalogue.']);
        }
        $user = auth()->user();
        DB::transaction(function () use ($r, $value, $scope, $note, $user) {
            $r->fill([
                'status' => 'approved',
                'approved_value' => $value ?? $r->requested_value,
                'scope' => $scope === 'all' ? 'all' : 'branch',
                'decided_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            $this->applyApprovedPrice($r, $r->branch);
            $r->applied = true;
            $r->save();
        });
        AuditLogger::log('price_change_approved', "Approved {$r->item_label} " . PriceChangeRequest::FIELDS[$r->field] . ' = ' . number_format((float) $r->approved_value, 2)
            . ' for ' . ($r->scope === 'all' ? 'all branches' : ($r->branch->code ?? 'branch')), 'pricing', $r->product);
        return $r;
    }

    public function rejectPrice(PriceChangeRequest $r, ?string $note = null): PriceChangeRequest
    {
        if ($r->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'This request was already decided.']);
        }
        $user = auth()->user();
        $r->update(['status' => 'rejected', 'decided_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
            'decided_at' => now(), 'decision_note' => $note, 'applied' => true]);
        AuditLogger::log('price_change_rejected', "Rejected price request for {$r->item_label}", 'pricing', $r->product);
        return $r;
    }

    /** Write an approved price on this server: into the branch's list, or into the catalogue. */
    protected function applyApprovedPrice(PriceChangeRequest $r, ?Branch $branch): void
    {
        $product = Product::where('uid', $r->product_uid)->first();
        if (!$product) {
            return;
        }
        $v = $r->variation_uid ? ProductVariation::where('uid', $r->variation_uid)->first() : null;
        $u = $r->pack_uid ? ProductUnit::where('uid', $r->pack_uid)->first() : null;
        $value = (float) $r->approved_value;
        $values = [$r->field => $value];
        if ($r->field === 'promo_price') {
            $values += ['promo_starts_at' => $r->promo_starts_at, 'promo_ends_at' => $r->promo_ends_at];
        }

        if ($r->scope === 'all') {
            $model = $u ?? $v ?? $product;
            $this->pricing->setPrice($model, $r->field, $value, 'branch_request', "Approved request from " . ($r->branch->code ?? 'branch'));
            if ($r->field === 'promo_price' && $model instanceof Product) {
                $model->update(['promo_starts_at' => $r->promo_starts_at, 'promo_ends_at' => $r->promo_ends_at]);
            }
            // A branch-only price for the same item would now hide the new catalogue price.
            if ($branch && ($list = PriceList::where('code', 'BR-' . $branch->code)->first())) {
                $list->items()->where('item_key', \App\Models\PriceListItem::keyFor($product->id, $v?->id, $u?->id))
                    ->update([$r->field => null]);
            }
        } elseif ($branch) {
            $this->prices->set(PriceList::forBranch($branch), $product, $v, $u, $values, auth()->id());
        }
    }

    /** HQ: add a branch's product to the catalogue. */
    public function approveSubmission(ProductSubmission $s, ?string $note = null): Product
    {
        if ($s->status !== 'pending') {
            throw ValidationException::withMessages(['submission' => 'This product was already decided.']);
        }
        $product = ProductPayload::import($s->payload, 'central', $s->branch_id);
        $user = auth()->user();
        $s->update(['status' => 'approved', 'central_product_id' => $product->id, 'applied' => true,
            'decided_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
            'decided_at' => now(), 'decision_note' => $note]);
        AuditLogger::log('product_submission_approved', "Added {$product->title} from " . ($s->branch->code ?? 'branch') . ' to the catalogue', 'products', $product);
        return $product;
    }

    public function rejectSubmission(ProductSubmission $s, ?string $note = null): ProductSubmission
    {
        if ($s->status !== 'pending') {
            throw ValidationException::withMessages(['submission' => 'This product was already decided.']);
        }
        $user = auth()->user();
        $s->update(['status' => 'rejected', 'applied' => true, 'decided_by_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'system',
            'decided_at' => now(), 'decision_note' => $note]);
        AuditLogger::log('product_submission_rejected', "Did not add {$s->title} from " . ($s->branch->code ?? 'branch') . ' to the catalogue', 'products');
        return $s;
    }

    /** HQ: decisions for one branch since a cursor (decided_at ISO). */
    public function decisionsFor(Branch $branch, ?string $since): array
    {
        $after = $since ? \Carbon\Carbon::parse($since) : null;
        $pr = PriceChangeRequest::where('branch_id', $branch->id)->whereIn('status', ['approved', 'rejected'])
            ->when($after, fn ($q) => $q->where('decided_at', '>=', $after))->orderBy('decided_at')->limit(500)->get();
        $ps = ProductSubmission::where('branch_id', $branch->id)->whereIn('status', ['approved', 'rejected'])
            ->when($after, fn ($q) => $q->where('decided_at', '>=', $after))->orderBy('decided_at')->limit(200)->get();
        $last = collect([$pr->max('decided_at'), $ps->max('decided_at')])->filter()->max();
        return [
            'price_requests' => $pr->map->decisionPayload()->all(),
            'submissions' => $ps->map->decisionPayload()->all(),
            'cursor' => $last ? $last->toIso8601String() : $since,
        ];
    }
}
