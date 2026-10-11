<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Sync\Feed;
use App\Services\Sync\Inbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Head office: the two sync endpoints a branch server calls (signed). */
class SyncApiController extends Controller
{
    public function push(Request $request, Inbox $inbox)
    {
        $data = $request->validate([
            'events' => 'required|array|max:1000',
            'events.*.event_uid' => 'required|string|size:26',
            'events.*.entity' => 'required|string|max:40',
            'events.*.entity_uid' => 'nullable|string|max:64',
            'events.*.op' => 'nullable|in:upsert,delete',
            'events.*.payload' => 'present|array',
        ]);
        $results = $inbox->receive($request->attributes->get('branch'), $request->attributes->get('installation'), $data['events']);
        return response()->json(['results' => $results]);
    }

    public function pull(Request $request)
    {
        $data = $request->validate(['cursor' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|min:1|max:1000']);
        $branch = $request->attributes->get('branch');
        $inst = $request->attributes->get('installation');
        $out = Feed::read($branch, (int) ($data['cursor'] ?? 0), (int) ($data['limit'] ?? 500));

        $cmds = DB::table('sync_commands')->where('branch_id', $branch->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderBy('id')->limit(10)->get();
        if ($cmds->isNotEmpty()) {
            DB::table('sync_commands')->whereIn('id', $cmds->pluck('id'))->whereNull('delivered_at')->update(['delivered_at' => now()]);
        }
        $out['commands'] = $cmds->map(fn ($c) => ['uid' => $c->uid, 'command' => $c->command, 'payload' => json_decode($c->payload ?? 'null', true),
            'min_version' => $c->min_version])->all();

        $inst?->forceFill(['last_pull_at' => now()])->saveQuietly();
        // How far behind is this branch?  (shown on the sync dashboard)
        DB::table('sync_state')->updateOrInsert(['key' => 'branch_cursor_' . $branch->id],
            ['value' => json_encode(['cursor' => $out['cursor'], 'head' => $out['head'], 'at' => now()->toIso8601String()]), 'updated_at' => now()]);
        return response()->json($out);
    }

    public function commandResult(Request $request, string $uid)
    {
        $data = $request->validate(['status' => 'required|in:done,failed,refused', 'result' => 'nullable|string|max:20000']);
        $n = DB::table('sync_commands')->where('uid', $uid)->where('branch_id', $request->attributes->get('branch')->id)
            ->update(['status' => $data['status'], 'result' => $data['result'] ?? null, 'finished_at' => now(), 'updated_at' => now()]);
        return response()->json(['updated' => $n]);
    }
}
