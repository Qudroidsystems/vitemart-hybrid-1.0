<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

/** Calls made by enrolled branch servers (HQ side). */
class BranchServerController extends Controller
{
    public function heartbeat(Request $request)
    {
        $inst = $request->attributes->get('installation');
        $data = $request->validate([
            'app_version' => 'nullable|string|max:20',
            'disk_free_mb' => 'nullable|integer|min:0',
            'pending_events' => 'nullable|integer|min:0',
            'dead_letters' => 'nullable|integer|min:0',
            'last_backup_at' => 'nullable|date',
        ]);
        $inst->forceFill(array_filter($data, fn ($v) => $v !== null))->saveQuietly();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'latest_release' => config('vitemart.version'),
            'branch' => $inst->branch->toSync(),
        ]);
    }

    /** Directory of all branches (used for transfers between branches). */
    public function branches()
    {
        return response()->json(['branches' => Branch::orderBy('number')->get()->map->toSync()]);
    }
}
