<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Refund;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Models\User;

/**
 * What travels, in which direction, and how foreign keys are translated.
 *
 * Integer ids never leave a server.  A foreign key `customer_id` travels as `customer_id__uid`
 * and is turned back into the receiving server's own id.  `keep` columns travel as they are
 * (receipt numbers, which are already unique across branches).
 */
class Registry
{
    /**
     * Branch → head office, in the order dependencies are needed.
     * natural: columns used to recognise "the same thing" created on two servers.
     */
    public const UP = [
        'stock_location' => ['model' => StockLocation::class, 'table' => 'stock_locations', 'refs' => []],
        'cash_register' => ['model' => CashRegister::class, 'table' => 'cash_registers', 'refs' => ['stock_location_id' => StockLocation::class],
            'natural' => [['branch_id', 'code']]],
        'customer' => ['model' => Customer::class, 'table' => 'customers', 'refs' => ['created_by' => User::class, 'updated_by' => User::class],
            'natural' => ['phone_number', 'email', 'loyalty_card_number'], 'skip' => ['origin_branch_id'], 'branch_on_insert' => true],
        'customer_point_tx' => ['model' => CustomerPointTransaction::class, 'table' => 'customer_point_transactions',
            'refs' => ['customer_id' => Customer::class, 'created_by' => User::class], 'keep' => ['order_id']],
        'pos_shift' => ['model' => PosShift::class, 'table' => 'pos_shifts',
            'refs' => ['user_id' => User::class, 'closed_by' => User::class, 'cash_register_id' => CashRegister::class]],
        'pos_cash_movement' => ['model' => PosCashMovement::class, 'table' => 'pos_cash_movements',
            'refs' => ['pos_shift_id' => PosShift::class, 'user_id' => User::class]],
        'order' => ['model' => Order::class, 'table' => 'orders', 'keep_id' => true,
            'refs' => ['customer_id' => Customer::class, 'user_id' => User::class, 'pos_shift_id' => PosShift::class,
                'cash_register_id' => CashRegister::class, 'voided_by' => User::class],
            'null' => ['shipping_address_id', 'billing_address_id']],
        'order_item' => ['model' => OrderItem::class, 'table' => 'order_items', 'keep' => ['order_id'],
            'refs' => ['product_id' => Product::class, 'product_unit_id' => ProductUnit::class, 'unit_id' => Unit::class,
                'variation_id' => ProductVariation::class]],
        'order_payment' => ['model' => OrderPayment::class, 'table' => 'order_payments', 'keep' => ['order_id'],
            'refs' => ['pos_shift_id' => PosShift::class, 'user_id' => User::class, 'confirmed_by' => User::class,
                'cash_register_id' => CashRegister::class]],
        'refund' => ['model' => Refund::class, 'table' => 'refunds', 'keep' => ['order_id'], 'refs' => ['user_id' => User::class]],
        'stock_adjustment' => ['model' => StockAdjustment::class, 'table' => 'stock_adjustments',
            'refs' => ['stock_location_id' => StockLocation::class, 'created_by' => User::class, 'approved_by' => User::class,
                'rejected_by' => User::class]],
        'stock_adjustment_item' => ['model' => StockAdjustmentItem::class, 'table' => 'stock_adjustment_items',
            'refs' => ['stock_adjustment_id' => StockAdjustment::class, 'product_id' => Product::class,
                'product_variation_id' => ProductVariation::class, 'product_unit_id' => ProductUnit::class]],
        'stock' => ['model' => Stock::class, 'table' => 'stocks',
            'refs' => ['product_id' => Product::class, 'product_variation_id' => ProductVariation::class,
                'stock_location_id' => StockLocation::class, 'destination_location_id' => StockLocation::class, 'user_id' => User::class]],
        'audit_log' => ['model' => AuditLog::class, 'table' => 'audit_logs', 'refs' => ['user_id' => User::class], 'keep' => ['auditable_id']],
        // special handlers
        'uid_alias' => ['model' => 'uid_alias', 'table' => 'sync_uid_aliases', 'special' => true],
        'product' => ['model' => Product::class, 'table' => 'products', 'special' => true],
        'user' => ['model' => User::class, 'table' => 'users', 'special' => true],
    ];

    /** Head office → branches (the feed). */
    public const DOWN = [
        'branch' => ['model' => Branch::class, 'table' => 'branches', 'special' => true],
        'category' => ['model' => Category::class, 'table' => 'categories', 'refs' => ['parent_id' => Category::class], 'natural' => ['name'],
            'skip' => ['origin_branch_id']],
        'brand' => ['model' => Brand::class, 'table' => 'brands', 'refs' => [], 'natural' => ['name'], 'skip' => ['origin_branch_id']],
        'unit' => ['model' => Unit::class, 'table' => 'units', 'refs' => [], 'natural' => ['name'], 'skip' => []],
        'product' => ['model' => Product::class, 'table' => 'products', 'special' => true],
        'price_list' => ['model' => PriceList::class, 'table' => 'price_lists', 'refs' => ['branch_id' => Branch::class], 'natural' => ['code']],
        'price_list_item' => ['model' => PriceListItem::class, 'table' => 'price_list_items',
            'refs' => ['price_list_id' => PriceList::class, 'product_id' => Product::class, 'product_variation_id' => ProductVariation::class,
                'product_unit_id' => ProductUnit::class, 'updated_by' => User::class],
            'natural' => [['price_list_id', 'item_key']]],
        'policies' => ['special' => true],
        'setting' => ['special' => true],
        'role' => ['special' => true],
        'user' => ['model' => User::class, 'table' => 'users', 'special' => true],
        'customer' => ['model' => Customer::class, 'table' => 'customers',
            'refs' => ['created_by' => User::class, 'updated_by' => User::class, 'branch_id' => Branch::class],
            'natural' => ['phone_number', 'email', 'loyalty_card_number'], 'skip' => ['origin_branch_id']],
    ];

    /** Settings head office decides for every branch. */
    public static function centralSettings(): array
    {
        return config('vitemart.sync.central_settings', []);
    }

    /** Model class → entity name for the up direction. */
    public static function upEntityFor(string $class): ?string
    {
        foreach (self::UP as $name => $def) {
            if ($def['model'] === $class) {
                return $name;
            }
        }
        return null;
    }
}
