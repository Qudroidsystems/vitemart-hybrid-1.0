<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    @php $storeName = \App\Models\StoreSetting::getSettings()?->store_name ?? config('app.name'); @endphp
    <title>@yield('title') | {{ $storeName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); min-height: 100vh; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .mfa-card { max-width: 480px; margin: 6vh auto; border: 0; border-radius: 14px; box-shadow: 0 10px 30px rgba(2, 6, 23, .08); }
        .mfa-code { font-size: 1.6rem; letter-spacing: .5rem; text-align: center; font-variant-numeric: tabular-nums; }
        .mfa-qr svg { width: 200px; height: 200px; }
        .mfa-secret { font-family: ui-monospace, monospace; font-size: 1rem; letter-spacing: .08rem; word-break: break-all; }
        .rc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; font-family: ui-monospace, monospace; }
        .rc-grid span { background: #f1f5f9; border-radius: 6px; padding: .35rem .5rem; text-align: center; }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="card mfa-card">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-3">
                <div class="avatar-sm mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-primary-subtle" style="width:52px;height:52px">
                    <i class="ri-shield-keyhole-line fs-3 text-primary"></i>
                </div>
                <h4 class="mb-1">@yield('heading')</h4>
                <p class="text-muted mb-0">{{ $storeName }} · {{ \App\Services\MultiBranch\Install::branch()?->name }}</p>
            </div>
            @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
            @if (session('warning'))<div class="alert alert-warning py-2">{{ session('warning') }}</div>@endif
            @yield('content')
            <form method="POST" action="{{ route('logout') }}" class="text-center mt-4">
                @csrf
                <button class="btn btn-link btn-sm text-muted">Sign out</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
