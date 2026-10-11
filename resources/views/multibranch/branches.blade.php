@extends('layouts.master')

@section('content')
@php $central = $install?->mode === 'central'; @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Branches</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">
                @if ($central)
                    This server is the <strong>headquarters</strong>. Each branch runs its own server and syncs here.
                @elseif ($install?->mode === 'branch')
                    This server is branch <strong>{{ $install->branch->code }} – {{ $install->branch->name }}</strong>, connected to {{ $install->central_url }}.
                @else
                    This server runs as a <strong>single store</strong>. Run <code>php artisan vitemart:install</code> to make it headquarters or a branch.
                @endif
            </p>
        </div>
        @if ($central)
            @can('Manage branches')
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#branchModal"><i class="bi bi-plus-lg me-1"></i>Add branch</button>
            @endcan
        @endif
    </div>

    @include('layouts.partials.flash')

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>#</th><th>Branch</th><th>Type</th><th>Status</th><th>Server</th><th>Last contact</th><th class="text-end">Sync backlog</th><th class="text-end">Sales today</th><th class="text-end">Staff</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($branches as $b)
                    @php
                        $inst = $b->activeInstallation;
                        $isSelf = $b->id === $install?->branch_id;
                        $online = $inst && $inst->last_seen_at && $inst->last_seen_at->gt(now()->subMinutes(10));
                        $sales = $counts[$b->id] ?? null;
                    @endphp
                    <tr>
                        <td class="vm-mono">{{ str_pad($b->number, 2, '0', STR_PAD_LEFT) }}</td>
                        <td><a href="{{ route('mb.branches.show', $b) }}" class="fw-semibold">{{ $b->code }}</a> – {{ $b->name }}
                            @if ($isSelf)<span class="badge bg-primary-subtle text-primary ms-1">this server</span>@endif
                            <div class="fs-xs text-muted">{{ $b->address }}</div></td>
                        <td class="text-capitalize">{{ $b->type }}</td>
                        <td><span class="badge bg-{{ ['active' => 'success', 'suspended' => 'warning', 'closed' => 'secondary'][$b->status] ?? 'light' }}-subtle text-{{ ['active' => 'success', 'suspended' => 'warning', 'closed' => 'secondary'][$b->status] ?? 'body' }} text-capitalize">{{ $b->status }}</span></td>
                        <td>
                            @if ($isSelf)
                                <span class="text-muted fs-sm">—</span>
                            @elseif ($inst)
                                <span class="badge {{ $online ? 'bg-success' : 'bg-danger' }}"><i class="bi bi-circle-fill fs-xs me-1"></i>{{ $online ? 'Online' : 'Offline' }}</span>
                                <div class="fs-xs text-muted">v{{ $inst->app_version }}</div>
                            @else
                                <span class="badge bg-light text-muted">Not enrolled</span>
                            @endif
                        </td>
                        <td class="fs-sm">{{ $inst?->last_seen_at?->diffForHumans() ?? ($isSelf ? 'now' : '—') }}</td>
                        <td class="text-end">
                            @if ($inst)
                                {{ number_format($inst->pending_events) }}
                                @if ($inst->dead_letters)<span class="badge bg-danger ms-1" title="Events that failed">{{ $inst->dead_letters }} failed</span>@endif
                            @else — @endif
                        </td>
                        <td class="text-end">{{ $sales ? money($sales->t, 0) : '—' }}<div class="fs-xs text-muted">{{ $sales ? $sales->c . ' sales' : '' }}</div></td>
                        <td class="text-end">{{ $staff[$b->id] ?? 0 }}</td>
                        <td class="text-end"><a href="{{ route('mb.branches.show', $b) }}" class="btn btn-sm btn-light">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($central)
            <div class="card-body border-top fs-sm text-muted">Sales today only include sales that have already synced from each branch. A branch shown offline keeps selling; its figures arrive when it reconnects.</div>
        @endif
    </div>
</div>
</div>
</div>

@if ($central)
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('mb.branches.store') }}">@csrf
            <div class="modal-header"><h5 class="modal-title">Add branch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" maxlength="10" placeholder="IKJ" required></div>
                    <div class="col-3"><label class="form-label">No.</label><input name="number" type="number" min="1" max="99" class="form-control" value="{{ ($branches->max('number') ?? 0) + 1 }}" required></div>
                    <div class="col-5"><label class="form-label">Type</label><select name="type" class="form-select"><option value="store">Store</option><option value="warehouse">Warehouse</option></select></div>
                    <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" placeholder="Ikeja Branch" required></div>
                    <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Manager</label>
                        <select name="manager_user_id" class="form-select"><option value="">—</option>@foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->first_name }} {{ $m->last_name }}</option>@endforeach</select></div>
                </div>
                <div class="form-text mt-2">The number (1–99) goes into every barcode this branch creates, so it can never clash with another branch.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create branch</button></div>
        </form>
    </div>
</div>
@endif
@endsection
