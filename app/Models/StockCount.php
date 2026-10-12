<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    use HasUid, BelongsToBranch;

    protected $fillable = ['uid','number','branch_id','stock_location_id','title','status','blind','recount_threshold','stock_adjustment_id','created_by','submitted_by','submitted_at','notes'];

    protected $casts = ['blind' => 'boolean', 'submitted_at' => 'datetime'];

    public function lines()
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }
}
