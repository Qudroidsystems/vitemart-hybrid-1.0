@extends('layouts.master')
@section('content')
@include('roles.partials.styles')

<div class="main-content">
<div class="page-content">
<div class="container-fluid rol-page">

    {{-- Hero --}}
    <div class="rol-hero">
        <h1><i class="bi bi-shield-lock-fill me-2"></i>Role Management</h1>
        <p>Define roles, assign permissions, and control what each member of staff can do in the store.</p>
        @can('Create role')
            <div class="rol-hero-actions">
                <button type="button" class="rol-btn rol-btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModalgrid">
                    <i class="bi bi-plus-circle"></i> Create Role
                </button>
            </div>
        @endcan
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

    {{-- Role cards --}}
    @if ($roles->count())
        @php
            $roleIcons = [
                'super admin' => 'bi-shield-fill-check', 'admin' => 'bi-shield-check', 'manager' => 'bi-briefcase',
                'cashier' => 'bi-cash-coin', 'sales person' => 'bi-person-badge', 'salesperson' => 'bi-person-badge',
                'storekeeper' => 'bi-box-seam', 'store keeper' => 'bi-box-seam', 'inventory' => 'bi-box-seam',
                'accountant' => 'bi-calculator', 'supervisor' => 'bi-person-check', 'staff' => 'bi-person-badge',
                'customer' => 'bi-bag', 'user' => 'bi-person',
            ];
        @endphp
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-3">
            @foreach ($roles as $role)
                @php
                    $roleUsers = (int) ($roleUserCounts[$role->id] ?? 0);
                    $permCount = $role->permissions->count();
                    $previewPerms = $role->permissions->pluck('name')->take(4);
                    $icon = $roleIcons[strtolower($role->name)] ?? 'bi-key';
                @endphp
                <div class="col">
                    <div class="rol-rcard h-100" style="animation-delay: {{ min($loop->index, 10) * 0.05 }}s">
                        {{-- Card top --}}
                        <div class="rol-rcard-top">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="rol-rcard-icon"><i class="bi {{ $icon }}"></i></div>
                                @canany(['View role', 'Update user-role', 'Delete role'])
                                    <div class="dropdown rol-dropdown">
                                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="Role actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('Update user-role')
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('roles.adduser', $role->id) }}">
                                                        <i class="bi bi-person-plus-fill text-primary"></i> Add Users
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('View role')
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('roles.show', $role->id) }}">
                                                        <i class="bi bi-eye-fill text-success"></i> View Details
                                                    </a>
                                                </li>
                                            @endcan
                                            @can('Delete role')
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('roles.destroy', $role->id) }}" class="rol-delete-form" data-name="{{ $role->name }}" data-users="{{ $roleUsers }}">
                                                        @csrf @method('DELETE')
                                                        <button class="dropdown-item text-danger" type="submit">
                                                            <i class="bi bi-trash3-fill"></i> Delete Role
                                                        </button>
                                                    </form>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                @endcanany
                            </div>
                            <div class="rol-rcard-name mt-1">
                                {{ $role->name }}
                                @if ($role->badge)<span class="{{ $role->badge }} ms-1" style="font-size:9px;vertical-align:middle;">&nbsp;</span>@endif
                            </div>
                            <div class="rol-rcard-count">
                                <strong>{{ $roleUsers }}</strong> assigned user{{ $roleUsers !== 1 ? 's' : '' }}
                            </div>
                        </div>

                        {{-- Permissions --}}
                        <div class="rol-rcard-body">
                            <div style="font-size:10.5px;font-weight:700;color:var(--rol-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                                Permissions <span class="ms-1">({{ $permCount }})</span>
                            </div>
                            @forelse ($previewPerms as $perm)
                                <span class="rol-perm-tag"><i class="bi bi-check2"></i>{{ $perm }}</span>
                            @empty
                                <span style="font-size:12px;color:var(--rol-muted);">No permissions assigned</span>
                            @endforelse
                            @if ($permCount > 4)
                                @can('View role')
                                    <br><a href="{{ route('roles.show', $role->id) }}" class="rol-perm-more mt-1 d-inline-block">
                                        +{{ $permCount - 4 }} more →
                                    </a>
                                @endcan
                            @endif
                        </div>

                        {{-- Footer --}}
                        <div class="rol-rcard-footer">
                            <span class="rol-users-badge">
                                <i class="bi bi-people-fill"></i> {{ $roleUsers }} user{{ $roleUsers !== 1 ? 's' : '' }}
                            </span>
                            @can('View role')
                                <a href="{{ route('roles.show', $role->id) }}" class="rol-view-link">
                                    View <i class="bi bi-arrow-right"></i>
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rol-empty">
            <span class="rol-empty-icon"><i class="bi bi-shield-x"></i></span>
            <h4>No Roles Created Yet</h4>
            <p>Click "Create Role" to define your first role and assign permissions.</p>
        </div>
    @endif

    {{-- ════ CREATE ROLE MODAL ════ --}}
    @can('Create role')
        <div class="modal fade rol-modal" id="addRoleModalgrid" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Create New Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="kt_modal_add_role_form" action="{{ route('roles.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="rol-form-label">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" class="rol-form-control" name="name" value="{{ old('name') }}" placeholder="e.g. Cashier" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="rol-form-label">Role Badge Colour</label>
                                    <select name="badge" class="rol-form-control">
                                        <option value="">Select colour…</option>
                                        <option value="badge bg-light">Light Grey</option>
                                        <option value="badge bg-dark">Dark</option>
                                        <option value="badge bg-primary">Blue</option>
                                        <option value="badge bg-secondary">Light Blue</option>
                                        <option value="badge bg-success">Green</option>
                                        <option value="badge bg-info">Purple</option>
                                        <option value="badge bg-warning">Yellow</option>
                                        <option value="badge bg-danger">Red</option>
                                    </select>
                                </div>
                            </div>

                            <div class="rol-info-banner" style="background:var(--rol-soft);border-color:#c7d2fe;color:#3730a3;">
                                <i class="bi bi-info-circle-fill me-1"></i> Tick the permissions this role should have. Use a group's checkbox to select everything under it at once, or the search box to find one quickly.
                            </div>

                            @include('roles.partials.permission-picker', ['pickerId' => 'createPermPicker', 'checkedIds' => old('permission', [])])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="rol-btn rol-btn-primary">
                                <i class="bi bi-check-circle"></i> Create Role
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
    // Re-open the create form if the server sent it back with errors
    @if ($errors->any() && old('name') !== null)
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(document.getElementById('addRoleModalgrid')).show();
    @endif

    // Confirm before deleting a role
    document.querySelectorAll('.rol-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            const name = form.dataset.name, users = Number(form.dataset.users || 0);
            const text = users ? `${users} user(s) will lose the permissions this role gives them.` : 'No users have this role.';
            const go = () => { form.dataset.confirmed = '1'; form.submit(); };
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: `Delete the "${name}" role?`, text, showCancelButton: true,
                    confirmButtonText: 'Yes, delete it', confirmButtonColor: '#ef4444' }).then(r => { if (r.isConfirmed) go(); });
            } else if (confirm(`Delete the "${name}" role? ${text}`)) {
                go();
            }
        });
    });
});
</script>
@endsection
