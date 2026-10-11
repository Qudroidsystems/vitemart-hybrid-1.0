@extends('auth.partials.mfa-shell')
@section('title', 'Two-step sign-in')
@section('heading', 'Two-step sign-in')
@section('content')
    <p class="text-center">Hi {{ $user->first_name ?? $user->username }}, open your authenticator app and enter the 6-digit code for this account.</p>
    <form method="POST" action="{{ route('mfa.verify') }}" autocomplete="off">
        @csrf
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" autofocus required
               class="form-control form-control-lg mfa-code @error('code') is-invalid @enderror" placeholder="000000">
        @error('code')<div class="invalid-feedback d-block text-center">{{ $message }}</div>@enderror
        <button class="btn btn-primary w-100 btn-lg mt-3">Verify</button>
    </form>
    <details class="mt-3 small text-muted">
        <summary>Lost your phone?</summary>
        Type one of your recovery codes (like <code>ABCDE-FGHJK</code>) in the box above. Each code works once.
        No codes left? Ask the Super Admin to reset two-step sign-in for your account.
    </details>
@endsection
