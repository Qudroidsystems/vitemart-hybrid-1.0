{{-- Sidebar footer: signed-in user + Sign Out (CB UI shell). --}}
@auth
    @php
        $sbUser = Auth::user();
        $sbName = trim($sbUser->name ?? '') ?: ($sbUser->username ?? 'User');
        $sbInitials = collect(preg_split('/\s+/', $sbName))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
        $sbPhoto = null;
        if ($sbUser->profile_image) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($sbUser->profile_image)) {
                    $sbPhoto = asset('storage/' . $sbUser->profile_image);
                }
            } catch (\Throwable $e) {
            }
        }
        $sbRole = optional($sbUser->roles->first())->name ?? 'User';
    @endphp
    <div class="sidebar-footer">
        <div class="sidebar-footer-user">
            @if ($sbPhoto)
                <img src="{{ $sbPhoto }}" alt="{{ $sbName }}">
            @else
                <span class="sidebar-footer-avatar-initials">{{ $sbInitials }}</span>
            @endif
            <div class="sidebar-footer-user-info">
                <div class="sidebar-footer-user-name">{{ $sbName }}</div>
                <div class="sidebar-footer-user-role">{{ $sbRole }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
            @csrf
            <button type="submit" class="sidebar-logout-btn">
                <i class="mdi mdi-logout"></i>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
@endauth
