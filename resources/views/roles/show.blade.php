@extends('layouts.master')
@section('content')
@include('roles.partials.styles')
@php
    $avatarOf = function ($u) {
        if (!empty($u->profile_image)) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($u->profile_image)) {
                    return asset('storage/' . $u->profile_image);
                }
            } catch (\Throwable $e) {
            }
        }
        return null;
    };
    $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('') ?: '?';
    $permGroups = $rolePermissions->groupBy(fn ($p) => $p->title ?: 'Other')->sortKeys();
@endphp

<div class="main-content">
<div class="page-content">
<div class="container-fluid rol-page">

    {{-- Hero --}}
    <div class="rol-hero">
        <h1><i class="bi bi-shield-fill-check me-2"></i>{{ $role->name }} — Role Details</h1>
        <p>Manage the permissions of this role and the staff who have it.</p>
        <div class="rol-hero-actions">
            <a href="{{ route('roles.index') }}" class="rol-btn rol-btn-back">
                <i class="bi bi-arrow-left"></i> Back to Roles
            </a>
            @can('Update user-role')
                <button type="button" class="rol-btn rol-btn-success" data-bs-toggle="modal" data-bs-target="#addUserModalgrid">
                    <i class="bi bi-person-plus-fill"></i> Add Users
                </button>
            @endcan
            @can('Update role')
                <button type="button" class="rol-btn rol-btn-primary" data-bs-toggle="modal" data-bs-target="#editRoleModalgrid">
                    <i class="bi bi-pencil-square"></i> Edit Role
                </button>
            @endcan
        </div>
    </div>

    {{-- Alerts --}}
    @if ($errors->any())
        <div class="rol-alert-danger mb-3">
            <strong>Whoops!</strong>
            <ul class="mb-0 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @if (session('success') || session('status'))
        <div class="rol-alert-success mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') ?? session('status') }}
        </div>
    @endif

    <div class="row g-3">

        {{-- ── Permissions sidebar ── --}}
        <div class="col-xl-3 col-lg-4">
            <div class="rol-card">
                <div class="rol-card-header">
                    <h5><i class="bi bi-key-fill me-2" style="color:var(--rol-accent)"></i>Permissions</h5>
                    <span style="background:var(--rol-soft);color:var(--rol-accent);border:1px solid #c7d2fe;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">
                        {{ $rolePermissions->count() }}
                    </span>
                </div>
                <div class="rol-card-body" style="max-height:70vh;overflow-y:auto;">
                    @can('Update role')
                        <button type="button" class="edit-role-btn" data-bs-toggle="modal" data-bs-target="#editRoleModalgrid">
                            <i class="bi bi-pencil-square"></i> Edit Role & Permissions
                        </button>
                    @endcan
                    @forelse ($permGroups as $groupName => $perms)
                        <div style="font-size:10.5px;font-weight:700;color:var(--rol-muted);text-transform:uppercase;letter-spacing:.5px;margin:10px 0 4px;">{{ $groupName }}</div>
                        @foreach ($perms as $rm)
                            <div class="perm-list-item" title="{{ \App\Support\PermissionMeta::for($rm->name)['desc'] }}">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>{{ $rm->name }}</span>
                            </div>
                        @endforeach
                    @empty
                        <p style="font-size:13px;color:var(--rol-muted);text-align:center;padding:20px 0;">No permissions assigned</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Users table ── --}}
        <div class="col-xl-9 col-lg-8">
            <div class="rol-card">
                <div class="rol-card-header">
                    <h5>
                        <i class="bi bi-people-fill me-2" style="color:var(--rol-accent)"></i>
                        Assigned Users
                        <span id="totalUsersCount" style="background:var(--rol-accent);color:#fff;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;margin-left:6px;">
                            {{ $userRoleCount }}
                        </span>
                    </h5>
                    @canany(['Remove user-role', 'Delete role'])
                        <button type="button" class="rol-btn rol-btn-danger" id="bulkRemoveBtn" style="display:none;padding:7px 14px;font-size:12.5px;">
                            <i class="bi bi-person-x-fill"></i> Remove Selected
                        </button>
                    @endcanany
                </div>
                <div class="rol-tools">
                    <div class="rol-search">
                        <i class="bi bi-search"></i>
                        <input type="search" id="userSearch" placeholder="Search name, email or username…" autocomplete="off">
                    </div>
                    <span style="font-size:12px;color:var(--rol-muted);">Tick users to remove several at once.</span>
                </div>
                <div class="rol-card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table id="usersDataTable" class="table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" class="rol-checkbox" id="checkAll" aria-label="Select all"></th>
                                    <th width="50">Avatar</th>
                                    <th>Name</th>
                                    <th>Email / Username</th>
                                    <th>Other roles</th>
                                    <th>Joined</th>
                                    @canany(['Remove user-role', 'Delete role'])
                                        <th width="80">Action</th>
                                    @endcanany
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                @forelse ($usersWithRole as $u)
                                    @php
                                        $uName = trim($u->name ?? '') ?: ($u->username ?? 'User');
                                        $photo = $avatarOf($u);
                                        $others = $u->roles->where('id', '!=', $role->id)->pluck('name');
                                    @endphp
                                    <tr data-user-id="{{ $u->id }}" data-search="{{ \Illuminate\Support\Str::lower($uName . ' ' . $u->email . ' ' . $u->username) }}">
                                        <td><input type="checkbox" class="rol-checkbox user-checkbox" value="{{ $u->id }}" data-user-name="{{ $uName }}"></td>
                                        <td><div class="usr-avatar-sm">@if ($photo)<img src="{{ $photo }}" alt="">@else{{ $initialsOf($uName) }}@endif</div></td>
                                        <td>
                                            @if (Route::has('users.show'))
                                                <a href="{{ route('users.show', $u->id) }}" style="font-weight:600;font-size:13.5px;color:var(--rol-ink);">{{ $uName }}</a>
                                            @else
                                                <span style="font-weight:600;font-size:13.5px;color:var(--rol-ink);">{{ $uName }}</span>
                                            @endif
                                        </td>
                                        <td style="font-size:12px;color:var(--rol-muted);">
                                            {{ $u->email }}
                                            @if ($u->username && $u->username !== $u->email)<br><span class="vm-mono">{{ $u->username }}</span>@endif
                                        </td>
                                        <td>
                                            @forelse ($others as $o)
                                                <span class="rol-perm-tag" style="font-size:10px;padding:1px 8px;">{{ $o }}</span>
                                            @empty
                                                <span style="font-size:12px;color:var(--rol-muted);">—</span>
                                            @endforelse
                                        </td>
                                        <td style="font-size:12px;color:var(--rol-muted);">{{ optional($u->created_at)->format('d M Y') }}</td>
                                        @canany(['Remove user-role', 'Delete role'])
                                            <td>
                                                <button type="button" class="remove-user-btn"
                                                        data-user-name="{{ $uName }}"
                                                        data-url="{{ route('roles.removeuserrole', ['userid' => $u->id, 'roleid' => $role->id]) }}"
                                                        data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                        title="Remove from role">
                                                    <i class="bi bi-person-x-fill"></i>
                                                </button>
                                            </td>
                                        @endcanany
                                    </tr>
                                @empty
                                    <tr class="rol-no-users"><td colspan="7" class="text-center py-5" style="color:var(--rol-muted);">
                                        <i class="bi bi-people" style="font-size:36px;opacity:.3;display:block;margin-bottom:8px;"></i>
                                        No one has this role yet.
                                    </td></tr>
                                @endforelse
                                <tr id="noMatchRow" style="display:none;"><td colspan="7" class="text-center py-4" style="color:var(--rol-muted);">No users match your search.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rol-pager" id="usersPager" style="display:none;">
                        <span id="pagerInfo"></span>
                        <div class="pg" id="pagerButtons"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /row --}}

    {{-- ════ REMOVE ONE USER ════ --}}
    <div id="deleteRecordModal" class="modal fade rol-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-body" style="padding:36px 28px;text-align:center;">
                    <div class="del-icon-wrap"><i class="bi bi-person-x-fill"></i></div>
                    <h4 style="font-weight:800;color:var(--rol-ink);margin-bottom:8px;">Remove User?</h4>
                    <p id="singleDeleteMessage" style="color:var(--rol-muted);font-size:14px;">Remove this user from the role?</p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="rol-btn rol-btn-danger" id="delete-record" style="padding:9px 22px;">
                            <i class="bi bi-person-dash-fill"></i> Yes, Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════ REMOVE SEVERAL USERS ════ --}}
    <div id="bulkRemoveModal" class="modal fade rol-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i>Remove Multiple Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="del-icon-wrap" style="width:56px;height:56px;font-size:24px;"><i class="bi bi-people-fill"></i></div>
                    <p style="text-align:center;color:var(--rol-muted);font-size:13.5px;margin-bottom:12px;">
                        Remove <strong id="selectedCount">0</strong> user(s) from <strong>{{ $role->name }}</strong>?
                    </p>
                    <div id="selectedUsersList" style="max-height:160px;overflow-y:auto;background:var(--rol-surface2);border-radius:9px;padding:10px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="rol-btn rol-btn-danger" id="confirmBulkRemove">
                        <i class="bi bi-trash3"></i> Remove All
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ════ ADD USERS ════ --}}
    @can('Update user-role')
        <div class="modal fade rol-modal" id="addUserModalgrid" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add Users to "{{ $role->name }}"</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="addUserRoleForm" action="{{ route('roles.updateuserrole') }}" method="POST">
                        @csrf
                        <input type="hidden" name="roleid" value="{{ $role->id }}">
                        <div class="modal-body">
                            @include('roles.partials.user-picker', ['users' => $usersNotInRole, 'pickerId' => 'addUsersPicker'])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="rol-btn rol-btn-primary" id="submit-add-btn">
                                <i class="bi bi-person-check-fill"></i> Add Selected Users
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    {{-- ════ EDIT ROLE ════ --}}
    @can('Update role')
        <div class="modal fade rol-modal" id="editRoleModalgrid" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Role: {{ $role->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('roles.update', $role->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <div class="modal-body">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="rol-form-label">Role Name</label>
                                    <input type="text" class="rol-form-control" name="name" value="{{ old('name', $role->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="rol-form-label">Badge Colour</label>
                                    <select name="badge" class="rol-form-control">
                                        <option value="">None</option>
                                        @foreach (['badge bg-light' => 'Light Grey', 'badge bg-dark' => 'Dark', 'badge bg-primary' => 'Blue', 'badge bg-secondary' => 'Light Blue', 'badge bg-success' => 'Green', 'badge bg-info' => 'Purple', 'badge bg-warning' => 'Yellow', 'badge bg-danger' => 'Red'] as $val => $lbl)
                                            <option value="{{ $val }}" @selected($role->badge === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <label class="rol-form-label mb-2">Permissions</label>
                            <div class="rol-info-banner" style="background:var(--rol-soft);border-color:#c7d2fe;color:#3730a3;">
                                <i class="bi bi-info-circle-fill me-1"></i> Tick the permissions for this role. A group's checkbox selects everything under it at once.
                            </div>

                            @include('roles.partials.permission-picker', ['pickerId' => 'editPermPicker', 'checkedIds' => $role->permissions->pluck('id')->all()])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="rol-btn rol-btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const roleName = @json($role->name);
    const notify = (icon, title, text) => window.Swal ? Swal.fire({icon, title, text, confirmButtonColor: '#6366f1', timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success'}) : alert(title + (text ? '\n' + text : ''));

    // ── Search + paging (client side) ───────────────────────────────
    const rows = Array.from(document.querySelectorAll('#usersTableBody tr[data-user-id]'));
    const PER_PAGE = 15;
    let page = 1, filtered = rows;
    const search = document.getElementById('userSearch');

    function draw() {
        const pages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
        page = Math.min(page, pages);
        rows.forEach(r => r.style.display = 'none');
        filtered.slice((page - 1) * PER_PAGE, page * PER_PAGE).forEach(r => r.style.display = '');
        document.getElementById('noMatchRow').style.display = rows.length && !filtered.length ? '' : 'none';
        const pager = document.getElementById('usersPager');
        pager.style.display = filtered.length > PER_PAGE ? '' : 'none';
        const from = filtered.length ? (page - 1) * PER_PAGE + 1 : 0, to = Math.min(page * PER_PAGE, filtered.length);
        document.getElementById('pagerInfo').textContent = `Showing ${from}–${to} of ${filtered.length} users`;
        const btns = [];
        btns.push(`<button type="button" data-p="${page - 1}" ${page === 1 ? 'disabled' : ''} aria-label="Previous">‹</button>`);
        for (let i = 1; i <= pages; i++) {
            if (i === 1 || i === pages || Math.abs(i - page) <= 1) btns.push(`<button type="button" data-p="${i}" class="${i === page ? 'active' : ''}">${i}</button>`);
            else if (Math.abs(i - page) === 2) btns.push('<span class="px-1">…</span>');
        }
        btns.push(`<button type="button" data-p="${page + 1}" ${page === pages ? 'disabled' : ''} aria-label="Next">›</button>`);
        document.getElementById('pagerButtons').innerHTML = btns.join('');
        updateBulkBtn();
    }
    document.getElementById('pagerButtons').addEventListener('click', e => {
        const p = e.target.closest('button[data-p]'); if (!p || p.disabled) return;
        page = Number(p.dataset.p); draw();
    });
    search?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        filtered = rows.filter(r => !q || r.dataset.search.includes(q));
        page = 1; draw();
    });

    // ── Remove one user ─────────────────────────────────────────────
    document.querySelectorAll('.remove-user-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const url = this.dataset.url;
            document.getElementById('singleDeleteMessage').textContent = `Remove "${this.dataset.userName}" from the ${roleName} role?`;
            document.getElementById('delete-record').onclick = () => doRemoveSingle(url);
        });
    });

    function doRemoveSingle(url) {
        const btn = document.getElementById('delete-record');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Removing…';
        fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteRecordModal'))?.hide();
                    notify('success', 'Removed!', data.message);
                    setTimeout(() => location.reload(), 1600);
                } else {
                    notify('error', 'Error', data.message || 'Could not remove the user.');
                }
            })
            .catch(() => notify('error', 'Network error', 'Please try again.'))
            .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-dash-fill"></i> Yes, Remove'; });
    }

    // ── Remove several users ────────────────────────────────────────
    const visibleBoxes = () => Array.from(document.querySelectorAll('#usersTableBody tr[data-user-id]')).filter(r => r.style.display !== 'none').map(r => r.querySelector('.user-checkbox'));
    function updateBulkBtn() {
        const checked = document.querySelectorAll('#usersDataTable .user-checkbox:checked').length;
        const btn = document.getElementById('bulkRemoveBtn');
        if (btn) {
            btn.style.display = checked > 0 ? 'inline-flex' : 'none';
            btn.innerHTML = `<i class="bi bi-person-x-fill"></i> Remove Selected (${checked})`;
        }
        const checkAll = document.getElementById('checkAll');
        const vis = visibleBoxes();
        if (checkAll) checkAll.checked = vis.length > 0 && vis.every(c => c.checked);
    }
    document.querySelectorAll('#usersDataTable .user-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBtn));
    document.getElementById('checkAll')?.addEventListener('change', function () {
        visibleBoxes().forEach(cb => cb.checked = this.checked);
        updateBulkBtn();
    });

    document.getElementById('bulkRemoveBtn')?.addEventListener('click', function () {
        const checked = document.querySelectorAll('#usersDataTable .user-checkbox:checked');
        if (!checked.length) return;
        document.getElementById('selectedCount').textContent = checked.length;
        const listEl = document.getElementById('selectedUsersList');
        listEl.innerHTML = '';
        checked.forEach(cb => {
            const d = document.createElement('div');
            d.style.cssText = 'padding:5px 8px;background:var(--rol-surface);border-radius:7px;margin-bottom:4px;font-size:12.5px;display:flex;align-items:center;gap:6px;';
            d.innerHTML = '<i class="bi bi-person-fill" style="color:var(--rol-accent)"></i>';
            d.appendChild(document.createTextNode(cb.dataset.userName));
            listEl.appendChild(d);
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkRemoveModal')).show();
    });

    document.getElementById('confirmBulkRemove')?.addEventListener('click', function () {
        const ids = Array.from(document.querySelectorAll('#usersDataTable .user-checkbox:checked')).map(c => c.value);
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Removing…';
        fetch(@json(route('roles.bulkremoveusers')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ role_id: @json($role->id), selected_users: ids })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('bulkRemoveModal'))?.hide();
                notify('success', 'Done!', data.message);
                setTimeout(() => location.reload(), 1600);
            } else {
                notify('error', 'Error', data.message || 'Could not remove the users.');
            }
        }).catch(() => notify('error', 'Network error', 'Please try again.'))
          .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash3"></i> Remove All'; });
    });

    // Re-open the edit form if the server sent it back with errors
    @if ($errors->any())
        if (window.bootstrap && document.getElementById('editRoleModalgrid')) bootstrap.Modal.getOrCreateInstance(document.getElementById('editRoleModalgrid')).show();
    @endif

    draw();
});
</script>
@endsection
