@extends('layouts.master')

@section('content')
@php
    $badge = [
        'ok' => ['success', 'bi-check-circle-fill', 'OK'],
        'warn' => ['warning', 'bi-exclamation-triangle-fill', 'Look at this'],
        'fail' => ['danger', 'bi-x-octagon-fill', 'Fix before go-live'],
        'manual' => ['info', 'bi-person-check', 'You confirm'],
    ];
    $order = ['fail' => 0, 'warn' => 1, 'manual' => 2, 'ok' => 3];
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Go-live check</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">What this server still needs before trading on the multi-branch setup. Checked just now
                – the same list as <code>php artisan vitemart:preflight</code>.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-light btn-sm" href="{{ route('mb.sync.index') }}"><i class="bi bi-arrow-left me-1"></i>Sync</a>
            <a class="btn btn-primary btn-sm" href="{{ route('mb.sync.go-live') }}"><i class="bi bi-arrow-clockwise me-1"></i>Check again</a>
        </div>
    </div>

    <div class="alert {{ $summary['ready'] ? 'alert-success' : 'alert-danger' }} d-flex gap-3 align-items-center">
        <i class="bi {{ $summary['ready'] ? 'bi-check-circle' : 'bi-x-octagon' }} fs-3"></i>
        <div>
            <strong>{{ $summary['ready'] ? 'Ready to trade.' : 'Not ready yet.' }}</strong>
            {{ $summary['fail'] }} to fix · {{ $summary['warn'] }} to look at · {{ $summary['manual'] }} for you to confirm · {{ $summary['ok'] }} ok.
            @unless ($summary['ready'])
                <span class="d-block fs-sm">Start with the red items – each one says what to do.</span>
            @endunless
        </div>
    </div>

    <div class="row g-3">
        @foreach ($groups as $group => $rows)
            @php
                $rows = $rows->sortBy(fn ($c) => $order[$c['status']])->values();
                $worst = $rows->first()['status'];
            @endphp
            <div class="col-xl-6">
                <div class="card mb-0 h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ $group }}</h5>
                        <span class="badge bg-{{ $badge[$worst][0] }}-subtle text-{{ $badge[$worst][0] }}">
                            {{ $rows->where('status', 'ok')->count() }} / {{ $rows->count() }} ok
                        </span>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach ($rows as $c)
                            @php [$tone, $icon, $word] = $badge[$c['status']]; @endphp
                            <li class="list-group-item d-flex gap-3 py-2">
                                <i class="bi {{ $icon }} text-{{ $tone }} fs-5 mt-1" title="{{ $word }}" aria-label="{{ $word }}"></i>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <span class="fw-medium">{{ $c['label'] }}</span>
                                        @if ($c['detail'])
                                            <span class="text-muted fs-sm text-break">{{ $c['detail'] }}</span>
                                        @endif
                                    </div>
                                    @if ($c['status'] !== 'ok' && $c['fix'])
                                        <div class="fs-sm mt-1 text-{{ $c['status'] === 'manual' ? 'muted' : $tone }}">{{ $c['fix'] }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
</div>
</div>
</div>
@endsection
