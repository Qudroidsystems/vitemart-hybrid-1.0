@extends('auth.partials.mfa-shell')
@section('title', 'Two-step sign-in')
@section('heading', 'Two-step sign-in')
@section('content')
    @if ($recovery)
        <div class="alert alert-warning">
            <strong>Save these recovery codes now.</strong> Each one lets you sign in once if you lose your phone.
            They will not be shown again.
        </div>
        <div class="rc-grid mb-3" id="rc">@foreach ($recovery as $c)<span>{{ $c }}</span>@endforeach</div>
        <button type="button" class="btn btn-soft-secondary btn-sm w-100 mb-3"
                onclick="navigator.clipboard.writeText(document.getElementById('rc').innerText);this.innerText='Copied'">
            <i class="ri-file-copy-line"></i> Copy codes
        </button>
    @endif

    @if (!$enabled)
        @if ($required)
            <div class="alert alert-info py-2 small">Your role can change prices, stock or money across branches, so two-step sign-in is required.</div>
        @endif
        <ol class="ps-3">
            <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…).</li>
            <li>Scan this code, or type the key by hand.</li>
        </ol>
        <div class="text-center mfa-qr mb-2">{!! $qr !!}</div>
        <p class="text-center mfa-secret mb-3">{{ $secret }}</p>
        <form method="POST" action="{{ route('mfa.confirm') }}" autocomplete="off">
            @csrf
            <label class="form-label">3. Enter the 6-digit code the app shows</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required
                   class="form-control form-control-lg mfa-code @error('code') is-invalid @enderror" placeholder="000000">
            @error('code')<div class="invalid-feedback d-block text-center">{{ $message }}</div>@enderror
            <button class="btn btn-primary w-100 btn-lg mt-3">Turn on</button>
        </form>
    @else
        <div class="alert alert-success py-2"><i class="ri-checkbox-circle-line"></i> On since {{ $user->two_factor_confirmed_at?->format('d M Y H:i') }}. {{ $recoveryLeft }} recovery codes left.</div>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary w-100 mb-3">Continue</a>
        <form method="POST" action="{{ route('mfa.regenerate') }}" class="mb-2">
            @csrf
            <div class="input-group">
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Your password" required>
                <button class="btn btn-soft-primary">New recovery codes</button>
            </div>
        </form>
        @unless ($required)
            <form method="POST" action="{{ route('mfa.disable') }}">
                @csrf
                <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="Your password" required>
                    <button class="btn btn-soft-danger">Turn off</button>
                </div>
            </form>
        @endunless
        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @endif
@endsection
