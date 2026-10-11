<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Brand;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\StoreSetting;


class InventoryController extends Controller
{
    public function __construct()
    {
        // Permissions (uncomment when ready)
        // $this->middleware('permission:View inventory', ['only' => ['index', 'show']]);
        // $this->middleware('permission:View stock levels', ['only' => ['stockLevels']]);
        // $this->middleware('permission:Manage inventory', ['only' => ['adjustStock', 'transferStock', 'bulkAdjust']]);
    }

    private function calculateProductStockFromInventory($productId)
    {
        $totalStock = Stock::where('product_id', $productId)
            ->selectRaw('
                SUM(CASE
                    WHEN type IN ("in", "adjustment", "transfer_in", "return") THEN quantity
                    WHEN type IN ("out", "damage", "transfer") THEN -quantity
                    ELSE 0
                END) as total
            ')
            ->value('total') ?? 0;

        return max(0, $totalStock);
    }

    private function updateProductStock($productId)
    {
        try {
            $product = Product::find($productId);
            if (!$product) {
                Log::error("Product {$productId} not found when updating stock");
                return 0;
            }

            $calculatedStock = $this->calculateProductStockFromInventory($productId);
            $oldStock = $product->stock;

            Product::where('id', $productId)->update(['stock' => $calculatedStock]);
            $product->refresh();

            Log::info("Updated product {$productId} stock: {$oldStock} → {$calculatedStock}");

            return $calculatedStock;
        } catch (\Exception $e) {
            Log::error("Failed to update product stock for {$productId}: " . $e->getMessage());
            return 0;
        }
    }

    public function index(Request $request)
    {
        $pagetitle = "Inventory Management";

        $query = Stock::with(['product', 'user', 'stockLocation', 'destinationLocation'])
            ->latest('transaction_date');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('location_id')) {
            $query->where('stock_location_id', $request->location_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('reference_number')) {
            $query->where('reference_number', 'like', "%{$request->reference_number}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $transactions = $query->paginate(25)->withQueryString();

        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'price']);
        $locations = StockLocation::orderBy('name')->get();

        $users = \App\Models\User::whereHas('stocks')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $summary = [
            'total_in' => Stock::where('type', 'in')->sum('quantity'),
            'total_out' => Stock::where('type', 'out')->sum('quantity'),
            'total_adjustments' => Stock::where('type', 'adjustment')->count(),
            'total_transfers' => Stock::where('type', 'transfer')->count(),
            'total_returns' => Stock::where('type', 'return')->count(),
            'total_damages' => Stock::where('type', 'damage')->count(),
            'total_value' => Stock::where('type', 'in')->sum(DB::raw('COALESCE(quantity * unit_cost, 0)')),
        ];

        $recentActivity = Stock::with(['product', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        return view('inventory.index', compact(
            'pagetitle',
            'transactions',
            'products',
            'locations',
            'users',
            'summary',
            'recentActivity'
        ));
    }

    public function dashboard()
    {
        $pagetitle = "Inventory Dashboard";

        $totalProducts = Product::count();
        $totalLocations = StockLocation::count();

        $locations = StockLocation::withCount(['stocks as total_items' => function($query) {
            $query->select(DB::raw('SUM(CASE WHEN type IN ("in", "adjustment", "transfer") THEN quantity ELSE -quantity END)'));
        }])->get();

        $lowStockProducts = Product::where('stock', '>', 0)
            ->where('stock', '<=', 10)
            ->orderBy('stock')
            ->limit(10)
            ->get();

        $recentTransactions = Stock::with(['product', 'stockLocation'])
            ->latest()
            ->limit(10)
            ->get();

        $monthlyMovements = Stock::select(
                DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN type IN ("in", "adjustment", "transfer") THEN quantity ELSE 0 END) as stock_in'),
                DB::raw('SUM(CASE WHEN type IN ("out", "damage") THEN quantity ELSE 0 END) as stock_out')
            )
            ->where('transaction_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $stockValueByLocation = StockLocation::select('stock_locations.*')
            ->selectSub(function($query) {
                $query->selectRaw('SUM(
                    CASE
                        WHEN stocks.type IN ("in", "adjustment", "transfer") THEN stocks.quantity * COALESCE(stocks.unit_cost, products.price)
                        WHEN stocks.type IN ("out", "damage") THEN -stocks.quantity * COALESCE(stocks.unit_cost, products.price)
                        ELSE 0
                    END
                )')
                ->from('stocks')
                ->join('products', 'stocks.product_id', '=', 'products.id')
                ->whereColumn('stocks.stock_location_id', 'stock_locations.id');
            }, 'total_value')
            ->orderBy('total_value', 'desc')
            ->get();

        return view('inventory.dashboard', compact(
            'pagetitle',
            'totalProducts',
            'totalLocations',
            'locations',
            'lowStockProducts',
            'recentTransactions',
            'monthlyMovements',
            'stockValueByLocation'
        ));
    }

    public function stockLevels(Request $request)
    {
        $pagetitle = "Stock Levels Report";

        $query = Product::with(['category', 'brand'])->select('products.*');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('stock', '>', 10);
                    break;
                case 'low_stock':
                    $query->where('stock', '>', 0)->where('stock', '<=', 10);
                    break;
                case 'out_of_stock':
                    $query->where('stock', '=', 0);
                    break;
                case 'negative_stock':
                    $query->where('stock', '<', 0);
                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('brand', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $request->get('sort_by', 'title');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $products = $query->paginate(25)->withQueryString();

        $locations = StockLocation::orderBy('name')->get();

        $locationStockData = [];
        foreach ($products as $product) {
            foreach ($locations as $location) {
                $locationStockData[$product->id][$location->id] = $location->getProductStock($product->id);
            }
        }

        // Summary for cards and doughnut chart
        $summaryCounts = Product::selectRaw('
            COUNT(*) as total_products,
            SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock_count,
            SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
        ')->first();

        $summary = [
            'total_products' => $summaryCounts->total_products,
            'in_stock'       => $summaryCounts->in_stock_count,
            'low_stock'      => $summaryCounts->low_stock_count,
            'out_of_stock'   => $summaryCounts->out_of_stock_count,
        ];

        // Total stock per location for bar chart
        $locationStockTotals = [];
        $allProductIds = Product::pluck('id');
        foreach ($locations as $location) {
            $total = 0;
            foreach ($allProductIds as $productId) {
                $total += $location->getProductStock($productId);
            }
            $locationStockTotals[$location->id] = $total;
        }

        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return view('inventory.stock-levels', compact(
            'pagetitle',
            'products',
            'locations',
            'locationStockData',
            'categories',
            'brands',
            'summary',
            'locationStockTotals'
        ));
    }

    public function stockHistory($id)
    {
        $product = Product::with(['category', 'brand'])->findOrFail($id);
        $pagetitle = "Stock History - {$product->title}";

        $history = Stock::with(['user', 'stockLocation', 'destinationLocation'])
            ->where('product_id', $id)
            ->latest('transaction_date')
            ->paginate(20);

        $locations = StockLocation::get();
        $locationStock = [];

        foreach ($locations as $location) {
            $locationStock[$location->id] = [
                'name' => $location->name,
                'stock' => $location->getProductStock($id)
            ];
        }

        $movementData = StockMovement::where('product_id', $id)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN movement_type IN ("in", "adjustment", "transfer_in") THEN quantity ELSE -quantity END) as daily_change'),
                DB::raw('MAX(balance) as closing_balance')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('inventory.stock-history', compact(
            'pagetitle',
            'product',
            'history',
            'locations',
            'locationStock',
            'movementData'
        ));
    }

    public function adjustStock(Request $request)
    {
        // Every manual change now goes through the stock-adjustment workflow so it is
        // confirmed (approved) before it touches stock, and appears in the audit trail.
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'location_id' => 'required|exists:stock_locations,id',
            'adjustment_type' => 'required|in:add,remove,set',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $adj = app(\App\Services\StockAdjustmentService::class)->create([
                'type' => $request->adjustment_type === 'set' ? 'count' : 'adjustment',
                'location_id' => $request->location_id,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'items' => [[
                    'product_id' => $request->product_id,
                    'variation_id' => $request->variation_id,
                    'mode' => $request->adjustment_type,
                    'quantity' => $request->quantity,
                    'unit_cost' => $request->unit_cost,
                ]],
            ], 'submit');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to adjust stock: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $adj->status,
            'message' => $adj->status === 'approved'
                ? 'Stock adjusted successfully'
                : "Adjustment {$adj->reference} submitted for approval.",
            'reference' => $adj->reference,
            'product_stock' => (float) Product::find($request->product_id)->stock,
        ]);
    }
    public function transferStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'from_location_id' => 'required|exists:stock_locations,id',
            'to_location_id' => 'required|exists:stock_locations,id|different:from_location_id',
            'quantity' => 'required|numeric|min:0.001',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            [$out] = app(\App\Services\StockService::class)->transfer(
                (int) $request->product_id,
                $request->variation_id ? (int) $request->variation_id : null,
                (int) $request->from_location_id,
                (int) $request->to_location_id,
                (float) $request->quantity,
                array_filter([
                    'reference_number' => $request->reference_number,
                    'notes' => $request->notes,
                    'unit_cost' => $request->unit_cost,
                ], fn ($v) => $v !== null && $v !== '')
            );
        } catch (\App\Exceptions\InsufficientStockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to transfer stock: ' . $e->getMessage()], 500);
        }

        \App\Services\AuditLogger::log('transferred', 'Transferred ' . qty($request->quantity) . ' of ' . Product::find($request->product_id)->title, 'inventory', $out);

        return response()->json([
            'success' => true,
            'message' => 'Stock transferred successfully',
            'stock' => $out->load(['product', 'user', 'stockLocation', 'destinationLocation']),
            'product_stock' => (float) Product::find($request->product_id)->stock,
        ]);
    }
    public function bulkAdjust(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.variation_id' => 'nullable|exists:product_variations,id',
            'products.*.quantity' => 'required|numeric|min:0',
            'location_id' => 'required|exists:stock_locations,id',
            'adjustment_type' => 'required|in:add,remove,set',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $adj = app(\App\Services\StockAdjustmentService::class)->create([
                'type' => $request->adjustment_type === 'set' ? 'count' : 'adjustment',
                'location_id' => $request->location_id,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'items' => collect($request->products)->map(fn ($p) => [
                    'product_id' => $p['id'],
                    'variation_id' => $p['variation_id'] ?? null,
                    'mode' => $request->adjustment_type,
                    'quantity' => $p['quantity'],
                ])->all(),
            ], 'submit');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to perform bulk adjustment: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $adj->status,
            'message' => $adj->status === 'approved'
                ? 'Bulk adjustment completed successfully'
                : "Adjustment {$adj->reference} ({$adj->items_count} products) submitted for approval.",
            'count' => $adj->items_count,
            'reference' => $adj->reference,
        ]);
    }
    public function show($id)
    {
        try {
            $stock = Stock::with(['product', 'user', 'stockLocation', 'destinationLocation'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'stock' => $stock
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch stock transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction'
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $stock = Stock::findOrFail($id);
            $productId = $stock->product_id;

            $hoursOld = now()->diffInHours($stock->created_at);
            if ($hoursOld > 24 && !auth()->user()->hasRole('Admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete transactions older than 24 hours'
                ], 400);
            }

            StockMovement::where('stock_id', $id)->delete();

            $stock->delete();

            $newProductStock = $this->updateProductStock($productId);

            DB::commit();
            Log::info("Stock transaction {$id} deleted successfully");

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully',
                'product_stock' => $newProductStock
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProductStock($productId, $locationId)
    {
        try {
            $product = Product::findOrFail($productId);
            $location = StockLocation::findOrFail($locationId);

            $stock = $location->getProductStock($productId);

            return response()->json([
                'success' => true,
                'stock' => $stock,
                'product_total_stock' => $product->stock,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => $product->price
                ],
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get stock level: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stock level: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportTransactions(Request $request)
    {
        $query = Stock::with(['product', 'stockLocation', 'user'])
            ->latest('transaction_date');

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $transactions = $query->get();

        $filename = 'inventory-transactions-' . date('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Date', 'Type', 'Product', 'SKU', 'Location',
                'Quantity', 'Unit Cost', 'Total Cost', 'Reference',
                'Reason', 'User', 'Notes'
            ]);

            foreach ($transactions as $transaction) {
                $userName = $transaction->user ?
                    $transaction->user->first_name . ' ' . $transaction->user->last_name :
                    'System';

                fputcsv($file, [
                    $transaction->transaction_date->format('Y-m-d H:i:s'),
                    ucfirst($transaction->type),
                    $transaction->product->title,
                    $transaction->product->sku,
                    $transaction->stockLocation->name,
                    $transaction->quantity,
                    $transaction->unit_cost ? currency_symbol() . number_format($transaction->unit_cost, 2) : '',
                    $transaction->total_cost ? currency_symbol() . number_format($transaction->total_cost, 2) : '',
                    $transaction->reference_number,
                    $transaction->adjustment_reason ?? '',
                    $userName,
                    $transaction->notes ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportStockLevels(Request $request)
    {
        $products = Product::with(['category', 'brand'])
            ->orderBy('stock')
            ->get();

        $locations = StockLocation::get();

        $filename = 'stock-levels-' . date('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($products, $locations) {
            $file = fopen('php://output', 'w');

            $headerRow = ['Product', 'SKU', 'Category', 'Brand', 'Price', 'Total Stock'];
            foreach ($locations as $location) {
                $headerRow[] = $location->name;
            }
            $headerRow[] = 'Status';

            fputcsv($file, $headerRow);

            foreach ($products as $product) {
                $row = [
                    $product->title,
                    $product->sku,
                    $product->category->name ?? '',
                    $product->brand->name ?? '',
                    currency_symbol() . number_format($product->price, 2),
                    $product->stock
                ];

                foreach ($locations as $location) {
                    $row[] = $location->getProductStock($product->id);
                }

                $stock = $product->stock;
                if ($stock > 10) {
                    $status = 'In Stock';
                } elseif ($stock > 0) {
                    $status = 'Low Stock';
                } else {
                    $status = 'Out of Stock';
                }
                $row[] = $status;

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,txt,xlsx,xls',
            'location_id' => 'required|exists:stock_locations,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import functionality to be implemented'
        ]);
    }

    public function getLowStockAlerts()
    {
        $lowStockProducts = Product::where('stock', '>', 0)
            ->where('stock', '<=', 10)
            ->orderBy('stock')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $lowStockProducts->count(),
            'products' => $lowStockProducts
        ]);
    }

    public function getStockValueReport()
    {
        $locations = StockLocation::get();
        $report = [];

        foreach ($locations as $location) {
            $value = $location->total_value ?? 0;
            if ($value > 0) {
                $report[] = [
                    'location' => $location->name,
                    'value' => $value,
                    'formatted_value' => currency_symbol() . number_format($value, 2),
                    'product_count' => $location->total_products ?? 0
                ];
            }
        }

        usort($report, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        $totalValue = array_sum(array_column($report, 'value'));

        return response()->json([
            'success' => true,
            'total_value' => $totalValue,
            'formatted_total' => currency_symbol() . number_format($totalValue, 2),
            'locations' => $report
        ]);
    }

    public function lowStockAlerts(Request $request)
    {
        $pagetitle = "Low Stock Alerts";

        $threshold = $request->filled('threshold') ? (int)$request->threshold : 10;
        $forceRecalculate = $request->has('recalculate');

        $query = Product::with(['category', 'brand'])
            ->select('products.*');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('category', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('brand', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $allProducts = $query->get();

        $debugInfo = [];

        $lowStockProducts = collect();

        foreach ($allProducts as $product) {
            $calculatedStock = $product->calculateCurrentStock();

            if (count($debugInfo) < 5) {
                $debugInfo[] = [
                    'id' => $product->id,
                    'name' => $product->title,
                    'db_stock' => $product->stock,
                    'calculated_stock' => $calculatedStock,
                    'is_low' => ($calculatedStock > 0 && $calculatedStock <= $threshold)
                ];
            }

            if ($calculatedStock > 0 && $calculatedStock <= $threshold) {
                $product->current_calculated_stock = $calculatedStock;
                $product->is_critical = ($calculatedStock <= 3);
                $product->is_action_required = ($calculatedStock <= 5);

                $lowStockProducts->push($product);
            }
        }

        $lowStockProducts = $lowStockProducts->sortBy('current_calculated_stock');

        $page = $request->get('page', 1);
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $lowStockProducts->slice($offset, $perPage)->values(),
            $lowStockProducts->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $products = $paginatedProducts;

        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();
        $locations = StockLocation::orderBy('name')->get();

        $locationStockData = [];
        foreach ($products as $product) {
            $locationStockData[$product->id] = [];
            foreach ($locations as $location) {
                $locationStockData[$product->id][$location->id] = $product->getStockByLocation($location->id);
            }
        }

        $totalLowStock = $lowStockProducts->count();
        $criticalCount = $lowStockProducts->where('is_critical', true)->count();
        $actionRequiredCount = $lowStockProducts->where('is_action_required', true)->count();

        $totalProducts = $allProducts->count();
        $productsWithStock = $allProducts->filter(function($p) {
            return $p->calculateCurrentStock() > 0;
        })->count();

        return view('inventory.low-stock-alerts', compact(
            'pagetitle',
            'products',
            'categories',
            'brands',
            'locations',
            'locationStockData',
            'threshold',
            'totalLowStock',
            'criticalCount',
            'actionRequiredCount',
            'totalProducts',
            'productsWithStock',
            'debugInfo',
            'forceRecalculate'
        ));
    }

    public function recalculateStock(Request $request)
    {
        try {
            Artisan::call('products:sync-stock');

            return redirect()->route('inventory.low-stock-alerts')
                ->with('success', 'All product stocks have been recalculated from inventory transactions!');

        } catch (\Exception $e) {
            Log::error('Failed to recalculate stock: ' . $e->getMessage());

            return redirect()->route('inventory.low-stock-alerts')
                ->with('error', 'Failed to recalculate stock: ' . $e->getMessage());
        }
    }

    public function stockValueReport(Request $request)
    {
        $pagetitle = "Stock Value Report";

        $locations = StockLocation::get();
        $report = [];

        foreach ($locations as $location) {
            $value = $location->total_value ?? 0;
            if ($value > 0) {
                $report[] = [
                    'location' => $location,
                    'value' => $value,
                    'formatted_value' => currency_symbol() . number_format($value, 2),
                    'product_count' => $location->total_products ?? 0
                ];
            }
        }

        usort($report, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        $totalValue = array_sum(array_column($report, 'value'));

        return view('inventory.stock-value-report', compact(
            'pagetitle',
            'report',
            'totalValue'
        ));
    }

    public function getLocationStock($productId, $locationId)
    {
        try {
            $product = Product::findOrFail($productId);
            $location = StockLocation::findOrFail($locationId);

            $stock = $location->getProductStock($productId);

            return response()->json([
                'success' => true,
                'stock' => $stock,
                'product_total_stock' => $product->stock,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => $product->price
                ],
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get stock level: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stock level: ' . $e->getMessage()
            ], 500);
        }
    }

    public function realtimeProductStock()
    {
        $products = Product::select('id', 'title', 'sku', 'stock')->get();
        return response()->json($products);
    }

    public function syncAllProductStocks()
    {
        try {
            $count = app(\App\Services\StockService::class)->rebuildAll();
            \App\Services\AuditLogger::log('stock_synced', "Rebuilt cached stock balances for {$count} products", 'inventory');
            return response()->json(['success' => true, 'message' => "Synced stock for {$count} products (and their variations).", 'updated_count' => $count]);
        } catch (\Exception $e) {
            Log::error('Failed to sync product stocks: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to sync product stocks: ' . $e->getMessage()], 500);
        }
    }
    public function getStockHistory($id, Request $request)
{
    $product = Product::with(['category', 'brand'])->findOrFail($id);

    $query = Stock::with(['user', 'stockLocation', 'destinationLocation'])
        ->where('product_id', $id);

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('reference_number', 'like', "%{$search}%")
              ->orWhere('adjustment_reason', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('user', function($q) use ($search) {
                  $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
              });
        });
    }

    if ($request->filled('date_from')) {
        $query->whereDate('transaction_date', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('transaction_date', '<=', $request->date_to);
    }

    $history = $query->latest('transaction_date')->paginate(20);

    return response()->json([
        'success' => true,
        'product' => [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'stock' => $product->stock
        ],
        'history' => $history
    ]);
}



public function exportStockLevelsPDF(Request $request)
{
    // Reuse filtering logic from stockLevels()
    $query = Product::with(['category', 'brand'])->select('products.*');

    if ($request->filled('category_id')) {
        $query->where('category_id', $request->category_id);
    }
    if ($request->filled('brand_id')) {
        $query->where('brand_id', $request->brand_id);
    }
    if ($request->filled('stock_status')) {
        switch ($request->stock_status) {
            case 'in_stock':
                $query->where('stock', '>', 10);
                break;
            case 'low_stock':
                $query->where('stock', '>', 0)->where('stock', '<=', 10);
                break;
            case 'out_of_stock':
                $query->where('stock', '=', 0);
                break;
            case 'negative_stock':
                $query->where('stock', '<', 0);
                break;
        }
    }
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhereHas('category', fn($q) => $q->where('name', 'like', "%{$search}%"))
              ->orWhereHas('brand', fn($q) => $q->where('name', 'like', "%{$search}%"));
        });
    }

    $sortBy = $request->get('sort_by', 'title');
    $sortOrder = $request->get('sort_order', 'asc');
    $query->orderBy($sortBy, $sortOrder);

    $products = $query->get();

    $locations = StockLocation::orderBy('name')->get();

    $locationStockData = [];
    foreach ($products as $product) {
        foreach ($locations as $location) {
            $locationStockData[$product->id][$location->id] = $location->getProductStock($product->id);
        }
    }

    // Store settings with logo
    $settings = StoreSetting::getSettings();

    // Date information
    $generatedAt = now()->format('F j, Y \a\t g:i A');
    $stockAsOfDate = now()->format('F j, Y'); // Stock levels are current as of today

    $data = [
        'products'          => $products,
        'locations'         => $locations,
        'locationStockData' => $locationStockData,
        'settings'          => $settings,
        'filters'           => $request->query(),
        'totalProducts'     => $products->count(),
        'generatedAt'       => $generatedAt,
        'stockAsOfDate'     => $stockAsOfDate,
        'generatedBy'       => auth()->user()->name ?? 'System',
    ];

    $pdf = Pdf::loadView('inventory.pdf.stock-levels', $data)
              ->setPaper('a4', 'landscape');

    return $pdf->download('stock-levels-' . now()->format('Y-m-d-His') . '.pdf');
}
}
