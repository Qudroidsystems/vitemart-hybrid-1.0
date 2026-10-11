<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    use HasUid, BelongsToBranch;
    protected $fillable = [
        'user_id', 'register', 'opened_at', 'opening_float', 'closed_at', 'expected_cash', 'counted_cash',
        'variance', 'cash_in', 'cash_out', 'summary', 'status', 'notes', 'closed_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'summary' => 'array',
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'pos_shift_id');
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class, 'pos_shift_id');
    }

    public function cashMovements()
    {
        return $this->hasMany(PosCashMovement::class);
    }

    public static function currentFor(?int $userId): ?self
    {
        if (!$userId) {
            return null;
        }
        return static::where('user_id', $userId)->where('status', 'open')->latest('opened_at')->first();
    }

    /** Totals for the X/Z report. */
    public function computeSummary(): array
    {
        $orders = $this->orders()->where('status', '!=', 'cancelled')->get(['id', 'total_amount', 'discount_amount', 'cost_total']);
        $voided = $this->orders()->where('status', 'cancelled')->count();
        $byMethod = $this->payments()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_payments.method, SUM(order_payments.amount) as total, COUNT(*) as cnt')
            ->groupBy('order_payments.method')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->method => ['total' => (float) $r->total, 'count' => (int) $r->cnt]])
            ->all();

        $cashSales = $byMethod['cash']['total'] ?? 0;
        $cashIn = (float) $this->cashMovements()->where('type', 'in')->sum('amount');
        $cashOut = (float) $this->cashMovements()->where('type', 'out')->sum('amount');
        $refundsCash = (float) \App\Models\Refund::whereIn('order_id', $this->orders()->pluck('id'))
            ->where('status', 'processed')->sum('amount');

        return [
            'orders_count' => $orders->count(),
            'voided_count' => $voided,
            'gross_sales' => round((float) $orders->sum('total_amount'), 2),
            'discounts' => round((float) $orders->sum('discount_amount'), 2),
            'by_method' => $byMethod,
            'cash_sales' => round($cashSales, 2),
            'cash_in' => round($cashIn, 2),
            'cash_out' => round($cashOut, 2),
            'refunds' => round($refundsCash, 2),
            'expected_cash' => round((float) $this->opening_float + $cashSales + $cashIn - $cashOut - $refundsCash, 2),
        ];
    }
}
