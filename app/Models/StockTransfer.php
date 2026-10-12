<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasUid;

    protected $fillable = ['uid','number','from_branch_id','to_branch_id','status','version','has_discrepancy','notes','carrier','requested_by_name','requested_at','approved_by_name','approved_at','dispatched_by_name','dispatched_at','received_by_name','received_at','closed_by_name','closed_at','cancel_reason'];

    protected $casts = ['has_discrepancy' => 'boolean', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'closed_at' => 'datetime'];

    public const STATUSES = ['requested' => 'Requested', 'approved' => 'Approved', 'dispatched' => 'On the way', 'received' => 'Received',
        'closed' => 'Closed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'];
    public const RANK = ['requested' => 1, 'approved' => 2, 'dispatched' => 3, 'received' => 4, 'closed' => 5, 'cancelled' => 9, 'rejected' => 9];

    public function lines()
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }
}
