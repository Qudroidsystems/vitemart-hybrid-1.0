@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Audit trail</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Who did what, when, from where – with the values before and after every change.</p>
        </div>
        @canany(['Export audit trail', 'View audit trail'])
            <a href="{{ route('audit.export', request()->query()) }}" class="btn btn-light btn-sm mt-2 mt-sm-0"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endcanany
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Events today', $stats['today'], 'bi-activity', 'primary', []], ['Failed logins today', $stats['failed_logins'], 'bi-shield-exclamation', 'danger', ['event' => 'login_failed', 'from' => today()->toDateString()]],
                   ['Price changes today', $stats['price_changes'], 'bi-tags', 'info', ['module' => 'pricing']], ['Voids & refunds today', $stats['voids'], 'bi-arrow-return-left', 'warning', ['module' => 'pos', 'from' => today()->toDateString()]]] as [$l, $v, $i, $c, $q])
            <div class="col-6 col-xl-3">
                <a href="{{ route('audit.index', $q) }}" class="card vm-stat mb-0 h-100 text-reset"><div class="card-body d-flex gap-3 align-items-center">
                    <span class="avatar-sm"><span class="avatar-title rounded bg-{{ $c }}-subtle text-{{ $c }} fs-3"><i class="bi {{ $i }}"></i></span></span>
                    <div><div class="vm-stat-label">{{ $l }}</div><div class="vm-stat-value">{{ number_format($v) }}</div></div>
                </div></a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                @if(request('subject_type'))<input type="hidden" name="subject_type" value="{{ request('subject_type') }}"><input type="hidden" name="subject_id" value="{{ request('subject_id') }}">@endif
                <div class="col-md-3"><label class="form-label fs-xs text-muted mb-1">Search</label><input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Text, item, user or IP"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">User</label>
                    <select name="user_id" class="form-select"><option value="">Everyone</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">Area</label>
                    <select name="module" class="form-select"><option value="">All areas</option>@foreach ($modules as $m)<option value="{{ $m }}" @selected(request('module') === $m)>{{ ucfirst($m) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-1"><label class="form-label fs-xs text-muted mb-1">Event</label>
                    <select name="event" class="form-select"><option value="">All</option>@foreach ($events as $e)<option value="{{ $e }}" @selected(request('event') === $e)>{{ str_replace('_', ' ', $e) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-1"><label class="form-label fs-xs text-muted mb-1">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
                <div class="col-6 col-md-1"><label class="form-label fs-xs text-muted mb-1">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
                <div class="col-6 col-md-2 d-flex gap-1"><button class="btn btn-primary flex-fill">Filter</button><a href="{{ route('audit.index') }}" class="btn btn-light"><i class="bi bi-x-lg"></i></a></div>
            </form>
            @if(request('subject_type'))
                <div class="mt-2 fs-sm"><span class="badge bg-primary-subtle text-primary">Showing history for one {{ class_basename(request('subject_type')) }}</span></div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th style="width:160px">When</th><th>User</th><th>Event</th><th>What</th><th>Changes</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr class="audit-row" data-id="{{ $log->id }}" style="cursor:pointer">
                        <td class="fs-sm text-nowrap">{{ $log->created_at->format('d M Y') }}<div class="text-muted">{{ $log->created_at->format('h:i:s A') }}</div></td>
                        <td class="fs-sm">{{ $log->user_name ?: 'System' }}</td>
                        <td><span class="badge bg-{{ $log->event_color }}-subtle text-{{ $log->event_color }}">{{ str_replace('_', ' ', $log->event) }}</span><div class="fs-xs text-muted">{{ $log->module }}</div></td>
                        <td style="max-width:360px">
                            @if($log->auditable_label)<div class="fw-medium text-truncate">{{ $log->auditable_label }}</div>@endif
                            <div class="fs-sm text-muted text-truncate">{{ $log->description }}</div>
                        </td>
                        <td class="fs-xs" style="max-width:300px">
                            @foreach (array_slice($log->changedKeys(), 0, 3) as $k)
                                @php $o = $log->old_values[$k] ?? null; $n = $log->new_values[$k] ?? null; @endphp
                                <div class="text-truncate"><span class="text-muted">{{ $k }}:</span>
                                    @if($log->old_values)<span class="vm-diff-old px-1 rounded">{{ is_scalar($o) || $o === null ? \Illuminate\Support\Str::limit((string) ($o ?? '∅'), 30) : 'list' }}</span> →@endif
                                    <span class="vm-diff-new px-1 rounded">{{ is_scalar($n) || $n === null ? \Illuminate\Support\Str::limit((string) ($n ?? '∅'), 30) : 'list' }}</span></div>
                            @endforeach
                            @if(count($log->changedKeys()) > 3)<span class="text-muted">+{{ count($log->changedKeys()) - 3 }} more</span>@endif
                        </td>
                        <td class="fs-sm vm-mono">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No activity matches these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{!! $logs->links('pagination::bootstrap-5') !!}</div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
document.querySelectorAll('.audit-row').forEach(r => r.addEventListener('click', async () => {
    try {
        const d = await VM.api('/audit-trail/' + r.dataset.id);
        const fmt = v => v === null || v === undefined ? '<span class="text-muted">∅</span>' : VM.esc(typeof v === 'object' ? JSON.stringify(v) : v);
        const rows = d.keys.map(k => `<tr><td class="text-muted">${VM.esc(k)}</td><td>${d.old ? `<span class="vm-diff-old px-1 rounded">${fmt(d.old[k])}</span>` : ''}</td><td><span class="vm-diff-new px-1 rounded">${fmt(d.new ? d.new[k] : null)}</span></td></tr>`).join('');
        Swal.fire({
            width: 760, title: d.event.replace(/_/g, ' '),
            html: `<div class="text-start fs-sm">
                <div class="mb-2">${VM.esc(d.description || '')}</div>
                <table class="table table-sm"><tr><th style="width:120px">When</th><td>${VM.esc(d.when)}</td></tr>
                <tr><th>User</th><td>${VM.esc(d.user)}</td></tr>
                ${d.subject ? `<tr><th>Item</th><td>${VM.esc(d.subject)} <span class="text-muted">(${VM.esc(d.subject_type || '')} #${VM.esc(d.subject_id || '')})</span></td></tr>` : ''}
                <tr><th>From</th><td>${VM.esc(d.ip || '')} · ${VM.esc(d.agent || '')}</td></tr>
                <tr><th>Page</th><td class="text-break">${VM.esc(d.method || '')} ${VM.esc(d.url || '')}</td></tr></table>
                ${rows ? `<table class="table table-sm"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>${rows}</tbody></table>` : ''}</div>`,
        });
    } catch (e) { VM.error(e); }
}));
</script>
@endpush
