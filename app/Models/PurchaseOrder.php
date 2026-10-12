<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasUid;

    protected $fillable = ['uid','number','branch_id','supplier_id','status','version','expected_date','total','notes','created_by_name','approved_by_name','approved_at','sent_at','closed_at','cancel_reason'];

    protected $casts = ['expected_date' => 'date', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'closed_at' => 'datetime', 'total' => 'float'];

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Waiting for approval', 'approved' => 'Approved', 'sent' => 'Sent to supplier',
        'partial' => 'Partly received', 'received' => 'Received', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
    public const RANK = ['draft' => 1, 'submitted' => 2, 'approved' => 3, 'sent' => 4, 'partial' => 5, 'received' => 6, 'closed' => 7, 'cancelled' => 9];

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function receipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
