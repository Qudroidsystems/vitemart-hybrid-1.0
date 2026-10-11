@extends('layouts.master')
@section('content')
@include('roles.partials.styles')

<div class="main-content">
<div class="page-content">
<div class="container-fluid rol-page">

    <div class="rol-hero">
        <h1><i class="bi bi-person-plus-fill me-2"></i>Add Users to Role</h1>
        <p>Give staff the <strong style="color:rgba(255,255,255,.9);">{{ $role->name }}</strong> role and everything it allows.</p>
        <div class="rol-hero-actions">
            <a href="{{ route('roles.index') }}" class="rol-btn rol-btn-back"><i class="bi bi-arrow-left"></i> Back to Roles</a>
            @can('View role')
                <a href="{{ route('roles.show', $role->id) }}" class="rol-btn rol-btn-back"><i class="bi bi-eye"></i> Role Details</a>
            @endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="rol-alert-danger mb-3">
            <strong>Whoops!</strong>
            <ul class="mb-0 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="rol-card">
        <div class="rol-card-header">
            <h5><i class="bi bi-person-badge-fill me-2" style="color:var(--rol-accent)"></i>Role Assignment</h5>
            <div class="rol-role-chip" style="padding:6px 12px;font-size:13px;"><i class="bi bi-shield-check"></i>{{ $role->name }}</div>
        </div>
        <form action="{{ route('roles.updateuserrole') }}" method="POST">
            @csrf
            <input type="hidden" name="roleid" value="{{ $role->id }}">
            <div class="rol-card-body">
                @include('roles.partials.user-picker', ['users' => $users, 'pickerId' => 'addUserPagePicker'])
            </div>
            <div class="d-flex justify-content-end gap-2" style="padding:14px 20px;border-top:1px solid var(--rol-border);background:var(--rol-surface2);">
                <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="rol-btn rol-btn-primary"><i class="bi bi-person-check-fill"></i> Add Selected Users</button>
            </div>
        </form>
    </div>

</div>
</div>
</div>
@endsection
