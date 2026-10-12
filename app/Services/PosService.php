<?php

namespace App\Services;

use App\Exceptions\PosAuthorizationRequired;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\CustomerPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PosHeldSale;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Refund;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Till logic. Prices are always re-calculated on the server – the browser only
 * sends what was scanned, how many, and any discount the cashier applied.
 */
class PosService
{
    public function __construct(
        protected PricingService $pricing,
        protected StockService $stock,
    ) {
    }

    // -----------------------------------------------------------------
    // Stock the till can actually sell (its own location)
    // -----------------------------------------------------------------

    protected ?int $stockLocationId = null;
    /** @var array<string,float> "productId:variationId" and "productId:*" => balance */
    protected array $stockMemo = [];

    /** Sales from this till deduct from its register's location, so that's the stock to show and check. */
    public function useTillLocation(?int $registerId): void
    {
        $loc = null;
        if ($registerId) {
            $loc = \App\Models\CashRegister::here()->find($registerId)?->stock_location_id;
        }
        $this->stockLocationId = $loc ?: $this->stock->defaultLocation()->id;
        $this->stockMemo = [];
    }

    /** One grouped query for the balances of many products at the till's location. */
    public function primeStock(array $productIds): void
    {
        if ($this->stockLocationId === null) {
            $this->useTillLocation(null);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $ids = array_values(array_filter($ids, fn ($id) => !array_key_exists($id . ':*', $this->stockMemo)));
        if (!$ids) {
            return;
        }
        foreach ($ids as $id) {
            $this->stockMemo[$id . ':*'] = 0.0;
        }
        $rows = DB::table('stocks')
            ->where('stock_location_id', $this->stockLocationId)
            ->whereIn('product_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('product_id', 'product_variation_id')
            ->select('product_id', 'product_variation_id')
            ->selectRaw(StockService::signedSql() . ' as total')
            ->get();
        foreach ($rows as $r) {
            $total = round((float) $r->total, 3);
            $this->stockMemo[$r->product_id . ':*'] += $total;
            if ($r->product_variation_id) {
                $this->stockMemo[$r->product_id . ':' . $r->product_variation_id] = $total;
            }
        }
    }

    public function sellableStock(Product $product, ?ProductVariation $variation = null): float
    {
        $this->primeStock([$product->id]);
        return $variation
            ? (float) ($this->stockMemo[$product->id . ':' . $variation->id] ?? 0)
            : (float) ($this->stockMemo[$product->id . ':*'] ?? 0);
    }

    // -----------------------------------------------------------------
    // Item lookup
    // -----------------------------------------------------------------

    /**
     * Resolve a scanned code or typed search to sellable items.
     * Returns ['exact' => bool, 'items' => [...]]
     */
    public function lookup(string $q, ?Customer $customer = null, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['exact' => false, 'items' => []];
        }

        // 1. Scale label (weight / price embedded)
        if ($scale = BarcodeGenerator::parseScaleBarcode($q)) {
            $product = Product::with('packs.unit')->where('is_active', true)
                ->where(fn ($w) => $w->where('plu_code', $scale['plu'])->orWhere('plu_code', str_pad($scale['plu'], 5, '0', STR_PAD_LEFT)))
                ->first();
            if ($product) {
                $item = $this->formatItem($product, null, null, $customer);
                $unitPrice = $item['price'];
                $item['scale'] = $scale;
                $item['qty'] = $scale['mode'] === 'weight'
                    ? round($scale['value'], 3)
                    : ($unitPrice > 0 ? round($scale['value'] / $unitPrice, 3) : 1);
                return ['exact' => true, 'items' => [$item]];
            }
        }

        // 2. Exact barcode on product / variation / pack
        if ($owner = BarcodeGenerator::owner($q)) {
            $model = $owner['model'];
            $item = match ($owner['type']) {
                'product' => $model->is_active ? $this->formatItem($model->load('packs.unit'), null, null, $customer) : null,
                'variation' => ($model->is_active !== false && $model->product?->is_active)
                    ? $this->formatItem($model->product->load('packs.unit'), $model, null, $customer) : null,
                'pack' => $model->product?->is_active
                    ? $this->formatItem($model->product->load('packs.unit'), null, $model, $customer) : null,
            };
            if ($item) {
                return ['exact' => true, 'items' => [$item]];
            }
        }

        // 3. Exact SKU / PLU
        $exact = Product::with('packs.unit')->where('is_active', true)
            ->where(fn ($w) => $w->where('sku', $q)->orWhere('plu_code', $q))->first();
        if ($exact && $exact->product_type !== 'variable') {
            return ['exact' => true, 'items' => [$this->formatItem($exact, null, null, $customer)]];
        }

        // 4. Text search – a few small indexed queries pick the ids, then only those load.
        $ids = $this->searchProductIds($q, $limit);
        if (!$ids) {
            return ['exact' => false, 'items' => []];
        }
        app(\App\Services\Catalogue\PriceListResolver::class)->prime($ids);
        $this->primeStock($ids);
        $rank = array_flip($ids);
        $products = Product::with(['packs.unit', 'variations' => fn ($v) => $v->where('is_active', true)])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($p) => $rank[$p->id] ?? PHP_INT_MAX)
            ->values();

        $items = [];
        foreach ($products as $p) {
            if (!$p->is_active) {
                continue;
            }
            if ($p->product_type === 'variable' && $p->variations->isNotEmpty()) {
                foreach ($p->variations as $v) {
                    $items[] = $this->formatItem($p, $v, null, $customer);
                }
            } else {
                $items[] = $this->formatItem($p, null, null, $customer);
            }
            if (count($items) >= $limit) {
                break;
            }
        }

        return ['exact' => false, 'items' => $items];
    }

    /** Words MySQL/MariaDB full-text search ignores by default. */
    protected const FT_STOPWORDS = ['a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www'];

    /**
     * Product ids for a typed search, best first. Built for large catalogues (100k+ products):
     * every step is an indexed query with a LIMIT, never a scan-and-sort of the whole table.
     *   1. name starts with the text      (products_title_idx)
     *   2. SKU / variation SKU starts with the text
     *   3. every word appears in the name  (products_search_ft full-text index; LIKE fallback)
     */
    public function searchProductIds(string $q, int $limit = 20): array
    {
        $q = trim(preg_replace('/\s+/', ' ', $q));
        if ($q === '') {
            return [];
        }
        $like = fn (string $s) => addcslashes($s, '%_\\');
        $ids = [];
        $add = function ($found) use (&$ids, $limit) {
            foreach ($found as $id) {
                $id = (int) $id;
                if (!in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
                if (count($ids) >= $limit) {
                    break;
                }
            }
        };

        // 1. Name starts with what was typed
        $add(Product::where('is_active', true)->where('title', 'like', $like($q) . '%')
            ->orderBy('title')->limit($limit)->pluck('id'));

        // 2. SKU starts with it (products, then variations)
        if (count($ids) < $limit && !str_contains($q, ' ')) {
            $add(Product::where('is_active', true)->where('sku', 'like', $like($q) . '%')->limit($limit)->pluck('id'));
            if (count($ids) < $limit) {
                $add(ProductVariation::where('is_active', true)->where('sku', 'like', $like($q) . '%')->limit($limit)->pluck('product_id'));
            }
        }

        // 3. Every word somewhere in the name
        if (count($ids) < $limit) {
            $words = array_slice(array_values(array_filter(explode(' ', mb_strtolower($q)), fn ($w) => $w !== '')), 0, 8);
            $ftWords = [];
            $likeWords = [];
            foreach ($words as $w) {
                $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $w);
                if (mb_strlen($clean) >= 3 && !in_array($clean, self::FT_STOPWORDS, true)) {
                    $ftWords[] = $clean;
                } else {
                    $likeWords[] = $w;
                }
            }

            $found = null;
            if ($ftWords && $this->fullTextReady()) {
                $boolean = implode(' ', array_map(fn ($w) => '+' . $w . '*', $ftWords));
                try {
                    $found = Product::where('is_active', true)
                        ->whereRaw('MATCH(title, sku) AGAINST (? IN BOOLEAN MODE)', [$boolean])
                        ->when($likeWords, fn ($w) => $w->where(function ($x) use ($likeWords, $like) {
                            foreach ($likeWords as $lw) {
                                $x->where('title', 'like', '%' . $like($lw) . '%');
                            }
                        }))
                        ->orderByRaw('MATCH(title, sku) AGAINST (? IN BOOLEAN MODE) DESC', [$boolean])
                        ->orderByDesc('sold_quantity')
                        ->limit($limit)
                        ->pluck('id');
                } catch (\Throwable $e) {
                    Cache::forget('pos_search_fulltext');
                    $found = null;
                }
            }

            if ($found === null) {
                // No full-text index (or only very short words): LIKE without ORDER BY, so the
                // database can stop as soon as it has enough rows; sort the few results here.
                $rows = Product::where('is_active', true)
                    ->where(function ($w) use ($words, $like) {
                        foreach ($words as $word) {
                            $w->where(fn ($x) => $x->where('title', 'like', '%' . $like($word) . '%')->orWhere('sku', 'like', $like($word) . '%'));
                        }
                    })
                    ->limit($limit * 3)
                    ->get(['id', 'title']);
                $lq = mb_strtolower($q);
                $found = $rows->sortBy(fn ($r) => (str_starts_with(mb_strtolower($r->title), $lq) ? '0' : '1') . mb_strtolower($r->title))->pluck('id');
            }
            $add($found);
        }

        return $ids;
    }

    /** Is there a full-text index on products(title, sku)? Checked once an hour. */
    protected function fullTextReady(): bool
    {
        return (bool) Cache::remember('pos_search_fulltext', 3600, function () {
            try {
                if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    return false;
                }
                foreach (\Illuminate\Support\Facades\Schema::getIndexes('products') as $ix) {
                    if (($ix['type'] ?? '') === 'fulltext' && $ix['columns'] === ['title', 'sku']) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
            return false;
        });
    }

    /** Packs whose unit is a weight or volume (kg, g, L, ml…) may be sold in fractions, e.g. 0.75 kg. */
    public function isMeasuredPack(?ProductUnit $pack): bool
    {
        if (!$pack || !$pack->unit) {
            return false;
        }
        foreach ([$pack->unit->name, $pack->unit->short_name] as $label) {
            $t = rtrim(trim((string) $label), '.');
            if ($t !== '' && preg_match('/^(kg|kgs|kilo|kilogram|kilogramme|g|gm|gms|gr|gram|gramme|lb|lbs|pound|l|lt|ltr|litre|liter|ml|millilitre|milliliter|cl|centilitre|centiliter)s?$/i', $t)) {
                return true;
            }
        }
        return false;
    }

    public function formatItem(Product $product, ?ProductVariation $variation = null, ?ProductUnit $pack = null, ?Customer $customer = null): array
    {
        $packs = $product->relationLoaded('packs') ? $product->packs : $product->packs()->with('unit')->get();
        $sellable = $packs->filter(fn ($p) => $p->is_sellable || $p->is_base)->values();
        $base = $sellable->firstWhere('is_base', true) ?? $sellable->first();
        $selected = $pack ?? $base;

        $packList = $sellable->map(function (ProductUnit $p) use ($product, $variation, $customer) {
            $quote = $this->pricing->quote($product, $variation, $p, $customer);
            return [
                'id' => $p->id,
                'name' => $p->unit->name ?? 'Unit',
                'short' => $p->unit->short_name ?? '',
                'qty' => (float) $p->quantity_per_unit,
                'is_base' => (bool) $p->is_base,
                'price' => $quote['price'],
                'regular_price' => $quote['regular_price'],
                'barcode' => $p->barcode,
            ];
        })->values()->all();

        // Products without any pack rows still sell by the piece.
        if (!$packList) {
            $quote = $this->pricing->quote($product, $variation, null, $customer);
            $packList = [[
                'id' => null, 'name' => $product->is_weighted ? 'Kilogram' : 'Piece', 'short' => $product->is_weighted ? 'kg' : 'pc',
                'qty' => 1.0, 'is_base' => true, 'price' => $quote['price'], 'regular_price' => $quote['regular_price'], 'barcode' => null,
            ]];
        }

        $quote = $this->pricing->quote($product, $variation, $selected, $customer);
        $stock = $this->sellableStock($product, $variation);

        return [
            'key' => $product->id . ':' . ($variation?->id ?? 0),
            'product_id' => $product->id,
            'variation_id' => $variation?->id,
            'pack_id' => $selected?->id,
            'title' => $product->title,
            'variant' => $variation?->label,
            'sku' => $variation?->sku ?: $product->sku,
            'barcode' => $variation?->barcode ?: $product->barcode,
            'price' => $quote['price'],
            'regular_price' => $quote['regular_price'],
            'on_promo' => !$variation && $product->isPromoActive(),
            'cost' => $quote['cost'],
            'stock' => $stock,
            'track_stock' => $product->track_stock !== false,
            'is_weighted' => (bool) $product->is_weighted,
            'allow_price_override' => (bool) $product->allow_price_override,
            'base_unit' => $base?->unit?->short_name ?: ($product->is_weighted ? 'kg' : 'pc'),
            'packs' => $packList,
            'thumbnail' => $variation?->image ? asset('storage/' . $variation->image)
                : ($product->thumbnail ? asset('storage/' . $product->thumbnail) : null),
            'qty' => 1,
        ];
    }

    // -----------------------------------------------------------------
    // Supervisor authorisation
    // -----------------------------------------------------------------

    public const OVERRIDES = [
        'discount' => 'Give pos discount',
        'price' => 'Override pos price',
        'below_cost' => 'Sell below cost',
        'void' => 'Void pos sale',
        'return' => 'Process pos return',
    ];

    /** Check a supervisor PIN; returns a one-time token valid for 10 minutes. */
    public function authorizeWithPin(string $pin, string $action): array
    {
        $permission = self::OVERRIDES[$action] ?? null;
        if (!$permission) {
            throw ValidationException::withMessages(['action' => 'Unknown action.']);
        }

        $supervisor = User::whereNotNull('pos_pin')->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))->get()
            ->first(fn (User $u) => Hash::check($pin, $u->pos_pin));

        if (!$supervisor || !$supervisor->can($permission)) {
            AuditLogger::log('override_denied', "Supervisor PIN rejected for '{$action}'", 'pos');
            throw ValidationException::withMessages(['pin' => 'Invalid PIN or that supervisor is not allowed to approve this.']);
        }

        $token = Str::random(40);
        Cache::put("pos_auth:{$token}", ['user_id' => $supervisor->id, 'action' => $action, 'cashier' => auth()->id()], now()->addMinutes(10));
        AuditLogger::log('override_granted', "{$supervisor->first_name} authorised '{$action}' at the till", 'pos', $supervisor);

        return ['token' => $token, 'supervisor' => trim($supervisor->first_name . ' ' . $supervisor->last_name)];
    }

    /** Is the current user – or the supervisor who entered their PIN – allowed to do $action? Returns approver id. */
    public function allowed(string $action, ?string $token = null): ?int
    {
        $permission = self::OVERRIDES[$action];
        $user = auth()->user();
        if ($user && $user->can($permission)) {
            return $user->id;
        }
        if ($token && ($data = Cache::get("pos_auth:{$token}")) && (int) $data['cashier'] === (int) auth()->id()) {
            $supervisor = User::find($data['user_id']);
            if ($supervisor && $supervisor->can($permission)) {
                return $supervisor->id;
            }
        }
        return null;
    }

    protected function requireApproval(string $action, string $message, ?string $token): int
    {
        $by = $this->allowed($action, $token);
        if (!$by) {
            throw new PosAuthorizationRequired($action, $message);
        }
        return $by;
    }

    // -----------------------------------------------------------------
    // Checkout
    // -----------------------------------------------------------------

    /**
     * @param array $data items[], customer_id, discount_type, discount_value, payments[], redeem_points,
     *                    note, held_sale_id, auth_token, client_ref
     */
    public function checkout(array $data): Order
    {
        $user = auth()->user();

        // Double-click / network retry protection
        if (!empty($data['client_ref'])) {
            $existing = Cache::get('pos_ref:' . $data['client_ref']);
            if ($existing && ($order = Order::find($existing))) {
                return $order;
            }
        }

        $shift = PosShift::currentFor($user->id);
        if (!$shift && SystemSetting::get('require_open_shift', false)) {
            throw ValidationException::withMessages(['shift' => 'Open a shift (cash drawer) before selling.']);
        }

        // Which till: the one the shift was opened on, else the one this browser is set to.
        $register = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('cash_registers')) {
            $rid = $shift?->cash_register_id ?: ($data['register_id'] ?? null);
            $register = $rid ? \App\Models\CashRegister::here()->where('status', 'active')->find($rid) : null;
        }
        $data['__register'] = $register;

        $customer = !empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
        $token = $data['auth_token'] ?? null;
        $maxDiscount = (float) ($user->max_discount_percent ?? SystemSetting::get('max_discount_percent', 10));
        $blockBelowCost = (bool) SystemSetting::get('block_sale_below_cost', true);

        $order = DB::transaction(function () use ($data, $user, $shift, $customer, $token, $maxDiscount, $blockBelowCost) {
            $orderId = \App\Services\MultiBranch\DocumentNumber::next('receipt');
            $lines = [];
            $subtotal = 0.0;
            $costTotal = 0.0;
            $approvals = [];

            foreach ($data['items'] as $i => $row) {
                $product = Product::with('packs.unit')->find($row['product_id']);
                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages(["items.$i" => 'A product in the cart is no longer available.']);
                }
                $variation = !empty($row['variation_id']) ? $product->variations()->find($row['variation_id']) : null;
                if ($product->product_type === 'variable' && !$variation && $product->variations()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(["items.$i" => "Choose a variation for {$product->title}."]);
                }
                $pack = !empty($row['pack_id']) ? $product->packs->firstWhere('id', (int) $row['pack_id']) : $product->basePack();

                $qty = round((float) $row['qty'], 3);
                if ($qty <= 0) {
                    continue;
                }
                if (!$product->is_weighted && abs($qty - round($qty)) > 0.0001 && (!$pack || $pack->quantity_per_unit >= 1) && !$this->isMeasuredPack($pack)) {
                    throw ValidationException::withMessages(["items.$i" => "{$product->title} is sold in whole units."]);
                }

                $quote = $this->pricing->quote($product, $variation, $pack, $customer);
                $unitPrice = $quote['price'];
                $overridden = false;

                // Price override
                if (isset($row['price']) && $row['price'] !== null && abs((float) $row['price'] - $unitPrice) >= 0.005) {
                    if (!$product->allow_price_override) {
                        $approvals['price'] = $this->requireApproval('price', "Changing the price of {$product->title} needs supervisor approval.", $token);
                    }
                    $unitPrice = round((float) $row['price'], 2);
                    $overridden = true;
                }

                $gross = round($unitPrice * $qty, 2);

                // Line discount
                $lineDiscount = 0.0;
                if (!empty($row['discount_value']) && (float) $row['discount_value'] > 0) {
                    $lineDiscount = ($row['discount_type'] ?? 'percent') === 'percent'
                        ? $gross * min(100, (float) $row['discount_value']) / 100
                        : min($gross, (float) $row['discount_value']);
                    $lineDiscount = round($lineDiscount, 2);
                    $pct = $gross > 0 ? $lineDiscount / $gross * 100 : 0;
                    if ($pct > $maxDiscount + 0.001) {
                        $approvals['discount'] = $this->requireApproval('discount', "Discount on {$product->title} is above your limit of {$maxDiscount}%.", $token);
                    }
                }

                $net = round($gross - $lineDiscount, 2);
                $lineCost = round($quote['cost'] * $qty, 2);

                if ($blockBelowCost && $lineCost > 0 && $net + 0.001 < $lineCost) {
                    $approvals['below_cost'] = $this->requireApproval('below_cost', "{$product->title} would be sold below cost price.", $token);
                }

                $baseQty = round($qty * (float) ($pack?->quantity_per_unit ?? 1), 3);

                $lines[] = compact('product', 'variation', 'pack', 'qty', 'unitPrice', 'gross', 'lineDiscount', 'net', 'lineCost', 'baseQty', 'overridden', 'quote');
                $subtotal += $net;
                $costTotal += $lineCost;
            }

            if (!$lines) {
                throw ValidationException::withMessages(['items' => 'The cart is empty.']);
            }

            // ---- order discount
            $orderDiscount = 0.0;
            if (!empty($data['discount_value']) && (float) $data['discount_value'] > 0) {
                $orderDiscount = ($data['discount_type'] ?? 'percent') === 'percent'
                    ? $subtotal * min(100, (float) $data['discount_value']) / 100
                    : min($subtotal, (float) $data['discount_value']);
                $orderDiscount = round($orderDiscount, 2);
                $pct = $subtotal > 0 ? $orderDiscount / $subtotal * 100 : 0;
                if ($pct > $maxDiscount + 0.001) {
                    $approvals['discount'] = $this->requireApproval('discount', "A {$maxDiscount}% discount is the most you can give without approval.", $token);
                }
            }

            // ---- loyalty points
            $pointsRedeemed = 0;
            $pointsDiscount = 0.0;
            if (!empty($data['redeem_points']) && $customer) {
                $balance = (int) (CustomerPoint::where('customer_id', $customer->id)->value('points') ?? 0);
                $pointsRedeemed = min((int) $data['redeem_points'], $balance);
                $pointsDiscount = round($pointsRedeemed / (float) config('loyalty.redeem_rate', 100), 2);
            }

            $total = round(max(0, $subtotal - $orderDiscount - $pointsDiscount), 2);

            // ---- payments
            $payments = collect($data['payments'] ?? [])->filter(fn ($p) => (float) ($p['amount'] ?? 0) > 0)->values();
            if ($payments->isEmpty()) {
                $payments = collect([['method' => 'cash', 'amount' => $total]]);
            }
            $paid = round((float) $payments->sum('amount'), 2);
            if ($paid + 0.009 < $total) {
                throw ValidationException::withMessages(['payments' => 'Amount paid (' . money($paid) . ') is less than the total (' . money($total) . ').']);
            }
            $nonCash = (float) $payments->where('method', '!=', 'cash')->sum('amount');
            if ($nonCash - 0.009 > $total) {
                throw ValidationException::withMessages(['payments' => 'Card / transfer payments cannot be more than the total. Only cash can give change.']);
            }
            $change = round($paid - $total, 2);

            $credit = (float) $payments->where('method', 'credit')->sum('amount');
            if ($credit > 0) {
                if (!$customer) {
                    throw ValidationException::withMessages(['payments' => 'Select a customer to sell on credit.']);
                }
                $limit = (float) ($customer->credit_limit ?? 0);
                $owing = (float) ($customer->outstanding_balance ?? 0);
                if ($limit > 0 && $owing + $credit > $limit + 0.009) {
                    throw ValidationException::withMessages(['payments' => "This sale would take {$customer->first_name} over their credit limit of " . money($limit) . '.']);
                }
            }

            // ---- payment checks that depend on settings
            $needsConfirm = (bool) SystemSetting::get('transfer_needs_confirmation', true);
            $canConfirm = $user->can('Confirm payments');
            if (SystemSetting::get('card_needs_terminal_ref', false)
                && $payments->contains(fn ($p) => $p['method'] === 'card' && empty($p['pending']) && trim((string) ($p['reference'] ?? '')) === '')) {
                throw ValidationException::withMessages(['payments' => 'Enter the card terminal reference (from the POS slip).']);
            }
            $payments = $payments->map(function ($p) use ($needsConfirm, $canConfirm) {
                // a card payment whose terminal timed out is kept as pending until someone checks the terminal / bank
                $p['status'] = (($p['method'] === 'transfer' && $needsConfirm && !(!empty($p['confirmed']) && $canConfirm))
                    || ($p['method'] === 'card' && !empty($p['pending']))) ? 'pending' : 'confirmed';
                return $p;
            });
            $pendingAmount = (float) $payments->where('status', 'pending')->sum('amount');

            // ---- stock (locks each product row); the till's own location if it has one
            $register = $data['__register'] ?? null;
            $location = $register?->location ?? $this->stock->defaultLocation();
            foreach ($lines as $l) {
                if ($l['product']->track_stock === false) {
                    continue;
                }
                $this->stock->record([
                    'product_id' => $l['product']->id,
                    'variation_id' => $l['variation']?->id,
                    'location_id' => $location->id,
                    'direction' => 'out',
                    'type' => 'out',
                    'quantity' => $l['baseQty'],
                    'unit_cost' => $l['baseQty'] > 0 ? $l['lineCost'] / $l['baseQty'] : null,
                    'reference_type' => 'sale',
                    'reference_number' => $orderId,
                    'notes' => 'POS sale',
                ]);
            }

            $methods = $payments->pluck('method')->unique();
            $order = Order::create([
                'id' => $orderId,
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'status' => 'completed',
                'total_amount' => $total,
                'order_date' => now(),
                'payment_method' => $methods->count() > 1 ? 'split' : $methods->first(),
                'payment_status' => $credit > 0 && $credit >= $total ? 'unpaid' : ($credit > 0 ? 'partial' : ($pendingAmount > 0 ? 'pending' : 'paid')),
                'paid_at' => now(),
            ]);
            $order->forceFill([
                'subtotal' => round($subtotal, 2),
                'discount_type' => $orderDiscount > 0 ? ($data['discount_type'] ?? 'percent') : null,
                'discount_value' => $orderDiscount > 0 ? (float) $data['discount_value'] : null,
                'discount_amount' => round($orderDiscount + $pointsDiscount, 2),
                'amount_paid' => $paid,
                'change_due' => $change,
                'cost_total' => round($costTotal, 2),
                'pos_shift_id' => $shift?->id,
                'cash_register_id' => $register?->id,
                'channel' => 'pos',
                'notes' => $data['note'] ?? null,
            ])->save();

            foreach ($lines as $l) {
                $unitName = $l['pack']?->unit?->name ?? ($l['product']->is_weighted ? 'Kilogram' : 'Piece');
                OrderItem::create([
                    'order_id' => $orderId,
                    'product_id' => $l['product']->id,
                    'variation_id' => $l['variation']?->id,
                    'title' => $l['product']->title . ($l['variation'] ? ' – ' . $l['variation']->label : ''),
                    'unit_price' => $l['unitPrice'],
                    'quantity' => $l['qty'],
                    'unit_id' => $l['pack']?->unit_id,
                    'unit_name' => $unitName,
                    'total_price' => $l['net'],
                    'selected_variation' => $l['variation']?->getAttribute('attributes'),
                ])->forceFill([
                    'sku' => $l['variation']?->sku ?: $l['product']->sku,
                    'barcode' => $l['variation']?->barcode ?: $l['product']->barcode,
                    'product_unit_id' => $l['pack']?->id,
                    'quantity_per_unit' => (float) ($l['pack']?->quantity_per_unit ?? 1),
                    'base_quantity' => $l['baseQty'],
                    'cost_price' => $l['qty'] > 0 ? round($l['lineCost'] / $l['qty'], 2) : 0,
                    'discount_amount' => $l['lineDiscount'],
                    'is_unit_mode' => (bool) $l['product']->is_weighted,
                    'price_overridden' => $l['overridden'],
                ])->save();

                DB::table('products')->where('id', $l['product']->id)->increment('sold_quantity', $l['baseQty']);
            }

            foreach ($payments as $p) {
                $amount = (float) $p['amount'];
                // Record cash net of change so drawer totals are right.
                if ($p['method'] === 'cash' && $change > 0) {
                    $amount = max(0, $amount - $change);
                }
                OrderPayment::create([
                    'order_id' => $orderId,
                    'method' => $p['method'],
                    'amount' => round($amount, 2),
                    'reference' => $p['reference'] ?? null,
                    'pos_shift_id' => $shift?->id,
                    'user_id' => $user->id,
                    'status' => $p['status'],
                    'confirmed_by' => $p['status'] === 'confirmed' && $p['method'] === 'transfer' ? $user->id : null,
                    'confirmed_at' => $p['status'] === 'confirmed' ? now() : null,
                    'cash_register_id' => $register?->id,
                ]);
            }

            if ($credit > 0 && $customer) {
                $customer->increment('outstanding_balance', $credit);
            }

            // ---- loyalty
            if ($customer) {
                $cp = CustomerPoint::firstOrCreate(['customer_id' => $customer->id], ['points' => 0]);
                if ($pointsRedeemed > 0) {
                    $cp->decrement('points', $pointsRedeemed);
                    CustomerPointTransaction::create([
                        'customer_id' => $customer->id, 'order_id' => $orderId, 'points_redeemed' => $pointsRedeemed,
                        'discount_applied' => $pointsDiscount, 'description' => "Redeemed {$pointsRedeemed} points",
                        'created_by' => $user->id,
                    ]);
                }
                $earned = (int) floor($total * (float) config('loyalty.earn_rate', 1));
                if ($earned > 0) {
                    $cp->increment('points', $earned);
                    CustomerPointTransaction::create([
                        'customer_id' => $customer->id, 'order_id' => $orderId, 'points_earned' => $earned,
                        'amount_spent' => $total, 'description' => "Earned from order #{$orderId}", 'created_by' => $user->id,
                    ]);
                }
            }

            if (!empty($data['held_sale_id'])) {
                PosHeldSale::where('id', $data['held_sale_id'])->delete();
            }

            try {
                $order->calculateAndSaveCommission();
            } catch (\Throwable $e) {
                report($e);
            }

            $desc = "POS sale {$orderId} – " . money($total) . ' (' . count($lines) . ' lines)';
            if ($approvals = array_filter($approvals)) {
                $desc .= ' – overrides: ' . implode(', ', array_keys($approvals));
            }
            AuditLogger::log('sale', $desc, 'pos', $order, null, [
                'total' => $total, 'discount' => $orderDiscount, 'payments' => $payments->all(), 'approved_by' => $approvals ?: null,
            ]);

            return $order;
        });

        if (!empty($data['client_ref'])) {
            Cache::put('pos_ref:' . $data['client_ref'], $order->id, now()->addMinutes(30));
        }
        if ($token) {
            Cache::forget("pos_auth:{$token}");
        }

        return $order;
    }

    // -----------------------------------------------------------------
    // Void & returns
    // -----------------------------------------------------------------

    public function void(Order $order, string $reason, ?int $approvedBy = null): Order
    {
        if ($order->status === 'cancelled') {
            throw new \RuntimeException('This sale has already been voided.');
        }

        DB::transaction(function () use ($order, $reason, $approvedBy) {
            $order->load('items.product');
            $location = $this->stock->defaultLocation();

            foreach ($order->items as $item) {
                if (!$item->product || $item->product->track_stock === false) {
                    continue;
                }
                $base = $this->baseQuantity($item) - (float) ($item->returned_quantity ?? 0) * (float) ($item->quantity_per_unit ?? 1);
                if ($base <= 0) {
                    continue;
                }
                $this->stock->record([
                    'product_id' => $item->product_id,
                    'variation_id' => $item->variation_id ?: null,
                    'location_id' => $location->id,
                    'direction' => 'in',
                    'type' => 'return',
                    'quantity' => $base,
                    'reference_type' => 'return',
                    'reference_number' => $order->id,
                    'notes' => "Sale voided: {$reason}",
                ]);
                DB::table('products')->where('id', $item->product_id)->where('sold_quantity', '>=', $base)->decrement('sold_quantity', $base);
            }

            // Reverse loyalty points and credit
            if ($order->customer_id) {
                $earned = (int) CustomerPointTransaction::where('order_id', $order->id)->sum('points_earned');
                $redeemed = (int) CustomerPointTransaction::where('order_id', $order->id)->sum('points_redeemed');
                if ($earned || $redeemed) {
                    $cp = CustomerPoint::firstOrCreate(['customer_id' => $order->customer_id], ['points' => 0]);
                    $cp->update(['points' => max(0, $cp->points - $earned + $redeemed)]);
                    CustomerPointTransaction::create([
                        'customer_id' => $order->customer_id, 'order_id' => $order->id,
                        'points_redeemed' => $earned, 'points_earned' => $redeemed,
                        'description' => "Reversed – order #{$order->id} voided", 'created_by' => auth()->id(),
                    ]);
                }
                $credit = (float) OrderPayment::where('order_id', $order->id)->where('method', 'credit')->sum('amount');
                if ($credit > 0) {
                    Customer::whereKey($order->customer_id)->decrement('outstanding_balance', $credit);
                }
            }

            $order->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'refunded',
                'voided_at' => now(),
                'voided_by' => $approvedBy ?? auth()->id(),
                'void_reason' => $reason,
            ])->save();

            if (\Illuminate\Support\Facades\Schema::hasTable('sales_commissions')) {
                DB::table('sales_commissions')->where('order_id', $order->id)->where('status', '!=', 'paid')->delete();
            }
        });

        AuditLogger::log('voided', "Sale {$order->id} voided (" . money($order->total_amount) . "): {$reason}", 'pos', $order,
            ['status' => 'completed'], ['status' => 'cancelled', 'approved_by' => $approvedBy]);

        return $order->fresh();
    }

    /**
     * @param array $lines [order_item_id => quantity (in the sold unit)]
     */
    public function returnItems(Order $order, array $lines, string $reason, string $method = 'cash', bool $restock = true): Refund
    {
        if ($order->status === 'cancelled') {
            throw new \RuntimeException('This sale was voided.');
        }

        $refund = DB::transaction(function () use ($order, $lines, $reason, $method, $restock) {
            $order->load('items.product');
            $amount = 0.0;
            $location = $this->stock->defaultLocation();
            $summary = [];

            foreach ($order->items as $item) {
                $qty = round((float) ($lines[$item->id] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }
                $remaining = (float) $item->quantity - (float) ($item->returned_quantity ?? 0);
                if ($qty > $remaining + 0.0001) {
                    throw ValidationException::withMessages(['items' => "Only " . qty($remaining) . " of {$item->title} can still be returned."]);
                }
                $lineValue = (float) $item->quantity > 0 ? (float) $item->total_price / (float) $item->quantity * $qty : 0;
                $amount += $lineValue;

                $item->forceFill(['returned_quantity' => (float) ($item->returned_quantity ?? 0) + $qty])->save();

                $base = $qty * (float) ($item->quantity_per_unit ?: 1);
                if ($restock && $item->product && $item->product->track_stock !== false) {
                    $this->stock->record([
                        'product_id' => $item->product_id,
                        'variation_id' => $item->variation_id ?: null,
                        'location_id' => $location->id,
                        'direction' => 'in',
                        'type' => 'return',
                        'quantity' => $base,
                        'reference_type' => 'return',
                        'reference_number' => $order->id,
                        'notes' => "Customer return: {$reason}",
                    ]);
                } elseif (!$restock && $item->product) {
                    // Returned damaged – log it without putting it back on the shelf.
                    $summary[] = "{$item->title} (not restocked)";
                }
                $summary[] = qty($qty) . ' × ' . $item->title;
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['items' => 'Choose at least one item to return.']);
            }

            // Order discount is shared proportionally.
            $subtotal = (float) ($order->subtotal ?: $order->items->sum('total_price'));
            if ($subtotal > 0 && (float) $order->discount_amount > 0) {
                $amount -= $amount * ((float) $order->discount_amount / $subtotal);
            }
            $amount = round(min($amount, $order->refundableAmount()), 2);

            $refund = Refund::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'reason' => $reason . ' [' . implode('; ', $summary) . '] via ' . $method,
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            $allReturned = $order->items->every(fn ($i) => (float) $i->returned_quantity >= (float) $i->quantity - 0.0001);
            $order->forceFill(['payment_status' => $allReturned ? 'refunded' : 'partially_refunded'])->save();
            if ($allReturned) {
                $order->update(['status' => 'refunded']);
            }

            return $refund;
        });

        AuditLogger::log('returned', "Return on {$order->id}: " . money($refund->amount) . " refunded – {$reason}", 'pos', $order, null,
            ['refund' => $refund->amount, 'lines' => $lines]);

        return $refund;
    }

    public function baseQuantity(OrderItem $item): float
    {
        if ($item->base_quantity !== null) {
            return (float) $item->base_quantity;
        }
        // Legacy rows: multiply by the pack size when it was a pack sale.
        if (!$item->is_unit_mode && $item->unit_id && $item->product) {
            $pack = ProductUnit::where('product_id', $item->product_id)->where('unit_id', $item->unit_id)->first();
            if ($pack && $pack->quantity_per_unit > 0) {
                return (float) $item->quantity * (float) $pack->quantity_per_unit;
            }
        }
        return (float) $item->quantity;
    }
}
