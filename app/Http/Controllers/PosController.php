<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\CustomerPoint;
use App\Models\CustomerPointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index()
    {
        $pagetitle = "Point of Sale";
        $customers = Customer::orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone_number', 'company_name']);

        return view('pos.index', compact('customers', 'pagetitle'));
    }

    public function search(Request $request)
    {
        $query = $request->q;

        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        // First, try exact barcode match in products
        $barcodeProduct = Product::where('barcode', $query)
            ->where('is_active', true)
            ->with(['units'])
            ->first();

        if ($barcodeProduct) {
            return $this->formatProductResponse($barcodeProduct);
        }

        // Then try exact barcode match in product variations
        $barcodeVariation = ProductVariation::where('barcode', $query)
            ->with(['product.units', 'product'])
            ->first();

        if ($barcodeVariation && $barcodeVariation->product->is_active) {
            return $this->formatVariationResponse($barcodeVariation);
        }

        // If no exact barcode match, do regular search
        $products = Product::where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->where('is_active', true)
            ->with(['units'])
            ->limit(20)
            ->get()
            ->map(function($product) {
                return $this->formatProductData($product);
            });

        // Also search in product variations
        $variations = ProductVariation::where(function($q) use ($query) {
                $q->where('sku', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->with(['product.units', 'product'])
            ->whereHas('product', function($q) {
                $q->where('is_active', true);
            })
            ->limit(20)
            ->get()
            ->map(function($variation) {
                return $this->formatVariationData($variation);
            });

        // Merge and return results
        $results = $products->merge($variations)->take(20);

        return response()->json($results);
    }

    private function formatProductResponse($product)
    {
        $primaryUnit = $product->units->first();
        return response()->json([
            [
                'id' => $product->id,
                'title' => $product->title,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => $product->price,
                'sale_price' => $product->sale_price ?? $product->price,
                'stock' => $product->current_stock,
                'thumbnail' => $product->thumbnail ? asset('storage/' . $product->thumbnail) : null,
                'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
                'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
                'units' => $product->units->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'short_name' => $unit->short_name,
                        'description' => $unit->description,
                        'is_default' => $unit->is_default ?? false,
                        'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                    ];
                }),
                'is_variation' => false,
                'variation_id' => null,
                'variation_attributes' => null
            ]
        ]);
    }

    private function formatVariationResponse($variation)
    {
        $product = $variation->product;
        $primaryUnit = $product->units->first();

        return response()->json([
            [
                'id' => $product->id,
                'variation_id' => $variation->id,
                'title' => $product->title . ($variation->attributes ? ' - ' . implode(', ', $variation->attributes) : ''),
                'sku' => $variation->sku,
                'barcode' => $variation->barcode,
                'price' => $variation->price,
                'sale_price' => $variation->sale_price ?? $variation->price,
                'stock' => $variation->stock,
                'thumbnail' => $variation->image ? asset('storage/' . $variation->image) :
                             ($product->thumbnail ? asset('storage/' . $product->thumbnail) : null),
                'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
                'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
                'units' => $product->units->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'short_name' => $unit->short_name,
                        'description' => $unit->description,
                        'is_default' => $unit->is_default ?? false,
                        'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                    ];
                }),
                'is_variation' => true,
                'variation_attributes' => $variation->attributes
            ]
        ]);
    }

    private function formatProductData($product)
    {
        $primaryUnit = $product->units->first();
        return [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => $product->price,
            'sale_price' => $product->sale_price ?? $product->price,
            'stock' => $product->current_stock,
            'thumbnail' => $product->thumbnail ? asset('storage/' . $product->thumbnail) : null,
            'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
            'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
            'units' => $product->units->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'short_name' => $unit->short_name,
                    'description' => $unit->description,
                    'is_default' => $unit->is_default ?? false,
                    'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                ];
            }),
            'is_variation' => false,
            'variation_id' => null,
            'variation_attributes' => null
        ];
    }

    private function formatVariationData($variation)
    {
        $product = $variation->product;
        $primaryUnit = $product->units->first();

        return [
            'id' => $product->id,
            'variation_id' => $variation->id,
            'title' => $product->title . ($variation->attributes ? ' - ' . implode(', ', $variation->attributes) : ''),
            'sku' => $variation->sku,
            'barcode' => $variation->barcode,
            'price' => $variation->price,
            'sale_price' => $variation->sale_price ?? $variation->price,
            'stock' => $variation->stock,
            'thumbnail' => $variation->image ? asset('storage/' . $variation->image) :
                         ($product->thumbnail ? asset('storage/' . $product->thumbnail) : null),
            'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
            'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
            'units' => $product->units->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'short_name' => $unit->short_name,
                    'description' => $unit->description,
                    'is_default' => $unit->is_default ?? false,
                    'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                ];
            }),
            'is_variation' => true,
            'variation_attributes' => $variation->attributes
        ];
    }

    public function getCustomerPoints($customerId)
    {
        $points = CustomerPoint::where('customer_id', $customerId)->first();

        return response()->json([
            'success' => true,
            'points' => $points ? $points->points : 0
        ]);
    }

    public function savePosOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.is_variation' => 'nullable|boolean',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.is_unit_mode' => 'nullable|boolean',
            'items.*.unit_name' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,transfer',
            'customer_id' => 'nullable|exists:customers,id',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'redeem_points' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $subtotal = 0;
                $orderItems = [];
                $orderId = 'POS-' . now()->format('Ymd-His') . '-' . Str::random(4);

                // Fetch default location
                $defaultLocation = StockLocation::where('is_default', true)
                    ->orWhere('code', 'MAIN')
                    ->firstOrFail();

                foreach ($request->items as $item) {
                    $isVariation = isset($item['is_variation']) ? (bool) $item['is_variation'] : false;

                    if ($isVariation && isset($item['variation_id'])) {
                        // Handle product variation
                        $variation = ProductVariation::with('product.units')->findOrFail($item['variation_id']);
                        $product = $variation->product;
                        $selectedUnit = Unit::findOrFail($item['unit_id']);
                        $price = $variation->sale_price ?? $variation->price;
                        $stock = $variation->stock;
                        $sku = $variation->sku;
                        $barcode = $variation->barcode;
                        $title = $product->title . ($variation->attributes ? ' - ' . implode(', ', $variation->attributes) : '');
                    } else {
                        // Handle regular product
                        $product = Product::with('units')->findOrFail($item['product_id']);
                        $selectedUnit = Unit::findOrFail($item['unit_id']);
                        $price = $product->sale_price ?? $product->price;
                        $stock = $product->current_stock;
                        $sku = $product->sku;
                        $barcode = $product->barcode;
                        $title = $product->title;
                        $variation = null;
                    }

                    // Convert quantity to float
                    $quantity = (float) $item['qty'];

                    // Check if this is unit mode (weight-based) or quantity mode (pieces)
                    $isUnitMode = isset($item['is_unit_mode']) ? (bool) $item['is_unit_mode'] : false;

                    // Calculate pieces to deduct from stock
                    $piecesToDeduct = $quantity;

                    if (!$isUnitMode) {
                        // Quantity mode: multiply by quantity_per_unit if set
                        $productUnitPivot = $product->units->where('id', $selectedUnit->id)->first();
                        if ($productUnitPivot && isset($productUnitPivot->pivot->quantity_per_unit) && $productUnitPivot->pivot->quantity_per_unit > 0) {
                            $piecesToDeduct = $quantity * $productUnitPivot->pivot->quantity_per_unit;
                        }
                    } else {
                        // Unit mode: For weight-based products, stock is in base unit (pieces)
                        $piecesToDeduct = $quantity;
                    }

                    // Check stock availability
                    if ($stock < $piecesToDeduct) {
                        throw new \Exception("Insufficient stock for {$title}. Available: {$stock}, Requested: {$piecesToDeduct}");
                    }

                    // Calculate price
                    $salePrice = (float) $item['sale_price'];
                    $discountedPrice = $salePrice;
                    $perItemDiscountAmount = 0;

                    if (isset($item['discount_value']) && $item['discount_value'] > 0) {
                        if ($item['discount_type'] === 'percent') {
                            $perItemDiscountAmount = ($salePrice * $item['discount_value']) / 100;
                        } else {
                            $perItemDiscountAmount = $item['discount_value'];
                        }
                        $discountedPrice = max(0, $salePrice - $perItemDiscountAmount);
                    }

                    // Calculate line total - for unit mode, price is per unit (e.g., per kg)
                    $lineTotal = $discountedPrice * $quantity;
                    $subtotal += $lineTotal;

                    $orderItems[] = [
                        'product_id'       => $product->id,
                        'variation_id'     => $isVariation ? $item['variation_id'] : null,
                        'quantity'         => $quantity,
                        'unit_id'          => $item['unit_id'],
                        'unit_price'       => $salePrice,
                        'discount_type'    => $item['discount_type'] ?? null,
                        'discount_value'   => $item['discount_value'] ?? 0,
                        'discounted_price' => $discountedPrice,
                        'total_price'      => $lineTotal,
                        'title'            => $title,
                        'sku'              => $sku,
                        'barcode'          => $barcode,
                        'unit_name'        => $item['unit_name'] ?? $selectedUnit->name ?? null,
                        'is_unit_mode'     => $isUnitMode,
                    ];

                    // Stock movement
                    $previous = $isVariation ? $variation->stock : $product->stock;
                    $new = max(0, $previous - $piecesToDeduct);

                    Stock::create([
                        'product_id'        => $product->id,
                        'variation_id'      => $isVariation ? $variation->id : null,
                        'stock_location_id' => $defaultLocation->id,
                        'user_id'           => auth()->id(),
                        'type'              => Stock::TYPE_OUT,
                        'quantity'          => $piecesToDeduct,
                        'previous_quantity' => $previous,
                        'new_quantity'      => $new,
                        'reference_type'    => Stock::REFERENCE_SALE,
                        'reference_number'  => $orderId,
                        'notes'             => "POS Sale - " . ($isUnitMode ? "Unit Mode" : "Quantity Mode") .
                                              ($isVariation ? " - Variation" : ""),
                        'transaction_date'  => now(),
                    ]);

                    // Update stock
                    if ($isVariation) {
                        $variation->decrement('stock', $piecesToDeduct);
                    } else {
                        $product->decrement('stock', $piecesToDeduct);
                    }
                }

                // Order-level discount
                $orderDiscountAmount = 0;
                $orderDiscountType = null;
                $orderDiscountValue = 0;

                if ($request->filled('discount_amount') && $request->discount_amount > 0) {
                    // Use provided discount amount
                    $orderDiscountAmount = (float) $request->discount_amount;
                    $orderDiscountType = $request->discount_type;
                    $orderDiscountValue = $request->discount_value;
                } elseif ($request->filled('discount_value') && $request->filled('discount_type')) {
                    // Calculate discount from value
                    $orderDiscountType = $request->discount_type;
                    $orderDiscountValue = $request->discount_value;
                    if ($orderDiscountType === 'percent') {
                        $orderDiscountAmount = ($subtotal * $orderDiscountValue) / 100;
                    } else {
                        $orderDiscountAmount = $orderDiscountValue;
                    }
                }

                // Ensure discount doesn't exceed subtotal
                $orderDiscountAmount = min($orderDiscountAmount, $subtotal);

                // Points redemption discount
                $pointsRedeemedDiscount = 0;
                $pointsRedeemed = 0;
                if ($request->filled('redeem_points') && $request->customer_id) {
                    $redeemRate = config('loyalty.redeem_rate', 100);
                    $pointsRedeemed = $request->redeem_points;
                    if ($pointsRedeemed > 0) {
                        $pointsRedeemedDiscount = $pointsRedeemed / $redeemRate;
                        $orderDiscountAmount += $pointsRedeemedDiscount;
                    }
                }

                $grandTotal = max(0, $subtotal - $orderDiscountAmount);

                $orderData = [
                    'id'              => $orderId,
                    'customer_id'     => $request->customer_id ?: null,
                    'user_id'         => auth()->id(),
                    'subtotal'        => $subtotal,
                    'discount_amount' => $orderDiscountAmount,
                    'total_amount'    => $grandTotal,
                    'payment_method'  => $request->payment_method,
                    'order_date'      => now(),
                    'notes'           => 'POS Sale',
                    'status'          => 'completed',
                ];

                if (\Schema::hasColumn('orders', 'payment_status')) {
                    $orderData['payment_status'] = 'paid';
                }

                if (\Schema::hasColumn('orders', 'discount_type')) {
                    $orderData['discount_type'] = $orderDiscountType;
                    $orderData['discount_value'] = $orderDiscountValue;
                }

                $order = Order::create($orderData);
                $order->items()->createMany($orderItems);

                // Loyalty Points Earned
                if ($order->customer_id) {
                    $earnRate = config('loyalty.earn_rate', 1);
                    $pointsEarned = floor($order->total_amount * $earnRate);

                    if ($pointsEarned > 0) {
                        $customerPoints = CustomerPoint::firstOrCreate(
                            ['customer_id' => $order->customer_id],
                            ['points' => 0]
                        );

                        $customerPoints->increment('points', $pointsEarned);

                        CustomerPointTransaction::create([
                            'customer_id' => $order->customer_id,
                            'order_id' => $order->id,
                            'points_earned' => $pointsEarned,
                            'amount_spent' => $order->total_amount,
                            'description' => "Earned from order #{$order->id}",
                            'created_by' => auth()->id(),
                        ]);
                    }

                    // Points Redeemed
                    if ($pointsRedeemed > 0) {
                        $customerPoints = CustomerPoint::firstOrCreate(
                            ['customer_id' => $order->customer_id],
                            ['points' => 0]
                        );

                        $customerPoints->decrement('points', $pointsRedeemed);

                        CustomerPointTransaction::create([
                            'customer_id' => $order->customer_id,
                            'order_id' => $order->id,
                            'points_redeemed' => $pointsRedeemed,
                            'discount_applied' => $pointsRedeemedDiscount,
                            'description' => "Redeemed {$pointsRedeemed} points for ₦{$pointsRedeemedDiscount} discount",
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
                $order->calculateAndSaveCommission();

                return response()->json([
                    'success'  => true,
                    'message'  => 'Order completed successfully!',
                    'order_id' => $orderId,
                    'total'    => $grandTotal,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProductUnits($productId)
    {
        try {
            $product = Product::with('units')->findOrFail($productId);

            $units = $product->units->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'short_name' => $unit->short_name,
                    'description' => $unit->description,
                    'is_default' => $unit->is_default ?? false,
                    'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                ];
            });

            // If product has no units, return default units
            if ($units->isEmpty()) {
                $units = collect([
                    [
                        'id' => 1,
                        'name' => 'Kilogram',
                        'short_name' => 'kg',
                        'description' => 'Kilogram',
                        'is_default' => true,
                        'quantity_per_unit' => 1
                    ],
                    [
                        'id' => 2,
                        'name' => 'Gram',
                        'short_name' => 'g',
                        'description' => 'Gram - 1000g = 1kg',
                        'is_default' => false,
                        'quantity_per_unit' => 0.001
                    ],
                    [
                        'id' => 3,
                        'name' => 'Pound',
                        'short_name' => 'lb',
                        'description' => 'Pound - 1lb ≈ 0.453kg',
                        'is_default' => false,
                        'quantity_per_unit' => 0.453592
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'units' => $units
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateReceipt($orderId)
    {
        $order = Order::where('id', $orderId)
            ->with(['customer', 'items.product', 'items.unit'])
            ->firstOrFail();

        if ($order->user_id != auth()->id() && !auth()->user()->can('Manage pos')) {
            abort(403);
        }

        return view('pos.receipt', compact('order'));
    }

    public function getTodaySales()
    {
        $today = now()->format('Y-m-d');

        $sales = Order::whereDate('order_date', $today)
            ->when(\Schema::hasColumn('orders', 'status'), function($query) {
                return $query->where('status', 'completed');
            })
            ->select([
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('AVG(total_amount) as average_sale'),
                'payment_method'
            ])
            ->groupBy('payment_method')
            ->get();

        $totalSales = $sales->sum('total_sales');
        $totalOrders = $sales->sum('total_orders');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'total_sales' => $totalSales,
                'average_sale' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                'by_payment_method' => $sales,
            ]
        ]);
    }

    public function getProductStock($productId)
    {
        $product = Product::findOrFail($productId);

        return response()->json([
            'success' => true,
            'stock' => $product->current_stock,
            'product_name' => $product->title
        ]);
    }

    public function voidOrder($orderId)
    {
        try {
            DB::transaction(function () use ($orderId) {
                $order = Order::with('items.product.units')->where('id', $orderId)->firstOrFail();

                $defaultLocation = StockLocation::where('is_default', true)
                    ->orWhere('code', 'MAIN')
                    ->firstOrFail();

                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (!$product) continue;

                    // Check if this item has a variation
                    $isVariation = !is_null($item->variation_id);
                    $variation = $isVariation ? ProductVariation::find($item->variation_id) : null;

                    // Calculate pieces to restore
                    $piecesToRestore = $item->quantity;

                    // Check if this was a unit mode purchase
                    $isUnitMode = isset($item->is_unit_mode) ? (bool) $item->is_unit_mode : false;

                    if (!$isUnitMode && $item->unit_id) {
                        // Quantity mode: multiply by quantity_per_unit
                        $unitPivot = $product->units->where('id', $item->unit_id)->first();
                        if ($unitPivot && isset($unitPivot->pivot->quantity_per_unit) && $unitPivot->pivot->quantity_per_unit > 0) {
                            $piecesToRestore = $item->quantity * $unitPivot->pivot->quantity_per_unit;
                        }
                    }

                    $previousQuantity = $isVariation ? $variation->stock : $product->stock;
                    $newQuantity = $previousQuantity + $piecesToRestore;

                    Stock::create([
                        'product_id'        => $product->id,
                        'variation_id'      => $isVariation ? $variation->id : null,
                        'stock_location_id' => $defaultLocation->id,
                        'user_id'           => auth()->id(),
                        'type'              => Stock::TYPE_RETURN,
                        'quantity'          => $piecesToRestore,
                        'previous_quantity' => $previousQuantity,
                        'new_quantity'      => $newQuantity,
                        'reference_type'    => Stock::REFERENCE_RETURN,
                        'reference_number'  => $orderId,
                        'notes'             => "Order #{$orderId} voided - stock restored" .
                                              ($isVariation ? " - Variation" : ""),
                        'transaction_date'  => now(),
                    ]);

                    if ($isVariation && $variation) {
                        $variation->increment('stock', $piecesToRestore);
                    } else {
                        $product->increment('stock', $piecesToRestore);
                    }
                }

                if (\Schema::hasColumn('orders', 'status')) {
                    $order->update(['status' => 'cancelled']);
                } else {
                    $order->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Order voided successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error voiding order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProductByBarcode($barcode)
    {
        // First try products
        $product = Product::with('units')
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->first();

        if ($product) {
            $primaryUnit = $product->units->first();
            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price ?? $product->price,
                    'stock' => $product->current_stock,
                    'thumbnail' => $product->thumbnail ? asset('storage/' . $product->thumbnail) : null,
                    'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
                    'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
                    'units' => $product->units->map(function($unit) {
                        return [
                            'id' => $unit->id,
                            'name' => $unit->name,
                            'short_name' => $unit->short_name,
                            'description' => $unit->description,
                            'is_default' => $unit->is_default ?? false,
                            'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                        ];
                    }),
                    'is_variation' => false,
                    'variation_id' => null,
                    'variation_attributes' => null
                ]
            ]);
        }

        // Then try variations
        $variation = ProductVariation::where('barcode', $barcode)
            ->with(['product.units', 'product'])
            ->first();

        if ($variation && $variation->product->is_active) {
            $product = $variation->product;
            $primaryUnit = $product->units->first();

            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'variation_id' => $variation->id,
                    'title' => $product->title . ($variation->attributes ? ' - ' . implode(', ', $variation->attributes) : ''),
                    'sku' => $variation->sku,
                    'barcode' => $variation->barcode,
                    'price' => $variation->price,
                    'sale_price' => $variation->sale_price ?? $variation->price,
                    'stock' => $variation->stock,
                    'thumbnail' => $variation->image ? asset('storage/' . $variation->image) :
                                 ($product->thumbnail ? asset('storage/' . $product->thumbnail) : null),
                    'primary_unit' => $primaryUnit ? $primaryUnit->name : 'Unit',
                    'primary_unit_id' => $primaryUnit ? $primaryUnit->id : null,
                    'units' => $product->units->map(function($unit) {
                        return [
                            'id' => $unit->id,
                            'name' => $unit->name,
                            'short_name' => $unit->short_name,
                            'description' => $unit->description,
                            'is_default' => $unit->is_default ?? false,
                            'quantity_per_unit' => $unit->pivot->quantity_per_unit ?? 1,
                        ];
                    }),
                    'is_variation' => true,
                    'variation_attributes' => $variation->attributes
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Product not found'
        ], 404);
    }
}
