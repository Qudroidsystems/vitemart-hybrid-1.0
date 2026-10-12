<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use HasUid, BelongsToBranch;

    protected $fillable = ['uid','number','branch_id','stock_location_id','supplier_id','purchase_order_id','supplier_invoice','invoice_date','total','status','notes','received_by'];

    protected $casts = ['invoice_date' => 'date', 'total' => 'float'];

    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
