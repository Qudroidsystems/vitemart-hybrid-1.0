@extends('layouts.master')

@section('content')
@php $central = $install?->mode === 'central'; $isSelf = $branch->id === $install?->branch_id; @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $branch->code }} – {{ $branch->name }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Branch #{{ str_pad($branch->number, 2, '0', STR_PAD_LEFT) }} · {{ ucfirst($branch->type) }} · {{ ucfirst($branch->status) }} @if ($isSelf)· <strong>this server</strong>@endif</p>
        </div>
        <a href="{{ route('mb.branches.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All branches</a>
    </div>

    @include('layouts.partials.flash')

    <div class="row g-3">
        <div class="col-xl-5">
            <form class="card" method="POST" action="{{ route('mb.branches.update', $branch) }}">@csrf @method('PUT')
                <div class="card-header"><h6 class="card-title mb-0">Details</h6></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" value="{{ $branch->code }}" @readonly(!$central)></div>
                        <div class="col-3"><label class="form-label">No.</label><input name="number" type="number" class="form-control" value="{{ $branch->number }}" @readonly(!$central)></div>
                        <div class="col-5"><label class="form-label">Type</label>
                            <select name="type" class="form-select" @disabled(!$central)>@foreach (['store', 'warehouse'] as $t)<option @selected($branch->type === $t)>{{ $t }}</option>@endforeach</select>
                            @if (!$central)<input type="hidden" name="type" value="{{ $branch->type }}">@endif</div>
                        <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $branch->name }}"></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ $branch->address }}"></div>
                        <div class="col-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $branch->phone }}"></div>
                        <div class="col-6"><label class="form-label">Email</label><input name="email" class="form-control" value="{{ $branch->email }}"></div>
                        @if ($central)
                            <div class="col-6"><label class="form-label">Status</label>
                                <select name="status" class="form-select">@foreach (['active', 'suspended', 'closed'] as $s)<option @selected($branch->status === $s)>{{ $s }}</option>@endforeach</select></div>
                        @endif
                        <div class="col-12"><label class="form-label">Receipt footer</label><input name="receipt_footer" class="form-control" value="{{ $branch->receipt_footer }}"></div>
                    </div>
                </div>
                @can('Manage branches')<div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>@endcan
            </form>

            @if ($central && !($branch->number === 0))
            <div class="card">
                <div class="card-header d-flex"><h6 class="card-title mb-0 flex-grow-1">Branch server</h6>
                    @can('Manage branches')<button class="btn btn-sm btn-primary" id="issueCode"><i class="bi bi-key me-1"></i>Issue enrollment code</button>@endcan</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Server</th><th>Status</th><th>Last contact</th><th>Version</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($branch->installations as $i)
                            <tr>
                                <td class="vm-mono fs-xs">{{ substr($i->uid, -8) }}<div class="text-muted">{{ $i->last_ip }}</div></td>
                                <td><span class="badge bg-{{ $i->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $i->status === 'active' ? 'success' : 'secondary' }}">{{ $i->status }}</span></td>
                                <td class="fs-sm">{{ $i->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                                <td class="fs-sm">{{ $i->app_version }}</td>
                                <td class="text-end">@if ($i->status === 'active')
                                    <form method="POST" action="{{ route('mb.branches.revoke', $i) }}" onsubmit="return confirm('Revoke this server? It stops syncing until enrolled again.')">@csrf<button class="btn btn-sm btn-light text-danger">Revoke</button></form>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No server enrolled yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Staff who can work here</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Roles</th><th>Access</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($users as $u)
                            <tr>
                                <td>{{ $u->first_name }} {{ $u->last_name }}<div class="fs-xs text-muted">{{ $u->email }}</div></td>
                                <td class="fs-sm">{{ $u->roles->pluck('name')->join(', ') }}</td>
                                <td>{!! $u->branch_id === $branch->id ? '<span class="badge bg-primary-subtle text-primary">Home branch</span>' : '<span class="badge bg-light text-body">Also works here</span>' !!}</td>
                                <td class="text-end">@can('Manage branches')@if ($u->branch_id !== $branch->id)
                                    <form method="POST" action="{{ route('mb.branches.user-access', $branch) }}">@csrf<input type="hidden" name="user_id" value="{{ $u->id }}"><input type="hidden" name="action" value="remove"><button class="btn btn-sm btn-light text-danger">Remove</button></form>@endif @endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No staff assigned.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @can('Manage branches')
                <form class="card-body border-top row g-2 align-items-end" method="POST" action="{{ route('mb.branches.user-access', $branch) }}">@csrf
                    <div class="col-md-6"><label class="form-label fs-sm">Staff member</label>
                        <select name="user_id" class="form-select" required><option value="">Choose…</option>@foreach ($allUsers as $u)<option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><select name="action" class="form-select"><option value="add">Can also work here</option><option value="home">Make this their home branch</option></select></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
                </form>
                @endcan
                <div class="card-body border-top fs-sm text-muted pt-2">At a branch server, only staff whose home branch is this one (or who were added here) can log in. Super Admins and roles with “Access all branches” can work anywhere.</div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
document.getElementById('issueCode')?.addEventListener('click', async () => {
    if (!await VM.confirm('Issue a new enrollment code?', 'It works once, for 24 hours. Enrolling a new server replaces the current one.')) return;
    try {
        const r = await VM.api(@json(route('mb.branches.issue-code', $branch)), {method: 'POST'});
        Swal.fire({title: 'Enrollment code', width: 720, html: `<div class="display-6 vm-mono my-2">${VM.esc(r.code)}</div><div class="text-muted mb-3">Valid until ${VM.esc(r.expires)} · shown only once</div>
            <div class="text-start fs-sm">On the branch server, run:</div><pre class="text-start bg-light p-2 rounded fs-xs" style="white-space:pre-wrap">${VM.esc(r.command)}</pre>`});
    } catch (e) { VM.error(e); }
});
</script>
@endpush
