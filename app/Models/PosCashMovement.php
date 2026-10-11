<?php

namespace App\Models;

use App\Traits\HasUid;

use Illuminate\Database\Eloquent\Model;

class PosCashMovement extends Model
{
    use HasUid;
    protected $fillable = ['pos_shift_id', 'type', 'amount', 'reason', 'user_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function shift()
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }
}
