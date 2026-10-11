{{-- Topbar tools (CB UI shell). Two sections: @include(..., ['part' => 'left' | 'right']) --}}
@if (($part ?? 'left') === 'left')
    <div class="d-none d-md-inline-flex align-items-center" style="position:relative;">
        <button type="button" id="spotlight-trigger" style="display:flex;align-items:center;gap:8px;border-radius:10px;padding:7px 14px;cursor:pointer;transition:all .2s;min-width:220px;">
            <i class="mdi mdi-magnify" style="font-size:16px;opacity:.8;"></i>
            <span style="font-size:13px;flex:1;text-align:left;">Search pages, products, customers…</span>
            <div style="display:flex;gap:4px;"><kbd style="font-size:10px;padding:2px 6px;border-radius:4px;">⌘</kbd><kbd style="font-size:10px;padding:2px 6px;border-radius:4px;">K</kbd></div>
        </button>
        <div class="search-tooltip">Press <kbd>⌘K</kbd> or <kbd>Ctrl+K</kbd> to search</div>
    </div>
    <button type="button" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle d-md-none ms-1" id="spotlight-trigger-sm" title="Search">
        <i class="mdi mdi-magnify fs-3xl"></i>
    </button>
@else
    {{-- Theme toggle --}}
    <div class="position-relative" id="theme-toggle-wrapper">
        <button type="button" id="theme-toggle-btn" class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle" style="width:38px;height:38px;" title="Light / dark mode">
            <i id="theme-icon" class="bi bi-sun align-middle fs-3xl"></i>
        </button>
        <div id="theme-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;min-width:170px;background:var(--vz-dropdown-bg,#fff);border:1px solid var(--vz-border-color,#e9ebec);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;overflow:hidden;padding:6px;">
            <a href="javascript:void(0)" class="theme-mode-item d-flex align-items-center gap-2 px-3 py-2 rounded-2 text-decoration-none" data-mode="light"><i class="bi bi-sun"></i> Light</a>
            <a href="javascript:void(0)" class="theme-mode-item d-flex align-items-center gap-2 px-3 py-2 rounded-2 text-decoration-none" data-mode="dark"><i class="bi bi-moon"></i> Dark</a>
            <a href="javascript:void(0)" class="theme-mode-item d-flex align-items-center gap-2 px-3 py-2 rounded-2 text-decoration-none" data-mode="auto"><i class="bi bi-moon-stars"></i> Auto</a>
        </div>
    </div>

    {{-- User menu --}}
    @auth
        @php
            $tbUser = Auth::user();
            $tbName = trim($tbUser->name ?? '') ?: ($tbUser->username ?? 'User');
            $tbInitials = collect(preg_split('/\s+/', $tbName))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
            $tbPhoto = null;
            if ($tbUser->profile_image) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($tbUser->profile_image)) {
                        $tbPhoto = asset('storage/' . $tbUser->profile_image);
                    }
                } catch (\Throwable $e) {
                }
            }
            $tbRoles = $tbUser->roles->pluck('name');
            $tbRoleColors = [
                'super admin' => ['#405189', '#eef2ff'],
                'admin' => ['#0a9396', '#e0f2fe'],
                'manager' => ['#6a0572', '#f3e8ff'],
                'cashier' => ['#2a9d8f', '#e6f7f5'],
                'sales person' => ['#e76f51', '#fff0ed'],
                'salesperson' => ['#e76f51', '#fff0ed'],
                'staff' => ['#457b9d', '#e8f0fe'],
            ];
        @endphp
        <div class="dropdown position-relative ms-sm-3 header-item topbar-user" id="user-dropdown-wrapper">
            <button type="button" id="user-menu-btn" class="btn shadow-none p-0" style="background:transparent;border:none;" aria-haspopup="true" aria-expanded="false">
                <span class="d-flex align-items-center gap-2">
                    <span style="display:inline-block;width:42px;height:42px;flex-shrink:0;position:relative;">
                        @if ($tbPhoto)
                            <img id="topbar-avatar-img" class="header-profile-user-enhanced" src="{{ $tbPhoto }}" alt="{{ $tbName }}" style="width:42px;height:42px;border-radius:10px;object-fit:cover;" onerror="this.style.display='none';document.getElementById('topbar-avatar-fallback').style.display='flex';">
                            <span id="topbar-avatar-fallback" style="display:none;width:42px;height:42px;border-radius:10px;background:#405189;color:#fff;align-items:center;justify-content:center;">{{ $tbInitials }}</span>
                        @else
                            <span class="header-profile-user-enhanced" style="display:flex;width:42px;height:42px;border-radius:10px;background:#405189;color:#fff;align-items:center;justify-content:center;">{{ $tbInitials }}</span>
                        @endif
                    </span>
                    <span class="d-none d-xl-flex flex-column align-items-start ms-1">
                        <span class="fw-medium" style="font-size:13px;">{{ $tbName }}</span>
                        <span class="text-muted" style="font-size:11px;">{{ $tbRoles->first() ?? 'User' }}</span>
                    </span>
                </span>
            </button>

            <div id="user-dropdown" class="dropdown-menu dropdown-menu-end" style="display:none;position:absolute;top:calc(100% + 8px);right:0;min-width:220px;background:var(--vz-dropdown-bg,#fff);border-radius:12px;z-index:9999;">
                <div class="dropdown-header"><h6 class="mb-0">Welcome back!</h6><small class="text-muted">{{ $tbName }}</small></div>
                <div class="dropdown-divider"></div>
                @if ($tbRoles->isNotEmpty())
                    <div class="px-3 py-2">
                        <div class="small text-muted mb-2 text-uppercase" style="font-size:10px;">Your roles</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($tbRoles as $roleName)
                                @php [$rc, $rbg] = $tbRoleColors[strtolower($roleName)] ?? ['#6c757d', '#f8fafc']; @endphp
                                <span style="display:inline-block;font-size:10px;font-weight:600;padding:3px 10px;border-radius:20px;background:{{ $rbg }};color:{{ $rc }};">{{ $roleName }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                @endif
                @if (Route::has('user.overview'))
                    <a class="dropdown-item" href="{{ route('user.overview', $tbUser->id) }}"><i class="mdi mdi-account-circle me-2"></i>My Profile</a>
                @endif
                @if (Route::has('mfa.setup'))
                    <a class="dropdown-item" href="{{ route('mfa.setup') }}"><i class="mdi mdi-shield-key me-2"></i>Two-step sign-in</a>
                @endif
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" id="topbar-logout-form">
                    @csrf
                    <a class="dropdown-item text-danger" href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('topbar-logout-form').submit();"><i class="mdi mdi-logout me-2"></i>Logout</a>
                </form>
            </div>
        </div>
    @else
        <a class="btn btn-sm btn-primary ms-3" href="{{ route('login') }}"><i class="mdi mdi-login me-1"></i>Login</a>
    @endauth
@endif
