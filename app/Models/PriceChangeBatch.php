<?php

namespace App\Models;

use App\Traits\HasUid;

use Illuminate\Database\Eloquent\Model;

class PriceChangeBatch extends Model
{
    use HasUid;
    protected $fillable = ['batch_id', 'description', 'criteria', 'items_count', 'user_id', 'reverted_at', 'reverted_by'];

    protected $casts = [
        'criteria' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revertedBy()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function histories()
    {
        return $this->hasMany(PriceHistory::class, 'batch_id', 'batch_id');
    }
}
