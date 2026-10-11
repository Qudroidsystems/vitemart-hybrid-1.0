{{--
    Searchable user cards for adding users to a role.
    Params: $users (collection of User, with roles loaded), $pickerId (unique id)
--}}
@php
    $upAvatar = function ($u) {
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
@endphp
<div class="user-picker" id="{{ $pickerId }}">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
        <div class="rol-modal-search flex-grow-1 mb-0" style="max-width:420px;">
            <i class="bi bi-search"></i>
            <input type="search" class="up-search" placeholder="Search by name, email or username…" autocomplete="off">
        </div>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--rol-accent);margin:0;">
            <input type="checkbox" class="rol-checkbox up-all"> Select all shown
        </label>
    </div>
    <div class="rol-info-banner">
        <i class="bi bi-info-circle-fill me-1"></i>
        Selected: <strong class="up-count">0</strong> of {{ $users->count() }} user(s). Click a card to select it.
    </div>

    <div class="row g-2 up-list">
        @forelse ($users as $u)
            @php
                $uName = trim($u->name ?? '') ?: ($u->username ?? 'User');
                $initials = collect(preg_split('/\s+/', $uName))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
                $photo = $upAvatar($u);
            @endphp
            <div class="col-xl-4 col-lg-6 up-item" data-search="{{ \Illuminate\Support\Str::lower($uName . ' ' . $u->email . ' ' . $u->username) }}">
                <div class="user-card-sel" data-user-id="{{ $u->id }}">
                    <div class="usr-avatar-sm">@if ($photo)<img src="{{ $photo }}" alt="">@else{{ $initials }}@endif</div>
                    <div class="ucs-info">
                        <div class="ucs-name">{{ $uName }}</div>
                        <div class="ucs-meta">{{ $u->email ?: $u->username }}</div>
                        @if ($u->roles->isNotEmpty())
                            <div class="ucs-roles">@foreach ($u->roles as $r)<span>{{ $r->name }}</span>@endforeach</div>
                        @endif
                    </div>
                    <div class="ucs-check">
                        <input type="checkbox" value="{{ $u->id }}" name="users[]" class="user-checkbox up-check" aria-label="Select {{ $uName }}">
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="rol-info-banner mb-0">Everyone already has this role.</div></div>
        @endforelse
        <div class="col-12 up-nomatch" style="display:none;"><div class="rol-info-banner mb-0">No users match your search.</div></div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById(@json($pickerId));
    if (!root || root.dataset.bound) return;
    root.dataset.bound = '1';
    const checks = () => Array.from(root.querySelectorAll('.up-check'));
    const shownChecks = () => checks().filter(c => c.closest('.up-item').style.display !== 'none');

    function refresh() {
        root.querySelector('.up-count').textContent = checks().filter(c => c.checked).length;
        checks().forEach(c => c.closest('.user-card-sel').classList.toggle('selected', c.checked));
        const shown = shownChecks();
        root.querySelector('.up-all').checked = shown.length > 0 && shown.every(c => c.checked);
    }
    root.querySelectorAll('.user-card-sel').forEach(card => card.addEventListener('click', function (e) {
        if (e.target.type === 'checkbox') return;
        const cb = this.querySelector('.up-check');
        cb.checked = !cb.checked;
        refresh();
    }));
    checks().forEach(c => c.addEventListener('change', refresh));
    root.querySelector('.up-all').addEventListener('change', function () {
        shownChecks().forEach(c => c.checked = this.checked);
        refresh();
    });
    root.querySelector('.up-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let shown = 0;
        root.querySelectorAll('.up-item').forEach(it => {
            const hit = !q || it.dataset.search.includes(q);
            it.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        root.querySelector('.up-nomatch').style.display = shown || !checks().length ? 'none' : '';
        refresh();
    });

    const form = root.closest('form');
    if (form && !form.dataset.upGuard) {
        form.dataset.upGuard = '1';
        form.addEventListener('submit', function (e) {
            if (!checks().some(c => c.checked)) {
                e.preventDefault();
                if (window.Swal) Swal.fire({ icon: 'warning', title: 'No users selected', text: 'Please select at least one user.', confirmButtonColor: '#6366f1' });
                else alert('Please select at least one user.');
                return;
            }
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding…'; }
        });
    }
    const modal = root.closest('.modal');
    if (modal) modal.addEventListener('hidden.bs.modal', () => { checks().forEach(c => c.checked = false); root.querySelector('.up-search').value = ''; root.querySelector('.up-search').dispatchEvent(new Event('input')); });
    refresh();
})();
</script>
