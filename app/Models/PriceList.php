<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    use HasUid, Auditable;

    protected $fillable = ['uid', 'code', 'name', 'branch_id', 'priority', 'is_active', 'starts_at', 'ends_at', 'owner', 'notes'];

    protected $casts = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'priority' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function scopeLiveFor($q, ?int $branchId)
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('branch_id')->when($branchId, fn ($x) => $x->orWhere('branch_id', $branchId)))
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /** The list a branch's own approved prices go into (created on demand). */
    public static function forBranch(Branch $branch): self
    {
        return static::firstOrCreate(
            ['code' => 'BR-' . $branch->code],
            ['name' => $branch->name . ' prices', 'branch_id' => $branch->id, 'priority' => 50, 'is_active' => true, 'owner' => 'central']
        );
    }
}
