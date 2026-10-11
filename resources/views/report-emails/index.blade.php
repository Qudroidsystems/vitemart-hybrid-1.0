@extends('layouts.master')
@section('content')
@php
    $presets = [
        ['End of day', 'bi-moon-stars', ['name' => 'End of day sales', 'frequency' => 'daily', 'send_time' => '22:00', 'period' => 'today']],
        ['Next morning', 'bi-sunrise', ['name' => 'Yesterday\'s sales', 'frequency' => 'daily', 'send_time' => '07:00', 'period' => 'yesterday']],
        ['Every shift', 'bi-arrow-repeat', ['name' => 'Shift sales', 'frequency' => 'hourly', 'every_hours' => 8, 'send_time' => '06:00', 'period' => 'last_n_hours']],
        ['Weekly', 'bi-calendar-week', ['name' => 'Weekly sales', 'frequency' => 'weekly', 'weekday' => 1, 'send_time' => '07:00', 'period' => 'previous_week']],
        ['Monthly', 'bi-calendar-month', ['name' => 'Monthly sales', 'frequency' => 'monthly', 'month_day' => 1, 'send_time' => '07:00', 'period' => 'previous_month']],
    ];
    $statusBadge = ['sent' => ['#e7f6ef', '#0f7a4a', 'Sent'], 'skipped' => ['#f1f3f7', '#64748b', 'Skipped'], 'failed' => ['#fde8e8', '#b42318', 'Failed']];
@endphp
<style>
.rp-page { --rp-accent:#0f766e; --rp-accent2:#14b8a6; --rp-ink:var(--cb-heading,#0f172a); --rp-muted:var(--cb-muted,#64748b); --rp-border:var(--cb-border,#e2e8f0); --rp-surface:var(--cb-surface,#fff); --rp-surface2:var(--cb-surface-2,#f8fafc); --rp-soft:#ecfdf5; font-family:'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; }
[data-bs-theme="dark"] .rp-page { --rp-soft:#0f2e2a; }
.rp-hero { background:linear-gradient(135deg,#0f172a 0%,#134e4a 55%,#0f766e 100%); border-radius:14px; padding:26px 30px; margin-bottom:20px; color:#fff; position:relative; overflow:hidden; animation:rpDown .45s cubic-bezier(.22,1,.36,1); }
.rp-hero::after { content:''; position:absolute; right:-60px; top:-60px; width:220px; height:220px; border-radius:50%; background:rgba(20,184,166,.18); }
.rp-hero h1 { font-size:22px; font-weight:800; margin:0 0 4px; color:#fff; position:relative; z-index:1; }
.rp-hero p { margin:0; color:rgba(255,255,255,.7); font-size:13px; position:relative; z-index:1; max-width:640px; }
.rp-hero .rp-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; position:relative; z-index:1; }
.rp-btn { display:inline-flex; align-items:center; gap:7px; border:0; border-radius:10px; padding:9px 16px; font-size:13px; font-weight:700; cursor:pointer; transition:transform .15s,box-shadow .15s; text-decoration:none; white-space:nowrap; }
.rp-btn:disabled { opacity:.55; cursor:not-allowed; }
.rp-btn-primary { background:linear-gradient(135deg,var(--rp-accent),var(--rp-accent2)); color:#fff; box-shadow:0 3px 12px rgba(15,118,110,.3); }
.rp-btn-primary:hover:not(:disabled) { transform:translateY(-1px); color:#fff; }
.rp-btn-white { background:#fff; color:#0f172a; }
.rp-btn-light { background:var(--rp-surface); color:var(--rp-ink); border:1.5px solid var(--rp-border); }
.rp-btn-light:hover { background:var(--rp-surface2); color:var(--rp-ink); }
.rp-btn-sm { padding:6px 11px; font-size:12.5px; }
.rp-btn .spinner-border { width:14px; height:14px; border-width:2px; }
.rp-banner { border-radius:12px; padding:12px 16px; font-size:13px; margin-bottom:16px; display:flex; gap:10px; align-items:flex-start; }
.rp-banner.warn { background:#fff7e6; border:1px solid #fde3a7; color:#7a4a00; }
.rp-banner i { font-size:17px; }
.rp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px; margin-bottom:22px; }
.rp-card { background:var(--rp-surface); border:1px solid var(--rp-border); border-radius:14px; box-shadow:0 4px 20px rgba(15,23,42,.05); display:flex; flex-direction:column; animation:rpUp .35s ease-out both; }
.rp-card.paused { opacity:.72; }
.rp-card-h { padding:16px 18px 10px; display:flex; gap:12px; align-items:flex-start; }
.rp-ico { width:40px; height:40px; flex:none; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; background:linear-gradient(135deg,var(--rp-accent),var(--rp-accent2)); }
.rp-card.paused .rp-ico { background:#94a3b8; }
.rp-card h5 { margin:0; font-size:15.5px; font-weight:800; color:var(--rp-ink); }
.rp-when { font-size:12.5px; color:var(--rp-muted); margin-top:2px; }
.rp-card-b { padding:0 18px 12px; font-size:13px; flex:1; }
.rp-row { display:flex; gap:8px; padding:6px 0; border-top:1px dashed var(--rp-border); }
.rp-row:first-child { border-top:0; }
.rp-row > span:first-child { color:var(--rp-muted); width:84px; flex:none; font-size:12px; padding-top:1px; }
.rp-row b { font-weight:700; color:var(--rp-ink); word-break:break-word; }
.rp-chip { display:inline-block; font-size:11px; font-weight:700; border-radius:999px; padding:1px 8px; margin:0 4px 3px 0; background:var(--rp-surface2); border:1px solid var(--rp-border); color:var(--rp-ink); }
.rp-badge { font-size:11px; font-weight:800; border-radius:999px; padding:2px 8px; }
.rp-card-f { padding:10px 14px; border-top:1px solid var(--rp-border); background:var(--rp-surface2); border-radius:0 0 14px 14px; display:flex; gap:6px; flex-wrap:wrap; }
.rp-empty { text-align:center; padding:40px 20px; background:var(--rp-surface); border:1.5px dashed var(--rp-border); border-radius:14px; margin-bottom:22px; }
.rp-empty i { font-size:40px; color:var(--rp-accent); }
.rp-presets { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; }
.rp-preset { border:1.5px solid var(--rp-border); background:var(--rp-surface); border-radius:12px; padding:10px; text-align:left; font-size:13px; font-weight:700; color:var(--rp-ink); transition:border-color .15s,transform .15s; }
.rp-preset i { display:block; font-size:18px; color:var(--rp-accent); margin-bottom:4px; }
.rp-preset:hover, .rp-preset.active { border-color:var(--rp-accent); background:var(--rp-soft); transform:translateY(-1px); }
.rp-sec { background:var(--rp-surface); border:1px solid var(--rp-border); border-radius:14px; box-shadow:0 4px 20px rgba(15,23,42,.05); }
.rp-sec-h { padding:16px 20px; border-bottom:1px solid var(--rp-border); display:flex; gap:12px; align-items:center; }
.rp-sec-h h5 { margin:0; font-size:15.5px; font-weight:800; }
.rp-sec-h p { margin:2px 0 0; font-size:12.5px; color:var(--rp-muted); }
.rp-sec-b { padding:18px 20px; }
.rp-label { font-size:12.5px; font-weight:700; color:var(--rp-ink); margin-bottom:5px; display:block; }
.rp-help { font-size:12px; color:var(--rp-muted); margin-top:4px; }
.rp-page .form-control, .rp-page .form-select, #schedModal .form-control, #schedModal .form-select { border-radius:10px; min-height:42px; }
.rp-ranges { display:flex; flex-wrap:wrap; gap:6px; }
.rp-ranges button { border:1.5px solid var(--rp-border); background:var(--rp-surface); border-radius:999px; padding:5px 12px; font-size:12.5px; font-weight:600; color:var(--rp-ink); }
.rp-ranges button.active, .rp-ranges button:hover { border-color:var(--rp-accent); color:var(--rp-accent); background:var(--rp-soft); }
.rp-checks { display:flex; flex-wrap:wrap; gap:6px 18px; font-size:13px; }
.rp-checks label { display:flex; align-items:center; gap:7px; cursor:pointer; margin:0; }
.rp-result { display:none; margin-top:12px; border-radius:10px; padding:10px 14px; font-size:13px; }
.rp-result.ok { display:block; background:#e7f6ef; color:#0f5f3c; border:1px solid #bfe8d3; }
.rp-result.bad { display:block; background:#fdecec; color:#8f1d1d; border:1px solid #f6c9c9; }
#schedModal .modal-content { border:0; border-radius:16px; }
#schedModal .modal-header { border-bottom:1px solid var(--rp-border,#e2e8f0); }
@keyframes rpDown { from{opacity:0;transform:translateY(-14px)} to{opacity:1;transform:none} }
@keyframes rpUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@media (prefers-reduced-motion: reduce) { .rp-hero, .rp-card { animation:none; } }
@media (max-width: 575px) { .rp-hero { padding:22px 18px; } .rp-grid { grid-template-columns:1fr; } }
</style>

<div class="main-content">
<div class="page-content">
<div class="container-fluid rp-page">

    <div class="rp-hero">
        <h1><i class="bi bi-envelope-paper me-2"></i>Sales Report Emails</h1>
        <p>Email the day's takings – every cashier's sales, payment methods, refunds, voids, cash counts and the total – automatically, to whoever needs them.</p>
        <div class="rp-actions">
            <button class="rp-btn rp-btn-white" type="button" data-new><i class="bi bi-plus-lg"></i>New report email</button>
            <a class="rp-btn rp-btn-light" href="#oneOff" style="background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.25)"><i class="bi bi-calendar-range"></i>Report for any dates</a>
        </div>
    </div>

    @if ($mailer === 'log')
        <div class="rp-banner warn"><i class="bi bi-exclamation-triangle-fill"></i><div><b>Email isn't set up yet</b> – reports will be written to the log instead of being sent.
            @if (Route::has('system-config.index'))<a href="{{ route('system-config.index') }}#email">Set up email in System Configuration</a>.@else Set MAIL_* in the .env file.@endif</div></div>
    @endif
    @if ($schedules->where('is_active', true)->isNotEmpty() && !$schedulerOk)
        <div class="rp-banner warn"><i class="bi bi-clock-history"></i><div><b>The scheduler isn't running</b>{{ $lastTick ? ' (last ran ' . \Carbon\Carbon::parse($lastTick)->diffForHumans() . ')' : '' }}, so scheduled reports won't go out on their own.
            @if (Route::has('system-config.index'))<a href="{{ route('system-config.index') }}#overview">See how to start it</a>.@endif Reports that were missed are sent once it starts.</div></div>
    @endif

    {{-- ===================== SCHEDULES ===================== --}}
    @if ($schedules->isEmpty())
        <div class="rp-empty">
            <i class="bi bi-envelope-paper-heart"></i>
            <h5 class="mt-2 mb-1 fw-bold">No report emails yet</h5>
            <p class="text-muted mb-3" style="font-size:13px">Start with the end-of-day report – it goes out every evening with every cashier's totals.</p>
            <button class="rp-btn rp-btn-primary" type="button" data-new data-preset="0"><i class="bi bi-moon-stars"></i>Set up the end-of-day report</button>
        </div>
    @else
        <div class="rp-grid">
            @foreach ($schedules as $i => $sc)
                <div class="rp-card {{ $sc->is_active ? '' : 'paused' }}" style="animation-delay:{{ min($i, 8) * 40 }}ms">
                    <div class="rp-card-h">
                        <div class="rp-ico"><i class="bi {{ ['daily' => 'bi-moon-stars', 'weekly' => 'bi-calendar-week', 'monthly' => 'bi-calendar-month', 'hourly' => 'bi-arrow-repeat'][$sc->frequency] ?? 'bi-envelope' }}"></i></div>
                        <div style="min-width:0;flex:1">
                            <h5>{{ $sc->name }}</h5>
                            <div class="rp-when">{{ $sc->describe() }}</div>
                        </div>
                        @if (!$sc->is_active)<span class="rp-badge" style="background:#f1f3f7;color:#64748b">Paused</span>@endif
                    </div>
                    <div class="rp-card-b">
                        <div class="rp-row"><span>Covers</span><b>{{ $sc->periodLabel() }}</b></div>
                        <div class="rp-row"><span>Sent to</span><div>@foreach ($sc->recipients ?? [] as $e)<span class="rp-chip">{{ $e }}</span>@endforeach</div></div>
                        <div class="rp-row"><span>Next</span><b>{{ $sc->is_active && $sc->next_run_at ? $sc->next_run_at->format('D j M, g:i A') : '—' }}</b></div>
                        <div class="rp-row"><span>Last</span>
                            <div>
                                @if ($sc->last_status)
                                    @php $b = $statusBadge[$sc->last_status] ?? $statusBadge['skipped']; @endphp
                                    <span class="rp-badge" style="background:{{ $b[0] }};color:{{ $b[1] }}">{{ $b[2] }}</span>
                                    <span class="text-muted" style="font-size:12px">{{ $sc->last_sent_at?->diffForHumans() ?? $sc->updated_at->diffForHumans() }}</span>
                                    @if ($sc->last_status !== 'sent' && $sc->last_message)<div style="font-size:12px;color:{{ $sc->last_status === 'failed' ? '#b42318' : 'var(--rp-muted)' }}">{{ \Illuminate\Support\Str::limit($sc->last_message, 160) }}</div>@endif
                                @else
                                    <span class="text-muted">Not sent yet</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="rp-card-f">
                        <button class="rp-btn rp-btn-light rp-btn-sm" type="button" data-edit='@json($sc)'><i class="bi bi-pencil"></i>Edit</button>
                        <a class="rp-btn rp-btn-light rp-btn-sm" href="{{ route('report-emails.preview-schedule', $sc) }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i>Preview</a>
                        <button class="rp-btn rp-btn-light rp-btn-sm" type="button" data-post="{{ route('report-emails.send', $sc) }}" data-confirm="Send this report now to {{ implode(', ', $sc->recipients ?? []) }}?"><i class="bi bi-send"></i>Send now</button>
                        <button class="rp-btn rp-btn-light rp-btn-sm" type="button" data-post="{{ route('report-emails.toggle', $sc) }}"><i class="bi {{ $sc->is_active ? 'bi-pause' : 'bi-play' }}"></i>{{ $sc->is_active ? 'Pause' : 'Resume' }}</button>
                        <button class="rp-btn rp-btn-light rp-btn-sm ms-auto text-danger" type="button" data-delete="{{ route('report-emails.destroy', $sc) }}" data-name="{{ $sc->name }}" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===================== ONE-OFF ===================== --}}
    <form class="rp-sec" id="oneOff" autocomplete="off">
        <div class="rp-sec-h"><div class="rp-ico"><i class="bi bi-calendar-range"></i></div><div><h5>Report for any dates</h5><p>See, download or email the sales for any period – for example last Friday, or one cashier's long shift.</p></div></div>
        <div class="rp-sec-b">
            <div class="rp-ranges mb-3" id="ranges">
                <button type="button" data-range="today" class="active">Today</button>
                <button type="button" data-range="yesterday">Yesterday</button>
                <button type="button" data-range="week">This week</button>
                <button type="button" data-range="lastweek">Last week</button>
                <button type="button" data-range="month">This month</button>
                <button type="button" data-range="lastmonth">Last month</button>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3"><label class="rp-label">From</label><input type="datetime-local" class="form-control" name="from" required></div>
                <div class="col-sm-6 col-lg-3"><label class="rp-label">To</label><input type="datetime-local" class="form-control" name="to" required></div>
                <div class="col-lg-6"><label class="rp-label">Email to</label><input class="form-control" name="recipients" value="{{ $me }}" placeholder="owner@shop.com, accountant@shop.com">
                    <div class="rp-help">Separate several addresses with commas.</div></div>
                @if ($branches->isNotEmpty())
                    <div class="col-lg-6"><label class="rp-label">Branches</label>
                        <select class="form-select" name="branch_ids[]" multiple size="3">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->code }} – {{ $b->name }}</option>@endforeach</select>
                        <div class="rp-help">None selected = all branches.</div></div>
                @endif
                <div class="col-12 rp-checks">
                    <label><input type="checkbox" class="form-check-input mt-0" name="include_transactions" value="1" checked>Every transaction + spreadsheet</label>
                    <label><input type="checkbox" class="form-check-input mt-0" name="include_shifts" value="1" checked>Shifts &amp; cash count</label>
                    <label><input type="checkbox" class="form-check-input mt-0" name="include_products" value="1" checked>Best sellers</label>
                    <label><input type="checkbox" class="form-check-input mt-0" name="include_profit" value="1">Cost &amp; profit</label>
                    <label><input type="checkbox" class="form-check-input mt-0" name="channels" value="all">Include online orders</label>
                </div>
            </div>
            <div class="rp-result"></div>
        </div>
        <div class="rp-card-f" style="padding:14px 20px">
            <button class="rp-btn rp-btn-light" type="button" id="ooPreview"><i class="bi bi-eye"></i>Preview</button>
            <button class="rp-btn rp-btn-light" type="button" id="ooDownload"><i class="bi bi-file-earmark-spreadsheet"></i>Download spreadsheet</button>
            <button class="rp-btn rp-btn-primary ms-auto" type="submit"><i class="bi bi-send"></i>Email it now</button>
        </div>
    </form>
</div>
</div>
</div>

{{-- ===================== CREATE / EDIT MODAL ===================== --}}
<div class="modal fade" id="schedModal" tabindex="-1" aria-labelledby="schedTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rp-page" id="schedForm" autocomplete="off">
            <div class="modal-header"><h5 class="modal-title fw-bold" id="schedTitle">New report email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div id="presetWrap" class="mb-3">
                    <label class="rp-label">Start from</label>
                    <div class="rp-presets">
                        @foreach ($presets as $i => $p)
                            <button type="button" class="rp-preset" data-preset-i="{{ $i }}"><i class="bi {{ $p[1] }}"></i>{{ $p[0] }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="rp-label">Name</label><input class="form-control" name="name" required maxlength="80" placeholder="End of day sales"></div>
                    <div class="col-md-6"><label class="rp-label">Send to</label><input class="form-control" name="recipients" required placeholder="owner@shop.com, manager@shop.com">
                        <div class="rp-help">Separate several addresses with commas.</div></div>
                    <div class="col-md-4"><label class="rp-label">How often</label>
                        <select class="form-select" name="frequency">@foreach (\App\Models\ReportSchedule::FREQUENCIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-4" data-f="hourly"><label class="rp-label">Every</label>
                        <select class="form-select" name="every_hours">@foreach ([2, 3, 4, 6, 8, 12] as $h)<option value="{{ $h }}">{{ $h }} hours</option>@endforeach</select></div>
                    <div class="col-md-4" data-f="weekly"><label class="rp-label">On</label>
                        <select class="form-select" name="weekday">@foreach (\App\Models\ReportSchedule::WEEKDAYS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-4" data-f="monthly"><label class="rp-label">On day</label>
                        <select class="form-select" name="month_day">@for ($d = 1; $d <= 28; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor<option value="31">Last day of the month</option></select></div>
                    <div class="col-md-4"><label class="rp-label" id="timeLabel">At</label><input type="time" class="form-control" name="send_time" value="22:00" required></div>
                    <div class="col-md-8"><label class="rp-label">Each report covers</label>
                        <select class="form-select" name="period">@foreach (\App\Models\ReportSchedule::PERIODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <div class="rp-help" id="periodHelp"></div></div>
                    @if ($branches->isNotEmpty())
                        <div class="col-md-6"><label class="rp-label">Branches</label>
                            <select class="form-select" name="branch_ids[]" multiple size="3">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->code }} – {{ $b->name }}</option>@endforeach</select>
                            <div class="rp-help">None selected = all branches together.</div></div>
                    @endif
                    <div class="col-md-6"><label class="rp-label">Sales to include</label>
                        <select class="form-select" name="channels"><option value="pos">Till (POS) sales</option><option value="all">Till and online orders</option></select></div>
                    <div class="col-12"><label class="rp-label">In the email</label>
                        <div class="rp-checks">
                            <label><input type="checkbox" class="form-check-input mt-0" name="include_transactions" value="1" checked>Every transaction + spreadsheet</label>
                            <label><input type="checkbox" class="form-check-input mt-0" name="include_shifts" value="1" checked>Shifts &amp; cash count</label>
                            <label><input type="checkbox" class="form-check-input mt-0" name="include_products" value="1" checked>Best sellers</label>
                            <label><input type="checkbox" class="form-check-input mt-0" name="include_profit" value="1">Cost &amp; profit</label>
                        </div>
                        <div class="rp-help">Totals, payment methods, each cashier's sales, refunds and voids are always included.</div></div>
                    <div class="col-12 rp-checks">
                        <label><input type="checkbox" class="form-check-input mt-0" name="skip_if_empty" value="1">Don't send when there were no sales</label>
                        <label><input type="checkbox" class="form-check-input mt-0" name="is_active" value="1" checked>Active</label>
                    </div>
                </div>
                <div class="rp-result"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rp-btn rp-btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="rp-btn rp-btn-primary"><i class="bi bi-check2-circle"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const CSRF = @json(csrf_token());
    const R = {
        store: @json(route('report-emails.store')),
        update: @json(route('report-emails.update', '__ID__')),
        sendOnce: @json(route('report-emails.send-once')),
        preview: @json(route('report-emails.preview')),
        download: @json(route('report-emails.download')),
    };
    const PRESETS = @json(array_map(fn ($p) => $p[2], $presets));
    const PERIOD_HINT = {
        daily: ['today', 'yesterday', 'last_24_hours'], hourly: ['last_n_hours', 'today'],
        weekly: ['previous_week', 'last_7_days'], monthly: ['previous_month', 'this_month'],
    };
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));

    async function req(url, method, body) {
        const res = await fetch(url, {method, headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body, credentials: 'same-origin'});
        let d = {}; try { d = await res.json(); } catch (e) {}
        if (!res.ok || d.success === false) {
            const first = d.errors ? Object.values(d.errors)[0][0] : null;
            throw new Error(first || d.message || ('Request failed (' + res.status + ')'));
        }
        return d;
    }
    function result(scope, ok, msg) { const r = $('.rp-result', scope); r.className = 'rp-result ' + (ok ? 'ok' : 'bad'); r.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' me-1"></i>' + esc(msg); }
    function busy(btn, on) { if (on) { btn.dataset.h = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border"></span>' + btn.textContent.trim(); } else { btn.disabled = false; btn.innerHTML = btn.dataset.h; } }
    async function ask(text) {
        if (window.Swal) return (await Swal.fire({icon: 'question', text, showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: '#0f766e'})).isConfirmed;
        return confirm(text);
    }
    function toast(msg, ok = true) {
        if (window.Swal) Swal.fire({toast: true, position: 'top-end', icon: ok ? 'success' : 'error', title: msg, showConfirmButton: false, timer: 3500});
        else alert(msg);
    }

    // ---------------- schedule modal
    const modalEl = $('#schedModal'), form = $('#schedForm');
    const modal = () => bootstrap.Modal.getOrCreateInstance(modalEl);
    let editingId = null;
    function syncFreq() {
        const f = form.elements.frequency.value;
        $$('[data-f]', form).forEach(el => el.style.display = el.dataset.f === f ? '' : 'none');
        $('#timeLabel').textContent = f === 'hourly' ? 'Starting at' : 'At';
        const hint = PERIOD_HINT[f] || [];
        $$('option', form.elements.period).forEach(o => o.textContent = o.textContent.replace(/ ★$/, '') + (hint[0] === o.value ? ' ★' : ''));
        $('#periodHelp').textContent = hint.length ? '★ = usual choice for this schedule' : '';
    }
    form.elements.frequency.addEventListener('change', () => {
        const hint = PERIOD_HINT[form.elements.frequency.value];
        if (hint && !hint.includes(form.elements.period.value)) form.elements.period.value = hint[0];
        syncFreq();
    });
    function fill(d) {
        const set = (n, v) => { const el = form.elements[n]; if (el && v !== undefined && v !== null) el.value = v; };
        ['name', 'frequency', 'every_hours', 'send_time', 'weekday', 'month_day', 'period', 'channels'].forEach(k => set(k, d[k]));
        if (Array.isArray(d.recipients)) set('recipients', d.recipients.join(', '));
        ['include_transactions', 'include_shifts', 'include_products', 'include_profit', 'skip_if_empty', 'is_active'].forEach(k => { if (k in d) form.elements[k].checked = !!d[k]; });
        const sel = form.elements['branch_ids[]'];
        if (sel) $$('option', sel).forEach(o => o.selected = (d.branch_ids || []).map(String).includes(o.value));
        syncFreq();
    }
    function openNew(preset) {
        editingId = null;
        form.reset();
        $('#schedTitle').textContent = 'New report email';
        $('#presetWrap').style.display = '';
        $$('.rp-preset').forEach(b => b.classList.remove('active'));
        fill({recipients: [@json($me)], include_transactions: 1, include_shifts: 1, include_products: 1, is_active: 1, channels: 'pos'});
        if (preset !== undefined) { fill(PRESETS[preset]); $$('.rp-preset')[preset]?.classList.add('active'); }
        $('.rp-result', form).className = 'rp-result';
        modal().show();
    }
    $$('[data-new]').forEach(b => b.addEventListener('click', () => openNew(b.dataset.preset !== undefined ? Number(b.dataset.preset) : 0)));
    $$('.rp-preset').forEach(b => b.addEventListener('click', () => {
        $$('.rp-preset').forEach(x => x.classList.toggle('active', x === b));
        fill(PRESETS[b.dataset.presetI]);
    }));
    $$('[data-edit]').forEach(b => b.addEventListener('click', () => {
        const d = JSON.parse(b.dataset.edit);
        editingId = d.id;
        form.reset();
        $('#schedTitle').textContent = 'Edit "' + d.name + '"';
        $('#presetWrap').style.display = 'none';
        fill(d);
        $('.rp-result', form).className = 'rp-result';
        modal().show();
    }));
    modalEl.addEventListener('shown.bs.modal', () => form.elements.name.focus());
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = $('button[type=submit]', form); busy(btn, true);
        const fd = new FormData(form);
        ['include_transactions', 'include_shifts', 'include_products', 'include_profit', 'skip_if_empty', 'is_active'].forEach(k => fd.set(k, form.elements[k].checked ? '1' : '0'));
        if (editingId) fd.set('_method', 'PUT');
        try {
            const d = await req(editingId ? R.update.replace('__ID__', editingId) : R.store, 'POST', fd);
            result(form, true, d.message);
            setTimeout(() => location.reload(), 900);
        } catch (err) { result(form, false, err.message); busy(btn, false); }
    });

    // ---------------- card actions
    $$('[data-post]').forEach(b => b.addEventListener('click', async () => {
        if (b.dataset.confirm && !await ask(b.dataset.confirm)) return;
        busy(b, true);
        try { const d = await req(b.dataset.post, 'POST', new FormData()); toast(d.message); if (d.reload) setTimeout(() => location.reload(), 1200); }
        catch (err) { toast(err.message, false); }
        finally { busy(b, false); }
    }));
    $$('[data-delete]').forEach(b => b.addEventListener('click', async () => {
        if (!await ask('Delete "' + b.dataset.name + '"? It will stop being sent.')) return;
        const fd = new FormData(); fd.set('_method', 'DELETE');
        try { await req(b.dataset.delete, 'POST', fd); location.reload(); } catch (err) { toast(err.message, false); }
    }));

    // ---------------- one-off
    const oo = $('#oneOff');
    const pad = n => String(n).padStart(2, '0');
    const local = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    function setRange(k) {
        const now = new Date(), s = new Date(now), e = new Date(now);
        const sod = d => { d.setHours(0, 0, 0, 0); return d; }, eod = d => { d.setHours(23, 59, 0, 0); return d; };
        const monday = d => { const x = new Date(d); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return sod(x); };
        let from = sod(s), to = e;
        if (k === 'yesterday') { from = sod(new Date(now.getTime() - 864e5)); to = eod(new Date(now.getTime() - 864e5)); }
        if (k === 'week') { from = monday(now); }
        if (k === 'lastweek') { from = monday(new Date(now.getTime() - 7 * 864e5)); to = eod(new Date(from.getTime() + 6 * 864e5)); }
        if (k === 'month') { from = sod(new Date(now.getFullYear(), now.getMonth(), 1)); }
        if (k === 'lastmonth') { from = sod(new Date(now.getFullYear(), now.getMonth() - 1, 1)); to = eod(new Date(now.getFullYear(), now.getMonth(), 0)); }
        oo.elements.from.value = local(from); oo.elements.to.value = local(to);
        $$('#ranges button').forEach(b => b.classList.toggle('active', b.dataset.range === k));
    }
    $$('#ranges button').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
    ['from', 'to'].forEach(n => oo.elements[n].addEventListener('input', () => $$('#ranges button').forEach(b => b.classList.remove('active'))));
    setRange('today');
    const ooQuery = () => {
        const p = new URLSearchParams();
        for (const [k, v] of new FormData(oo).entries()) if (k !== 'recipients') p.append(k, v);
        ['include_transactions', 'include_shifts', 'include_products', 'include_profit'].forEach(k => p.set(k, oo.elements[k].checked ? '1' : '0'));
        p.set('channels', oo.elements.channels.checked ? 'all' : 'pos');
        return p.toString();
    };
    $('#ooPreview').addEventListener('click', () => window.open(R.preview + '?' + ooQuery(), '_blank', 'noopener'));
    $('#ooDownload').addEventListener('click', () => { location.href = R.download + '?' + ooQuery(); });
    oo.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = $('button[type=submit]', oo); busy(btn, true);
        const fd = new FormData();
        new URLSearchParams(ooQuery()).forEach((v, k) => fd.append(k, v));
        fd.set('recipients', oo.elements.recipients.value);
        try { const d = await req(R.sendOnce, 'POST', fd); result(oo, true, d.message); }
        catch (err) { result(oo, false, err.message); }
        finally { busy(btn, false); }
    });
})();
</script>
@endsection
