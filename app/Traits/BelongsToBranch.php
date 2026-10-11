<?php

namespace App\Traits;

use App\Models\Branch;
use App\Services\MultiBranch\Install;

/**
 * Stamps the current installation's branch on new records and offers a scope
 * for "records of these branches".
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = Install::branchId();
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranches($query, $branchIds)
    {
        $branchIds = array_filter((array) $branchIds);
        return $branchIds ? $query->whereIn($this->getTable() . '.branch_id', $branchIds) : $query;
    }
}
