@extends('layouts.master')

@section('content')
@php
    $ago = fn ($iso) => $iso ? \Carbon\Carbon::parse($iso)->diffForHumans() : 'never';
    $cursor = (int) ($state['feed_cursor'] ?? 0);
    $head = (int) ($state['feed_head'] ?? 0);
    $hq = $state['last_sync_at'] ?? null;
    $stale = !$hq || \Carbon\Carbon::parse($hq)->lt(now()->subMinutes(10));
    $entityNames = ['order' => 'Sales', 'order_item' => 'Sale lines', 'order_payment' => 'Payments', 'stock' => 'Stock movements',
        'customer' => 'Customers', 'customer_point_tx' => 'Loyalty points', 'pos_shift' => 'Shifts', 'pos_cash_movement' => 'Cash in/out',
        'refund' => 'Refunds', 'stock_adjustment' => 'Stock adjustments', 'stock_adjustment_item' => 'Adjustment lines', 'audit_log' => 'Audit trail',
        'product' => 'Local products', 'user' => 'Staff', 'stock_location' => 'Locations', 'cash_register' => 'Tills', 'uid_alias' => 'Record links'];
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Sync with head office</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">
                @if (!empty($standalone))
                    This server runs as a single store – nothing to sync.
                @else
                    Sales, stock and customers go to head office; catalogue, prices, staff and settings come back. Runs every minute.
                @endif
            </p>
        </div>
        @if (empty($standalone))
            @can('Manage sync')
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-sm" href="{{ route('mb.sync.go-live') }}"><i class="bi bi-clipboard-check me-1"></i>Go-live check</a>
                    <button class="btn btn-light btn-sm" id="backupNow"><i class="bi bi-hdd me-1"></i>Back up now</button>
                    <button class="btn btn-light btn-sm" id="resync"><i class="bi bi-cloud-download me-1"></i>Download catalogue again</button>
                    <button class="btn btn-primary btn-sm" id="syncNow"><span class="spinner-border spinner-border-sm me-1 d-none"></span><i class="bi bi-arrow-repeat me-1"></i>Sync now</button>
                </div>
            @endcan
        @else
            <a class="btn btn-primary btn-sm" href="{{ route('mb.sync.go-live') }}"><i class="bi bi-clipboard-check me-1"></i>Go-live check</a>
        @endif
    </div>

    @if (empty($standalone))
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-sm">Last sync</div>
            <div class="fs-4 fw-semibold {{ $stale ? 'text-danger' : 'text-success' }}">{{ $ago($hq) }}</div>
            <div class="fs-xs text-muted">{{ $install?->central_url }}</div>
            @if (!empty($state['last_error']['message']))<div class="fs-xs text-danger mt-1">{{ $state['last_error']['message'] }}</div>@endif
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-sm">Waiting to send</div>
            <div class="fs-4 fw-semibold">{{ number_format($counts['pending'] ?? 0) }}</div>
            <div class="fs-xs text-muted">{{ $oldest ? 'oldest ' . \Carbon\Carbon::parse($oldest)->diffForHumans() : 'all sent' }} · last push {{ $ago($state['last_push_at'] ?? null) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-sm">Catalogue updates</div>
            <div class="fs-4 fw-semibold">{{ $head > $cursor ? number_format($head - $cursor) . ' behind' : 'up to date' }}</div>
            <div class="fs-xs text-muted">position {{ number_format($cursor) }} · last pull {{ $ago($state['last_pull_at'] ?? null) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-sm">Problems</div>
            <div class="fs-4 fw-semibold {{ ($counts['dead'] ?? 0) || count($problems) ? 'text-danger' : '' }}">{{ number_format(count($problems)) }}</div>
            <div class="fs-xs text-muted">{{ number_format($counts['dead'] ?? 0) }} gave up · {{ count($state['feed_failures'] ?? []) }} catalogue item(s) skipped</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Not sent yet – problems</h6>
                    @if (count($problems))
                        @can('Manage sync')<button class="btn btn-sm btn-soft-primary" id="retryAll">Send all again</button>@endcan
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>What</th><th>Record</th><th>Tries</th><th>Head office said</th><th>Next try</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($problems as $p)
                            <tr>
                                <td>{{ $entityNames[$p->entity] ?? $p->entity }} @if ($p->status === 'dead')<span class="badge bg-danger-subtle text-danger">gave up</span>@endif</td>
                                <td class="vm-mono fs-xs">{{ $p->entity_uid }}</td>
                                <td>{{ $p->attempts }}</td>
                                <td class="fs-sm" style="max-width:360px">{{ $p->last_error }}</td>
                                <td class="fs-sm">{{ $p->status === 'dead' ? '—' : ($p->next_attempt_at ? \Carbon\Carbon::parse($p->next_attempt_at)->diffForHumans() : 'now') }}</td>
                                <td class="text-end">@can('Manage sync')<button class="btn btn-sm btn-link retry-one" data-id="{{ $p->id }}">Send again</button>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No problems – everything is going through.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (!empty($state['feed_failures']))
                <div class="card">
                    <div class="card-header"><h6 class="card-title mb-0">Catalogue items that could not be applied</h6></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>When</th><th>What</th><th>Why</th></tr></thead>
                            <tbody>
                            @foreach (array_reverse($state['feed_failures']) as $f)
                                <tr><td class="fs-sm text-nowrap">{{ \Carbon\Carbon::parse($f['at'])->diffForHumans() }}</td><td class="fs-sm">{{ $f['entity'] }} <span class="vm-mono fs-xs text-muted">{{ $f['uid'] }}</span></td><td class="fs-sm">{{ $f['message'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer fs-sm text-muted">“Download catalogue again” re-applies everything from head office.</div>
                </div>
            @endif
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Waiting to send</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse ($byEntity as $e => $c)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $entityNames[$e] ?? $e }}</span><span class="fw-semibold">{{ number_format($c) }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">Nothing – all sent.</li>
                    @endforelse
                </ul>
            </div>
            @php
                $__bk = \Illuminate\Support\Facades\Schema::hasTable('backup_runs') ? \Illuminate\Support\Facades\DB::table('backup_runs')->orderByDesc('id')->limit(5)->get() : collect();
                $__up = \Illuminate\Support\Facades\Schema::hasTable('upgrade_runs') ? \Illuminate\Support\Facades\DB::table('upgrade_runs')->orderByDesc('id')->first() : null;
            @endphp
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Backups &amp; upgrades</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse ($__bk as $bk)
                        <li class="list-group-item fs-sm d-flex justify-content-between">
                            <span>{{ \Carbon\Carbon::parse($bk->finished_at ?? $bk->started_at)->diffForHumans() }} · {{ $bk->kind }}</span>
                            <span class="{{ $bk->status === 'ok' ? 'text-success' : 'text-danger' }}">{{ $bk->status === 'ok' ? number_format(($bk->size_bytes ?? 0) / 1048576, 1) . ' MB' : $bk->status }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-danger fs-sm">No backup yet.</li>
                    @endforelse
                    @if ($__up)
                        <li class="list-group-item fs-sm">Last upgrade: {{ $__up->to_version }} · <span class="{{ $__up->status === 'ok' ? 'text-success' : 'text-danger' }}">{{ str_replace('_', ' ', $__up->status) }}</span> · {{ \Carbon\Carbon::parse($__up->started_at)->diffForHumans() }}</li>
                    @endif
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Instructions from head office</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse ($runs as $r)
                        <li class="list-group-item fs-sm"><span class="fw-semibold">{{ $r->command }}</span> · {{ $r->status }} · {{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}
                            <div class="fs-xs text-muted text-truncate">{{ $r->result }}</div></li>
                    @empty
                        <li class="list-group-item text-muted fs-sm">None yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(() => {
    const post = async (url, body = {}) => { const r = await VM.api(url, {method: 'POST', body}); VM.toast(r.message, r.success ? 'success' : 'info'); setTimeout(() => location.reload(), 900); };
    document.getElementById('syncNow')?.addEventListener('click', async e => {
        const b = e.currentTarget; b.disabled = true; b.querySelector('.spinner-border').classList.remove('d-none');
        try { await post(@json(route('mb.sync.run'))); } catch (err) { VM.error(err); b.disabled = false; b.querySelector('.spinner-border').classList.add('d-none'); }
    });
    document.getElementById('resync')?.addEventListener('click', async () => {
        if (!await VM.confirm('Download the catalogue again?', 'Everything head office sends (products, prices, staff, settings) is applied again. Sales are not affected.')) return;
        try { await post(@json(route('mb.sync.resync'))); } catch (err) { VM.error(err); }
    });
    document.getElementById('backupNow')?.addEventListener('click', async e => {
        e.currentTarget.disabled = true;
        try { await post(@json(route('mb.sync.backup'))); } catch (err) { VM.error(err); e.currentTarget.disabled = false; }
    });
    document.getElementById('retryAll')?.addEventListener('click', async () => { try { await post(@json(route('mb.sync.retry'))); } catch (err) { VM.error(err); } });
    document.addEventListener('click', async e => {
        const b = e.target.closest('.retry-one'); if (!b) return;
        try { await post(@json(route('mb.sync.retry')), {id: b.dataset.id}); } catch (err) { VM.error(err); }
    });
})();
</script>
@endpush
