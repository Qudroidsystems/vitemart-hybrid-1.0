<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class SupplierReturn extends Model
{
    use HasUid, BelongsToBranch;

    protected $fillable = ['uid','number','branch_id','stock_location_id','supplier_id','goods_receipt_id','reason','total','credit_note','status','created_by'];

    protected $casts = ['total' => 'float'];

    public function lines()
    {
        return $this->hasMany(SupplierReturnLine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }
}
