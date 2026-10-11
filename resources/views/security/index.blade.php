@extends('layouts.master')

@section('content')
@php
    $dayBoxes = function ($name, $selected) use ($days) {
        $selected = array_map('intval', (array) ($selected ?: []));
        $out = '<div class="d-flex flex-wrap gap-1">';
        foreach ($days as $n => $d) {
            $id = $name . '_' . $n . '_' . uniqid();
            $out .= '<input type="checkbox" class="btn-check" name="' . $name . '[]" value="' . $n . '" id="' . $id . '" ' . (in_array($n, $selected, true) ? 'checked' : '') . '>'
                  . '<label class="btn btn-sm btn-outline-secondary px-2" for="' . $id . '">' . $d . '</label>';
        }
        return $out . '</div>';
    };
    $agent = function ($ua) {
        $ua = (string) $ua;
        $browser = str_contains($ua, 'Edg') ? 'Edge' : (str_contains($ua, 'Chrome') ? 'Chrome' : (str_contains($ua, 'Firefox') ? 'Firefox' : (str_contains($ua, 'Safari') ? 'Safari' : 'Browser')));
        $os = str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Android') ? 'Android' : (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') ? 'iOS' : (str_contains($ua, 'Mac') ? 'macOS' : (str_contains($ua, 'Linux') ? 'Linux' : ''))));
        return trim("$browser $os");
    };
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Login control & sessions</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Decide when staff can log in, end sessions, and set till rules. Super Admins are never locked out.</p>
        </div>
        @can('Force logout users')
            <form method="POST" action="{{ route('security.logout-all') }}" id="logoutAllForm" class="mt-2 mt-sm-0">@csrf
                <button type="button" class="btn btn-danger btn-sm" id="logoutAllBtn"><i class="bi bi-power me-1"></i>Log out everyone now</button>
            </form>
        @endcan
    </div>

    @include('layouts.partials.flash')

    <div class="row g-3">
        {{-- ================= LEFT ================= --}}
        <div class="col-xl-5">
            @can('Manage login control')
            <form class="card" method="POST" action="{{ route('security.settings') }}">
                @csrf <input type="hidden" name="section" value="login">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-clock me-1"></i>Store login hours</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="login_hours_enabled" value="1" id="lh" @checked($settings['login_hours_enabled'])>
                        <label class="form-check-label fw-semibold" for="lh">Only allow logins during these hours</label>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label fs-sm">From</label><input type="time" name="login_start_time" class="form-control" value="{{ $settings['login_start_time'] }}"></div>
                        <div class="col-6"><label class="form-label fs-sm">Until</label><input type="time" name="login_end_time" class="form-control" value="{{ $settings['login_end_time'] }}"></div>
                    </div>
                    <label class="form-label fs-sm">On these days</label>
                    {!! $dayBoxes('login_days', $settings['login_days']) !!}
                    <div class="form-text mb-3">Staff already logged in are signed out when the window closes. Role and user rules below override this.</div>

                    <hr>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="auto_logout_enabled" value="1" id="al" @checked($settings['auto_logout_enabled'])>
                        <label class="form-check-label fw-semibold" for="al">Log everyone out every day at</label>
                    </div>
                    <input type="time" name="auto_logout_time" class="form-control mb-3" style="max-width:160px" value="{{ $settings['auto_logout_time'] }}">

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fs-sm">Log out after inactivity</label>
                            <div class="input-group"><input type="number" min="0" name="idle_timeout_minutes" class="form-control" value="{{ $settings['idle_timeout_minutes'] }}"><span class="input-group-text">minutes</span></div>
                            <div class="form-text">0 = off</div>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="single_session" value="1" id="ss" @checked($settings['single_session'])>
                        <label class="form-check-label" for="ss">One device per user (new login ends the old one)</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="maintenance_lock" value="1" id="ml" @checked($settings['maintenance_lock'])>
                        <label class="form-check-label text-danger fw-semibold" for="ml">Lock the system – only Super Admins can log in</label>
                    </div>
                    <input name="maintenance_message" class="form-control form-control-sm" value="{{ $settings['maintenance_message'] }}" placeholder="Message shown to staff">
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Save login settings</button></div>
            </form>

            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-alarm me-1"></i>Scheduled logout (one-off)</h6></div>
                <div class="card-body">
                    @if ($settings['scheduled_logout_at'] && \Carbon\Carbon::parse($settings['scheduled_logout_at'])->isFuture())
                        <div class="alert alert-warning d-flex align-items-center mb-0">
                            <span class="me-auto">Everyone will be logged out at <strong>{{ \Carbon\Carbon::parse($settings['scheduled_logout_at'])->format('d M Y, h:i A') }}</strong>.</span>
                            <form method="POST" action="{{ route('security.schedule-logout') }}">@csrf<input type="hidden" name="clear" value="1"><button class="btn btn-sm btn-light">Cancel</button></form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('security.schedule-logout') }}" class="d-flex gap-2">@csrf
                            <input type="datetime-local" name="scheduled_logout_at" class="form-control" required min="{{ now()->format('Y-m-d\TH:i') }}">
                            <button class="btn btn-outline-primary text-nowrap">Schedule</button>
                        </form>
                        <div class="form-text">E.g. before a stock-take or system update.</div>
                    @endif
                </div>
            </div>

            <form class="card" method="POST" action="{{ route('security.settings') }}">
                @csrf <input type="hidden" name="section" value="operations">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-shop me-1"></i>Till & stock rules</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="adjustment_requires_approval" value="1" id="o1" @checked($settings['adjustment_requires_approval'])><label class="form-check-label" for="o1">Stock adjustments need a supervisor's approval</label></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="block_sale_below_cost" value="1" id="o2" @checked($settings['block_sale_below_cost'])><label class="form-check-label" for="o2">Block selling below cost (supervisor PIN to override)</label></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="require_open_shift" value="1" id="o3" @checked($settings['require_open_shift'])><label class="form-check-label" for="o3">Cashiers must open a shift (cash float) before selling</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="allow_negative_stock" value="1" id="o4" @checked($settings['allow_negative_stock'])><label class="form-check-label" for="o4">Allow selling when the system shows no stock</label></div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-sm">Max discount without approval</label>
                            <div class="input-group"><input type="number" step="0.5" min="0" max="100" name="max_discount_percent" class="form-control" value="{{ $settings['max_discount_percent'] }}"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-sm">In-store barcode prefix</label>
                            <input name="barcode_prefix" class="form-control" value="{{ $settings['barcode_prefix'] }}" maxlength="3">
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="scale_barcode_enabled" value="1" id="o5" @checked($settings['scale_barcode_enabled'])><label class="form-check-label fs-sm" for="o5">Read scale labels (21/22…)</label></div>
                        </div>
                        <div class="col-6">
                            <select name="scale_barcode_mode" class="form-select form-select-sm mt-1">
                                <option value="weight" @selected($settings['scale_barcode_mode'] === 'weight')>Scale prints weight</option>
                                <option value="price" @selected($settings['scale_barcode_mode'] === 'price')>Scale prints price</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Save rules</button></div>
            </form>
            @endcan
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex align-items-center"><h6 class="card-title mb-0 flex-grow-1"><i class="bi bi-broadcast me-1"></i>Active sessions <span class="badge bg-success-subtle text-success ms-1">{{ $sessions->count() }}</span></h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>User</th><th>Device</th><th>IP</th><th>Last activity</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($sessions as $s)
                            <tr>
                                <td>{{ trim($s->first_name . ' ' . $s->last_name) }} @if($s->id === $currentSession)<span class="badge bg-primary-subtle text-primary">you</span>@endif
                                    <div class="fs-xs text-muted">{{ $s->email }}</div></td>
                                <td class="fs-sm">{{ $agent($s->user_agent) }}</td>
                                <td class="fs-sm vm-mono">{{ $s->ip_address }}</td>
                                <td class="fs-sm">{{ \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans() }}</td>
                                <td class="text-end">
                                    @if($s->id !== $currentSession)
                                        @can('Force logout users')
                                            <form method="POST" action="{{ route('security.end-session', $s->id) }}" class="d-inline confirm-form" data-msg="End this session?">@csrf<button class="btn btn-sm btn-light text-danger">End</button></form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nobody else is logged in.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @can('Manage login control')
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-people me-1"></i>Login hours by role</h6></div>
                <div class="card-body">
                    @foreach ($roles as $role)
                        @php $rr = $roleRules[$role->id] ?? null; @endphp
                        <form method="POST" action="{{ route('security.role-rule', $role) }}" class="row g-2 align-items-center border-bottom pb-2 mb-2">@csrf
                            <div class="col-md-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="r{{ $role->id }}" @checked($rr?->enabled) @disabled($role->name === 'Super Admin')>
                                    <label class="form-check-label fw-semibold" for="r{{ $role->id }}">{{ $role->name }}</label>
                                </div>
                                @if($role->name === 'Super Admin')<div class="fs-xs text-muted">Always allowed</div>@endif
                            </div>
                            @if ($role->name !== 'Super Admin')
                                <div class="col-6 col-md-2"><input type="time" name="start_time" class="form-control form-control-sm" value="{{ $rr?->start_time ?? '08:00' }}"></div>
                                <div class="col-6 col-md-2"><input type="time" name="end_time" class="form-control form-control-sm" value="{{ $rr?->end_time ?? '20:00' }}"></div>
                                <div class="col-md-4">{!! $dayBoxes('days', $rr?->days ?? [1, 2, 3, 4, 5, 6]) !!}</div>
                                <div class="col-md-1 text-end"><button class="btn btn-sm btn-light">Save</button></div>
                            @endif
                        </form>
                    @endforeach
                </div>
            </div>
            @endcan

            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-person-gear me-1"></i>Staff accounts</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Staff</th><th>Login rule</th><th>Last login</th><th class="text-center">PIN</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($users as $u)
                            @php $isOnline = in_array($u->id, $onlineIds); @endphp
                            <tr>
                                <td>
                                    <i class="bi bi-circle-fill fs-xs {{ $isOnline ? 'text-success' : 'text-muted opacity-25' }}"></i>
                                    {{ $u->first_name }} {{ $u->last_name }}
                                    @if(!$u->is_active)<span class="badge bg-danger">disabled</span>@endif
                                    <div class="fs-xs text-muted">{{ $u->roles->pluck('name')->join(', ') }}</div>
                                </td>
                                <td class="fs-sm">
                                    @switch($u->login_rule)
                                        @case('always') <span class="badge bg-success-subtle text-success">Any time</span> @break
                                        @case('blocked') <span class="badge bg-danger-subtle text-danger">Blocked</span> @break
                                        @case('custom') <span class="badge bg-info-subtle text-info">{{ $u->login_start_time }}–{{ $u->login_end_time }}</span> @break
                                        @default <span class="text-muted">Store / role hours</span>
                                    @endswitch
                                    @if($u->max_discount_percent !== null)<div class="fs-xs text-muted">Max discount {{ (float) $u->max_discount_percent }}%</div>@endif
                                </td>
                                <td class="fs-sm">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}<div class="fs-xs text-muted vm-mono">{{ $u->last_login_ip }}</div></td>
                                <td class="text-center">{!! $u->pos_pin ? '<i class="bi bi-check-circle text-success" title="Supervisor PIN set"></i>' : '<span class="text-muted">—</span>' !!}
                                    @if($u->two_factor_confirmed_at)<i class="ri-shield-keyhole-line text-primary ms-1" title="Two-step sign-in on"></i>@endif</td>
                                <td class="text-end text-nowrap">
                                    @can('Manage login control')
                                        @php $uData = ['id' => $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'is_active' => (bool) $u->is_active, 'login_rule' => $u->login_rule ?? 'inherit', 'start' => $u->login_start_time, 'end' => $u->login_end_time, 'days' => $u->login_days ?? [], 'max' => $u->max_discount_percent, 'pin' => (bool) $u->pos_pin]; @endphp
                                        <button class="btn btn-sm btn-light edit-user" data-user="{{ json_encode($uData) }}">Edit</button>
                                    @endcan
                                    @if($u->two_factor_confirmed_at && $u->id !== auth()->id() && auth()->user()->hasRole('Super Admin'))
                                        <form method="POST" action="{{ route('mfa.reset', $u) }}" class="d-inline confirm-form" data-msg="Reset two-step sign-in for {{ $u->first_name }}? They will be signed out and must set it up again.">@csrf<button class="btn btn-sm btn-light" title="Reset two-step sign-in">Reset 2-step</button></form>
                                    @endif
                                    @if($isOnline && $u->id !== auth()->id())
                                        @can('Force logout users')
                                            <form method="POST" action="{{ route('security.logout-user', $u) }}" class="d-inline confirm-form" data-msg="Log {{ $u->first_name }} out now?">@csrf<button class="btn btn-sm btn-light text-danger">Log out</button></form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex"><h6 class="card-title mb-0 flex-grow-1"><i class="bi bi-journal-text me-1"></i>Recent sign-in activity</h6>@can('View audit trail')<a href="{{ route('audit.index', ['module' => 'security']) }}" class="fs-sm">All</a>@endcan</div>
                <div class="list-group list-group-flush">
                    @forelse ($recentEvents as $ev)
                        <div class="list-group-item d-flex gap-2 fs-sm">
                            <span class="badge bg-{{ $ev->event_color }}-subtle text-{{ $ev->event_color }} align-self-start">{{ str_replace('_', ' ', $ev->event) }}</span>
                            <span class="flex-grow-1">{{ $ev->user_name ?: ($ev->auditable_label ?: 'Unknown') }} – {{ $ev->description }}</span>
                            <span class="text-muted text-nowrap">{{ $ev->ip_address }} · {{ $ev->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="list-group-item text-muted text-center">No activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>

{{-- Edit user modal --}}
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="userForm">@csrf
                <div class="modal-header"><h5 class="modal-title">Login settings – <span id="uName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="uActive"><label class="form-check-label" for="uActive">Account active</label></div>
                    <label class="form-label">When can this person log in?</label>
                    <select name="login_rule" id="uRule" class="form-select mb-2">
                        <option value="inherit">Follow store / role hours</option>
                        <option value="custom">Custom hours</option>
                        <option value="always">Any time</option>
                        <option value="blocked">Never (blocked)</option>
                    </select>
                    <div id="uCustom" class="border rounded p-2 mb-3">
                        <div class="row g-2 mb-2"><div class="col-6"><input type="time" name="login_start_time" id="uStart" class="form-control form-control-sm"></div><div class="col-6"><input type="time" name="login_end_time" id="uEnd" class="form-control form-control-sm"></div></div>
                        <div id="uDays"></div>
                    </div>
                    <label class="form-label">Max discount without approval</label>
                    <div class="input-group mb-1" style="max-width:200px"><input type="number" step="0.5" min="0" max="100" name="max_discount_percent" id="uMax" class="form-control" placeholder="store default"><span class="input-group-text">%</span></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
            <hr class="m-0">
            <form method="POST" id="pinForm" class="p-3 d-flex gap-2 align-items-end">@csrf
                <div class="flex-grow-1">
                    <label class="form-label mb-1">Supervisor PIN <span class="text-muted fs-xs" id="pinState"></span></label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="6" pattern="\d{4,6}" class="form-control" placeholder="4–6 digits (leave blank to remove)" autocomplete="new-password">
                    <div class="form-text">Used at the till to approve discounts, price changes, voids and refunds.</div>
                </div>
                <button class="btn btn-outline-primary mb-4">Save PIN</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const days = @json($days);
    document.getElementById('logoutAllBtn')?.addEventListener('click', async () => {
        if (await VM.confirm('Log out everyone?', 'Every user except you will be signed out immediately, including "remember me" logins.', 'Log everyone out', 'error'))
            document.getElementById('logoutAllForm').submit();
    });
    document.querySelectorAll('.confirm-form').forEach(f => f.addEventListener('submit', async e => {
        e.preventDefault(); if (await VM.confirm(f.dataset.msg)) f.submit();
    }));
    const modal = new bootstrap.Modal('#userModal');
    const rule = document.getElementById('uRule');
    rule.addEventListener('change', () => document.getElementById('uCustom').style.display = rule.value === 'custom' ? '' : 'none');
    document.querySelectorAll('.edit-user').forEach(b => b.addEventListener('click', () => {
        const u = JSON.parse(b.dataset.user);
        document.getElementById('uName').textContent = u.name;
        document.getElementById('userForm').action = '/security/users/' + u.id;
        document.getElementById('pinForm').action = '/security/users/' + u.id + '/pin';
        document.getElementById('pinState').textContent = u.pin ? '(set)' : '(not set)';
        document.getElementById('uActive').checked = u.is_active;
        rule.value = u.login_rule; rule.dispatchEvent(new Event('change'));
        document.getElementById('uStart').value = u.start || '08:00';
        document.getElementById('uEnd').value = u.end || '18:00';
        document.getElementById('uMax').value = u.max ?? '';
        document.getElementById('uDays').innerHTML = '<div class="d-flex flex-wrap gap-1">' + Object.entries(days).map(([n, d]) =>
            `<input type="checkbox" class="btn-check" name="login_days[]" value="${n}" id="ud${n}" ${(u.days || []).map(Number).includes(Number(n)) || !(u.days || []).length ? 'checked' : ''}><label class="btn btn-sm btn-outline-secondary px-2" for="ud${n}">${d}</label>`).join('') + '</div>';
        modal.show();
    }));
})();
</script>
@endpush
