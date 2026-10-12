<?php

namespace App\Http\Controllers\MultiBranch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\MultiBranch\Install;
use App\Services\Sync\Feed;
use App\Services\Sync\Outbox;
use App\Services\Sync\SyncRunner;
use App\Services\Sync\SyncState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Sync status: what a branch still has to send / receive, and (at head office) how every branch is doing. */
class SyncController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View sync status|Manage sync', ['only' => ['index']]);
        $this->middleware('permission:Manage sync', ['except' => ['index', 'distribution']]);
        $this->middleware('permission:Distribute products', ['only' => ['distribution']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Sync';
        $mode = Install::mode();

        if ($mode === 'central') {
            $head = (int) DB::table('sync_feed')->max('id');
            $branches = Branch::with('activeInstallation')->where('id', '!=', Install::branchId())->orderBy('number')->get()
                ->map(function ($b) use ($head) {
                    $state = json_decode((string) DB::table('sync_state')->where('key', 'branch_cursor_' . $b->id)->value('value'), true) ?: [];
                    $b->sync_cursor = $state['cursor'] ?? null;
                    $b->sync_behind = isset($state['cursor']) ? max(0, $head - (int) $state['cursor']) : null;
                    $b->inbox_failed = DB::table('sync_inbox')->where('branch_id', $b->id)->where('status', 'failed')->count();
                    $b->inbox_today = DB::table('sync_inbox')->where('branch_id', $b->id)->where('created_at', '>=', today())->count();
                    return $b;
                });
            $failures = DB::table('sync_inbox')->join('branches', 'branches.id', '=', 'sync_inbox.branch_id')
                ->where('sync_inbox.status', 'failed')->orderByDesc('sync_inbox.updated_at')->limit(50)
                ->get(['sync_inbox.*', 'branches.code']);
            $commands = DB::table('sync_commands')->join('branches', 'branches.id', '=', 'sync_commands.branch_id')
                ->orderByDesc('sync_commands.id')->limit(30)->get(['sync_commands.*', 'branches.code']);
            $aliases = DB::table('sync_uid_aliases')->leftJoin('branches', 'branches.id', '=', 'sync_uid_aliases.branch_id')
                ->orderByDesc('sync_uid_aliases.id')->limit(30)->get(['sync_uid_aliases.*', 'branches.code']);
            $feed = ['head' => $head, 'rows' => DB::table('sync_feed')->count(), 'dirty' => DB::table('sync_dirty')->count(),
                'by' => DB::table('sync_feed')->selectRaw('entity, COUNT(*) c')->groupBy('entity')->pluck('c', 'entity')];
            return view('multibranch.sync-central', compact('pagetitle', 'branches', 'failures', 'commands', 'aliases', 'feed') + ['cmdList' => SyncRunner::COMMANDS]);
        }

        if ($mode === 'branch') {
            $state = SyncState::all();
            $counts = Outbox::counts();
            $byEntity = DB::table('sync_outbox')->where('status', 'pending')->selectRaw('entity, COUNT(*) c')->groupBy('entity')->pluck('c', 'entity');
            $problems = DB::table('sync_outbox')->where(fn ($q) => $q->where('status', 'dead')->orWhere(fn ($w) => $w->where('status', 'pending')->where('attempts', '>', 0)))
                ->orderBy('id')->limit(100)->get(['id', 'entity', 'entity_uid', 'status', 'attempts', 'last_error', 'next_attempt_at', 'created_at']);
            $oldest = DB::table('sync_outbox')->where('status', 'pending')->min('created_at');
            $runs = DB::table('sync_command_runs')->orderByDesc('created_at')->limit(20)->get();
            $install = Install::current();
            return view('multibranch.sync-branch', compact('pagetitle', 'state', 'counts', 'byEntity', 'problems', 'oldest', 'runs', 'install'));
        }

        return view('multibranch.sync-branch', ['pagetitle' => $pagetitle, 'standalone' => true, 'state' => [], 'counts' => [], 'byEntity' => [],
            'problems' => collect(), 'oldest' => null, 'runs' => collect(), 'install' => Install::current()]);
    }

    /** Branch: sync now. */
    public function run(SyncRunner $runner)
    {
        abort_unless(Install::isBranch(), 404);
        try {
            $r = $runner->run(true);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Head office not reachable: ' . $e->getMessage()], 502);
        }
        if (!empty($r['busy'])) {
            return response()->json(['success' => true, 'message' => 'A sync is already running – try again in a moment.']);
        }
        return response()->json(['success' => true, 'message' => sprintf('Sent %d, received %d change(s).', $r['sent'] ?? 0, $r['applied'] ?? 0), 'result' => $r]);
    }

    /** Branch: send failed events again (new event ids, so head office doesn't treat them as duplicates). */
    public function retry(Request $request)
    {
        abort_unless(Install::isBranch(), 404);
        $q = DB::table('sync_outbox')->whereIn('status', ['dead', 'pending'])->where('attempts', '>', 0);
        if ($request->filled('id')) {
            $q->where('id', (int) $request->id);
        }
        $n = 0;
        foreach ($q->get(['id']) as $r) {
            DB::table('sync_outbox')->where('id', $r->id)->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null,
                'event_uid' => (string) Str::ulid(), 'last_error' => null]);
            $n++;
        }
        AuditLogger::log('sync_retry', "Queued {$n} sync event(s) to send again", 'sync');
        return response()->json(['success' => true, 'message' => "{$n} event(s) will be sent again."]);
    }

    /** Branch: download the whole catalogue again. */
    public function resync()
    {
        abort_unless(Install::isBranch(), 404);
        SyncState::set('feed_cursor', 0);
        SyncState::set('feed_failures', []);
        AuditLogger::log('sync_resync', 'Catalogue will be downloaded again', 'sync');
        return response()->json(['success' => true, 'message' => 'The catalogue will be downloaded again on the next sync (within a minute).']);
    }

    /** Back up this server's database now. */
    public function backup(\App\Services\Operations\BackupService $backups)
    {
        try {
            $run = $backups->dump('manual');
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
        AuditLogger::log('backup', 'Database backed up (' . basename($run->path) . ')', 'system');
        return response()->json(['success' => true, 'message' => 'Backed up (' . number_format($run->size_bytes / 1048576, 1) . ' MB).']);
    }

    /** Head office: send a command to a branch server. */
    public function command(Request $request, Branch $branch)
    {
        abort_unless(Install::isCentral(), 404);
        $data = $request->validate([
            'command' => 'required|in:' . implode(',', array_keys(SyncRunner::COMMANDS)),
            'since' => 'nullable|date',
            'min_version' => 'nullable|string|max:20',
        ]);
        DB::table('sync_commands')->insert([
            'uid' => (string) Str::ulid(), 'branch_id' => $branch->id, 'command' => $data['command'],
            'payload' => json_encode(array_filter(['since' => $data['since'] ?? null])), 'min_version' => $data['min_version'] ?? null,
            'status' => 'pending', 'created_by' => $request->user()->id, 'expires_at' => now()->addDays(3),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogger::log('sync_command_sent', "Sent '{$data['command']}' to {$branch->code}", 'sync');
        return response()->json(['success' => true, 'message' => "Sent. {$branch->code} picks it up on its next sync (within a minute)."]);
    }

    /** Head office: rebuild the feed from scratch (repairs gaps). */
    public function snapshot()
    {
        abort_unless(Install::isCentral(), 404);
        $n = Feed::snapshot();
        Feed::build(100000);
        AuditLogger::log('sync_snapshot', "Feed rebuilt ({$n} records)", 'sync');
        return response()->json(['success' => true, 'message' => "{$n} records put back into the feed."]);
    }

    /** Head office: which branches sell a product. */
    public function distribution(Request $request, Product $product)
    {
        abort_unless(Install::isCentral(), 404);
        $data = $request->validate(['mode' => 'required|in:all,selected', 'branches' => 'array', 'branches.*' => 'integer|exists:branches,id']);
        DB::transaction(function () use ($product, $data) {
            $product->forceFill(['distribution' => $data['mode']])->save();
            DB::table('product_distributions')->where('product_id', $product->id)->delete();
            if ($data['mode'] === 'selected') {
                foreach ($data['branches'] ?? [] as $bid) {
                    DB::table('product_distributions')->insert(['product_id' => $product->id, 'branch_id' => $bid, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            Feed::mark('product', $product->uid);
        });
        AuditLogger::log('product_distribution', "{$product->title}: sold at " . ($data['mode'] === 'all' ? 'all branches' : count($data['branches'] ?? []) . ' branch(es)'), 'products', $product);
        return response()->json(['success' => true, 'message' => 'Saved. Branches get it with their next sync.']);
    }
}
