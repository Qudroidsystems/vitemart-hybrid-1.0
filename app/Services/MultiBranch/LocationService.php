<?php

namespace App\Services\MultiBranch;

use App\Models\Branch;
use App\Models\StockLocation;
use Illuminate\Support\Facades\Schema;

class LocationService
{
    public function ensureFor(Branch $branch): void
    {
        if (!Schema::hasColumn('stock_locations', 'type')) {
            return;
        }
        $store = StockLocation::where('branch_id', $branch->id)->where('type', 'store')->first()
            ?? StockLocation::where('branch_id', $branch->id)->whereNull('type')->orderByDesc('is_default')->first();
        if (!$store) {
            $store = StockLocation::create([
                'name' => $branch->name . ($branch->isWarehouse() ? '' : ' – Shop floor'),
                'code' => $branch->code . '-STORE',
                'branch_id' => $branch->id, 'type' => 'store', 'is_active' => true,
                'is_default' => $branch->id === Install::branchId(),
            ]);
        } elseif (!$store->type) {
            $store->update(['type' => 'store']);
        }
        foreach (['transit' => 'In transit', 'quarantine' => 'Quarantine (damaged / expired)'] as $type => $label) {
            if (!StockLocation::where('branch_id', $branch->id)->where('type', $type)->exists()) {
                StockLocation::create([
                    'name' => "{$branch->code} – {$label}", 'code' => $branch->code . '-' . strtoupper(substr($type, 0, 4)),
                    'branch_id' => $branch->id, 'type' => $type, 'is_active' => true, 'is_default' => false,
                ]);
            }
        }
    }

    public function storeFor(int $branchId): StockLocation
    {
        return StockLocation::where('branch_id', $branchId)->where('type', 'store')->orderByDesc('is_default')->first()
            ?? StockLocation::where('branch_id', $branchId)->orderByDesc('is_default')->firstOrFail();
    }

    public function typed(int $branchId, string $type): StockLocation
    {
        $loc = StockLocation::where('branch_id', $branchId)->where('type', $type)->first();
        if (!$loc) {
            $this->ensureFor(Branch::findOrFail($branchId));
            $loc = StockLocation::where('branch_id', $branchId)->where('type', $type)->firstOrFail();
        }
        return $loc;
    }
}
