<?php

namespace App\Services\Catalogue;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Services\MultiBranch\Install;
use Illuminate\Support\Facades\Schema;

/**
 * Branch prices.  The catalogue price (products / variations / packs) is the default;
 * a live price list for this branch can replace it item by item.
 *
 *   precedence: branch list (highest priority first) → all-branch list → catalogue
 *
 * The resolver returns copies of the models with the list prices filled in, so the
 * normal pricing rules (packs, wholesale, promo) run unchanged on top.
 */
class PriceListResolver
{
    /** @var array<int, array<string, PriceListItem>> product id → item_key → best item */
    protected array $memo = [];
    protected ?bool $ready = null;
    protected ?array $listIds = null;

    public function flush(): void
    {
        $this->memo = [];
        $this->listIds = null;
    }

    protected function ready(): bool
    {
        return $this->ready ??= Schema::hasTable('price_list_items');
    }

    /** Live list ids, best first. */
    public function liveListIds(?int $branchId = null): array
    {
        if ($this->listIds !== null && $branchId === null) {
            return $this->listIds;
        }
        $bid = $branchId ?? Install::branchId();
        $ids = PriceList::liveFor($bid)
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('priority')->orderByDesc('id')
            ->pluck('id')->all();
        if ($branchId === null) {
            $this->listIds = $ids;
        }
        return $ids;
    }

    /** @return array<string, PriceListItem> */
    public function itemsFor(int $productId): array
    {
        if (!$this->ready()) {
            return [];
        }
        if (array_key_exists($productId, $this->memo)) {
            return $this->memo[$productId];
        }
        $ids = $this->liveListIds();
        $best = [];
        if ($ids) {
            $rank = array_flip($ids);
            $items = PriceListItem::whereIn('price_list_id', $ids)->where('product_id', $productId)->get()
                ->sortBy(fn ($i) => $rank[$i->price_list_id] ?? 999);
            foreach ($items as $i) {
                $best[$i->item_key] ??= $i;
            }
        }
        return $this->memo[$productId] = $best;
    }

    /**
     * Load the price-list rows for many products in ONE query (search results, product grid),
     * so pricing a page of 20–60 products doesn't run a query per product.
     */
    public function prime(array $productIds): void
    {
        if (!$this->ready()) {
            return;
        }
        $ids = array_values(array_diff(array_unique(array_map('intval', $productIds)), array_keys($this->memo)));
        if (!$ids) {
            return;
        }
        foreach ($ids as $pid) {
            $this->memo[$pid] = [];
        }
        $lists = $this->liveListIds();
        if (!$lists) {
            return;
        }
        $rank = array_flip($lists);
        PriceListItem::with('priceList')
            ->whereIn('price_list_id', $lists)->whereIn('product_id', $ids)->get()
            ->sortBy(fn ($i) => $rank[$i->price_list_id] ?? 999)
            ->each(function ($i) {
                $this->memo[$i->product_id][$i->item_key] ??= $i;
            });
    }

    public function item(int $productId, ?int $variationId = null, ?int $packId = null): ?PriceListItem
    {
        return $this->itemsFor($productId)[PriceListItem::keyFor($productId, $variationId, $packId)] ?? null;
    }

    /**
     * @return array{0: Product, 1: ?ProductVariation, 2: ?ProductUnit, 3: ?string} models with list prices, and the list code used
     */
    public function apply(Product $product, ?ProductVariation $variation = null, ?ProductUnit $pack = null): array
    {
        $items = $this->itemsFor($product->id);
        if (!$items) {
            return [$product, $variation, $pack, null];
        }
        $used = null;

        if ($i = $items[PriceListItem::keyFor($product->id)] ?? null) {
            $product = $this->override(clone $product, $i, true);
            $used = $i;
        }
        if ($variation) {
            if ($i = $items[PriceListItem::keyFor($product->id, $variation->id)] ?? null) {
                $variation = $this->override(clone $variation, $i, false);
                $used = $i;
            } elseif ($used) {
                $variation = clone $variation;
            }
            $variation?->setRelation('product', $product);
        }
        if ($pack && ($i = $items[PriceListItem::keyFor($product->id, null, $pack->id)] ?? null)) {
            $pack = clone $pack;
            if ($i->price !== null) {
                $pack->price = $i->price;
            }
            if ($i->wholesale_price !== null) {
                $pack->wholesale_price = $i->wholesale_price;
            }
            $used = $i;
        }

        return [$product, $variation, $pack, $used ? ($used->priceList->code ?? 'list') : null];
    }

    protected function override($model, PriceListItem $i, bool $isProduct)
    {
        if ($i->price !== null) {
            $model->price = $i->price;
            $model->sale_price = null;
        }
        if ($i->wholesale_price !== null) {
            $model->wholesale_price = $i->wholesale_price;
        }
        if ($isProduct && $i->promo_price !== null) {
            $model->promo_price = $i->promo_price;
            $model->promo_starts_at = $i->promo_starts_at;
            $model->promo_ends_at = $i->promo_ends_at;
        }
        return $model;
    }

    /** Write a price into a list (used when head office approves a branch request, or edits a branch price). */
    public function set(PriceList $list, Product $product, ?ProductVariation $variation, ?ProductUnit $pack, array $values, ?int $userId = null): PriceListItem
    {
        $item = PriceListItem::firstOrNew([
            'price_list_id' => $list->id,
            'item_key' => PriceListItem::keyFor($product->id, $variation?->id, $pack?->id),
        ]);
        $item->fill([
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'product_unit_id' => $pack?->id,
            'updated_by' => $userId,
        ] + array_intersect_key($values, array_flip(['price', 'wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at'])));
        $item->save();
        $this->flush();
        return $item;
    }
}
