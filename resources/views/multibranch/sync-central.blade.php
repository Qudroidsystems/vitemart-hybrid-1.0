@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Sync – all branches</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Each branch sends its sales and stock, and downloads the catalogue, prices, staff and settings from here.
                Feed: {{ number_format($feed['rows']) }} records, position {{ number_format($feed['head']) }}@if ($feed['dirty']) · {{ $feed['dirty'] }} change(s) being prepared @endif.</p>
        </div>
        <div class="d-flex gap-2">
        <a class="btn btn-light btn-sm" href="{{ route('mb.sync.go-live') }}"><i class="bi bi-clipboard-check me-1"></i>Go-live check</a>
        @can('Manage sync')
            <button class="btn btn-light btn-sm" id="snapshot" title="Put every product, price, staff member … back into the feed"><i class="bi bi-database-check me-1"></i>Rebuild feed</button>
        @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Branch</th><th>Server</th><th>Last sent us data</th><th>Last downloaded</th><th class="text-end">Catalogue behind</th>
                    <th class="text-end">Waiting at branch</th><th>Last backup</th><th class="text-end">Failed here</th><th class="text-end">Received today</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($branches as $b)
                    @php $i = $b->activeInstallation; $online = $i && $i->last_seen_at && $i->last_seen_at->gt(now()->subMinutes(10)); @endphp
                    <tr>
                        <td><a href="{{ route('mb.branches.show', $b) }}" class="fw-semibold">{{ $b->code }}</a> – {{ $b->name }}</td>
                        <td>
                            @if ($i)
                                <span class="badge {{ $online ? 'bg-success' : 'bg-danger' }}">{{ $online ? 'Online' : 'Offline' }}</span>
                                <div class="fs-xs text-muted">v{{ $i->app_version }} · seen {{ $i->last_seen_at?->diffForHumans() ?? 'never' }}</div>
                            @else
                                <span class="badge bg-light text-muted">Not enrolled</span>
                            @endif
                        </td>
                        <td class="fs-sm">{{ $i?->last_push_at?->diffForHumans() ?? '—' }}</td>
                        <td class="fs-sm">{{ $i?->last_pull_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">{{ $b->sync_behind === null ? '—' : ($b->sync_behind ? number_format($b->sync_behind) : 'up to date') }}</td>
                        <td class="text-end {{ ($i?->pending_events ?? 0) > 500 ? 'text-warning fw-semibold' : '' }}">{{ number_format($i?->pending_events ?? 0) }}
                            @if (($i?->dead_letters ?? 0) > 0)<div class="fs-xs text-danger">{{ $i->dead_letters }} gave up</div>@endif</td>
                        <td class="fs-sm {{ !$i?->last_backup_at || $i->last_backup_at->lt(now()->subDays(2)) ? 'text-danger' : '' }}">{{ $i?->last_backup_at?->diffForHumans() ?? 'never' }}</td>
                        <td class="text-end {{ $b->inbox_failed ? 'text-danger fw-semibold' : '' }}">{{ number_format($b->inbox_failed) }}</td>
                        <td class="text-end">{{ number_format($b->inbox_today) }}</td>
                        <td class="text-end">
                            @if ($i)
                                @can('Manage sync')<button class="btn btn-sm btn-light send-cmd" data-id="{{ $b->id }}" data-code="{{ $b->code }}">Send instruction</button>@endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No branches yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Could not be applied here (the branch keeps retrying)</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Branch</th><th>What</th><th>Why</th><th>Tries</th><th>Last</th></tr></thead>
                        <tbody>
                        @forelse ($failures as $f)
                            <tr><td class="fw-semibold">{{ $f->code }}</td><td class="fs-sm">{{ $f->entity }} <span class="vm-mono fs-xs text-muted">{{ $f->entity_uid }}</span></td>
                                <td class="fs-sm" style="max-width:340px">{{ $f->error }}</td><td>{{ $f->attempts }}</td><td class="fs-sm">{{ \Carbon\Carbon::parse($f->updated_at)->diffForHumans() }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Nothing failing.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Records matched to existing ones</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Branch</th><th>Table</th><th>Branch record</th><th>Same as</th><th>Because</th></tr></thead>
                        <tbody>
                        @forelse ($aliases as $a)
                            <tr><td>{{ $a->code ?? '—' }}</td><td class="fs-sm">{{ $a->entity }}</td><td class="vm-mono fs-xs">{{ $a->alias_uid }}</td><td class="vm-mono fs-xs">{{ $a->target_uid }}</td><td class="fs-sm">{{ $a->reason }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">None.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Instructions sent</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse ($commands as $c)
                        @php $col = ['pending' => 'warning', 'done' => 'success', 'failed' => 'danger', 'refused' => 'danger'][$c->status] ?? 'secondary'; @endphp
                        <li class="list-group-item fs-sm">
                            <span class="fw-semibold">{{ $c->code }}</span> · {{ $cmdList[$c->command] ?? $c->command }}
                            <span class="badge bg-{{ $col }}-subtle text-{{ $col }} ms-1">{{ $c->status }}{{ $c->status === 'pending' && $c->delivered_at ? ' (delivered)' : '' }}</span>
                            <div class="fs-xs text-muted">{{ \Carbon\Carbon::parse($c->created_at)->diffForHumans() }}</div>
                            @if ($c->result)<div class="fs-xs text-muted text-truncate" title="{{ $c->result }}">{{ $c->result }}</div>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted fs-sm">None yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">In the feed</h6></div>
                <ul class="list-group list-group-flush">
                    @foreach ($feed['by'] as $e => $c)
                        <li class="list-group-item d-flex justify-content-between fs-sm"><span>{{ $e }}</span><span>{{ number_format($c) }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<div class="modal fade" id="cmdModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Instruction for <span id="cmdBranch"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @foreach ($cmdList as $k => $label)
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="cmd" id="cmd_{{ $k }}" value="{{ $k }}" @checked($loop->first)>
                    <label class="form-check-label" for="cmd_{{ $k }}">{{ $label }}</label></div>
            @endforeach
            <div class="mt-2" id="sinceBox" style="display:none"><label class="form-label fs-sm">From date (optional)</label><input type="date" class="form-control" id="cmdSince"></div>
            <p class="text-muted fs-sm mb-0 mt-3">The branch picks it up on its next sync. If the branch runs an older version than needed, it refuses and says so.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="cmdGo">Send</button></div>
    </div></div>
</div>
@endsection

@push('page-scripts')
<script>
(() => {
    let branchId = null;
    const modal = new bootstrap.Modal('#cmdModal');
    document.addEventListener('click', e => {
        const b = e.target.closest('.send-cmd'); if (!b) return;
        branchId = b.dataset.id; document.getElementById('cmdBranch').textContent = b.dataset.code; modal.show();
    });
    document.querySelectorAll('input[name=cmd]').forEach(r => r.addEventListener('change', () => {
        document.getElementById('sinceBox').style.display = document.querySelector('input[name=cmd]:checked').value === 'resend_history' ? '' : 'none';
    }));
    document.getElementById('cmdGo').addEventListener('click', async () => {
        try {
            const res = await VM.api(`{{ url('/sync/branches') }}/${branchId}/command`, {method: 'POST', body: {
                command: document.querySelector('input[name=cmd]:checked').value, since: document.getElementById('cmdSince').value || null}});
            modal.hide(); VM.toast(res.message); setTimeout(() => location.reload(), 900);
        } catch (err) { VM.error(err); }
    });
    document.getElementById('snapshot')?.addEventListener('click', async () => {
        if (!await VM.confirm('Rebuild the feed?', 'Every branch re-checks every product, price, staff member and setting. Safe, but it means more downloading for a few minutes.')) return;
        try { const r = await VM.api(@json(route('mb.sync.snapshot')), {method: 'POST'}); VM.toast(r.message); setTimeout(() => location.reload(), 900); } catch (err) { VM.error(err); }
    });
})();
</script>
@endpush
