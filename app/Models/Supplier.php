<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUid, Auditable;

    protected $fillable = ['uid','code','name','contact_person','phone','email','address','tax_id','payment_terms_days','lead_time_days','bank_details','is_active','notes'];

    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
