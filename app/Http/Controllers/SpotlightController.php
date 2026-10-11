<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Data search for the Spotlight box in the top bar (Ctrl/⌘ + K).
 * Pages are searched in the browser from the sidebar; this adds products,
 * customers and receipts – each only when the user may view them.
 */
class SpotlightController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $results = [];

        if ($user->canAny(['View product', 'Create product', 'Update product', 'Delete product']) && Route::has('products.show')) {
            $this->safely('products', function () use ($q, $like, &$results) {
                Product::query()
                    ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', $q))
                    ->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END', [$q, $q, addcslashes($q, '%_\\') . '%'])
                    ->orderBy('title')
                    ->limit(6)
                    ->get(['id', 'title', 'sku', 'price', 'stock', 'track_stock'])
                    ->each(function ($p) use (&$results) {
                        $results[] = [
                            'title' => $p->title,
                            'subtitle' => implode(' · ', array_filter([
                                $p->sku,
                                money($p->price),
                                $p->track_stock === false ? null : qty($p->stock) . ' in stock',
                            ])),
                            'url' => route('products.show', $p->id),
                            'icon' => 'mdi-package-variant-closed',
                            'category' => 'Products',
                        ];
                    });
            });
        }

        if ($user->canAny(['View customer', 'View customers', 'Manage customer']) && Route::has('customers.show')) {
            $this->safely('customers', function () use ($like, &$results) {
                Customer::query()
                    ->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                        ->orWhere('phone_number', 'like', $like)->orWhere('email', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]))
                    ->orderBy('first_name')
                    ->limit(5)
                    ->get(['id', 'first_name', 'last_name', 'phone_number', 'customer_type'])
                    ->each(function ($c) use (&$results) {
                        $results[] = [
                            'title' => trim($c->first_name . ' ' . $c->last_name),
                            'subtitle' => implode(' · ', array_filter([$c->phone_number, ucfirst((string) $c->customer_type)])),
                            'url' => route('customers.show', $c->id),
                            'icon' => 'mdi-account',
                            'category' => 'Customers',
                        ];
                    });
            });
        }

        if ($user->can('View order') && Route::has('orders.show')) {
            $this->safely('orders', function () use ($q, &$results) {
                Order::query()
                    ->where('id', 'like', addcslashes($q, '%_\\') . '%')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'total_amount', 'status', 'created_at'])
                    ->each(function ($o) use (&$results) {
                        $results[] = [
                            'title' => 'Receipt ' . $o->id,
                            'subtitle' => implode(' · ', array_filter([money($o->total_amount), ucfirst((string) $o->status), optional($o->created_at)->format('d M Y, H:i')])),
                            'url' => route('orders.show', $o->id),
                            'icon' => 'mdi-receipt',
                            'category' => 'Receipts',
                        ];
                    });
            });
        }

        return response()->json(['results' => $results]);
    }

    /** One failing source (e.g. a missing column) must not break the whole search. */
    protected function safely(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning("Spotlight search ({$what}) failed: " . $e->getMessage());
        }
    }
}
