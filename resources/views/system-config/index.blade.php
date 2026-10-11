@extends('layouts.master')
@section('content')
@php
    $states = collect($health);
    $bad = $states->where('state', 'bad')->count();
    $warn = $states->where('state', 'warn')->count();
    $modeLabel = ['standalone' => 'Single store', 'branch' => 'Branch of head office', 'central' => 'Head office'][$hq['mode']] ?? $hq['mode'];
    $isWin = PHP_OS_FAMILY === 'Windows';
    $phpBin = $isWin ? 'C:\\xampp\\php\\php.exe'
        : (is_file('/Applications/XAMPP/xamppfiles/bin/php') ? '/Applications/XAMPP/xamppfiles/bin/php'
        : (is_file('/opt/lampp/bin/php') ? '/opt/lampp/bin/php' : 'php'));
    $cron = $isWin
        ? '"' . $phpBin . '" "' . base_path('artisan') . '" schedule:run'
        : '* * * * * cd ' . base_path() . ' && ' . $phpBin . ' artisan schedule:run >> /dev/null 2>&1';
    $toolInfo = [
        'clear_cache' => ['bi-stars', 'After changing settings or updating files, if pages look old.'],
        'cache_views' => ['bi-lightning-charge', 'Makes the first page loads faster after an update.'],
        'migrate' => ['bi-database-up', 'Applies database changes that come with an update. Back up first.'],
        'storage_link' => ['bi-images', 'Run once if product pictures don\'t show.'],
        'rebuild_stock' => ['bi-box-seam', 'Recounts every product\'s stock from its history.'],
        'sync_now' => ['bi-arrow-repeat', 'Sends sales to head office and fetches prices now.'],
        'hq_status' => ['bi-broadcast', 'Shows this server\'s role and pings head office.'],
        'queue_work' => ['bi-play-circle', 'Runs emails and other queued work waiting to go out.'],
        'queue_retry' => ['bi-arrow-counterclockwise', 'Puts failed background jobs back in the queue.'],
        'backup_db' => ['bi-cloud-arrow-down', 'Saves a .zip copy of the database (needs mysqldump).'],
    ];
    $mailPresets = [
        'gmail' => ['Gmail', 'smtp.gmail.com', 587, 'tls', 'Use an App Password (Google Account → Security → App passwords), not the normal password.'],
        'outlook' => ['Outlook / Microsoft 365', 'smtp.office365.com', 587, 'tls', 'The mailbox must have "Authenticated SMTP" turned on.'],
        'zoho' => ['Zoho Mail', 'smtp.zoho.com', 465, 'ssl', 'Use smtppro.zoho.com for paid Zoho Workplace accounts.'],
        'yahoo' => ['Yahoo Mail', 'smtp.mail.yahoo.com', 465, 'ssl', 'Needs an app password from Yahoo account security.'],
        'cpanel' => ['My web host (cPanel)', 'mail.' . (parse_url($data['server']['APP_URL'], PHP_URL_HOST) ?: 'yourdomain.com'), 465, 'ssl', 'Use the full email address as the username, and that mailbox\'s password.'],
    ];
@endphp

<style>
.sc-page { --sc-accent:#6366f1; --sc-accent2:#8b5cf6; --sc-ink:var(--cb-heading,#0f172a); --sc-muted:var(--cb-muted,#64748b); --sc-border:var(--cb-border,#e2e8f0); --sc-surface:var(--cb-surface,#fff); --sc-surface2:var(--cb-surface-2,#f8fafc); --sc-soft:#f0f4ff; --sc-ok:#10b981; --sc-warn:#f59e0b; --sc-bad:#ef4444; --sc-radius:14px; font-family:'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; }
[data-bs-theme="dark"] .sc-page { --sc-soft:#232a45; }
.sc-hero { background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 45%,#312e81 100%); border-radius:var(--sc-radius); padding:26px 30px; margin-bottom:20px; position:relative; overflow:hidden; color:#fff; animation:scDown .45s cubic-bezier(.22,1,.36,1); }
.sc-hero::after { content:''; position:absolute; right:-70px; top:-70px; width:240px; height:240px; border-radius:50%; background:rgba(99,102,241,.16); }
.sc-hero h1 { font-size:22px; font-weight:800; margin:0 0 4px; color:#fff; position:relative; z-index:1; }
.sc-hero p { margin:0; color:rgba(255,255,255,.66); font-size:13px; position:relative; z-index:1; }
.sc-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; position:relative; z-index:1; }
.sc-chip { display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.14); border-radius:999px; padding:6px 13px; font-size:12.5px; color:#fff; max-width:100%; }
.sc-chip b { font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sc-dot { width:8px; height:8px; border-radius:50%; flex:none; background:var(--sc-ok); box-shadow:0 0 0 3px rgba(16,185,129,.25); }
.sc-dot.warn { background:var(--sc-warn); box-shadow:0 0 0 3px rgba(245,158,11,.25); }
.sc-dot.bad { background:var(--sc-bad); box-shadow:0 0 0 3px rgba(239,68,68,.25); }
.sc-layout { display:grid; grid-template-columns:230px minmax(0,1fr); gap:20px; align-items:start; }
.sc-nav { position:sticky; top:86px; background:var(--sc-surface); border:1px solid var(--sc-border); border-radius:var(--sc-radius); padding:8px; }
.sc-nav a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:var(--sc-ink); font-weight:600; font-size:13.5px; text-decoration:none; transition:background .15s,color .15s; }
.sc-nav a i { font-size:16px; color:var(--sc-muted); width:18px; text-align:center; }
.sc-nav a:hover { background:var(--sc-surface2); }
.sc-nav a.active { background:var(--sc-soft); color:var(--sc-accent); }
.sc-nav a.active i { color:var(--sc-accent); }
.sc-nav .sc-badge { margin-left:auto; font-size:11px; border-radius:999px; padding:1px 7px; background:#fff4de; color:#a35a00; font-weight:700; }
.sc-nav .sc-badge.bad { background:#fde8e8; color:#b42318; }
.sc-panel { display:none; animation:scUp .3s ease-out; }
.sc-panel.active { display:block; }
.sc-card { background:var(--sc-surface); border:1px solid var(--sc-border); border-radius:var(--sc-radius); margin-bottom:18px; box-shadow:0 4px 20px rgba(15,23,42,.05); }
.sc-card-h { padding:16px 20px; border-bottom:1px solid var(--sc-border); display:flex; align-items:center; gap:12px; }
.sc-card-h h5 { margin:0; font-size:15.5px; font-weight:800; color:var(--sc-ink); }
.sc-card-h p { margin:2px 0 0; font-size:12.5px; color:var(--sc-muted); }
.sc-ico { width:38px; height:38px; flex:none; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; background:linear-gradient(135deg,var(--sc-accent),var(--sc-accent2)); }
.sc-card-b { padding:18px 20px; }
.sc-card-f { padding:14px 20px; border-top:1px solid var(--sc-border); background:var(--sc-surface2); border-radius:0 0 var(--sc-radius) var(--sc-radius); display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.sc-label { font-size:12.5px; font-weight:700; color:var(--sc-ink); margin-bottom:5px; display:block; }
.sc-help { font-size:12px; color:var(--sc-muted); margin-top:4px; }
.sc-page .form-control, .sc-page .form-select { border-radius:10px; border-color:var(--sc-border); min-height:42px; }
.sc-page .form-control:focus, .sc-page .form-select:focus { border-color:var(--sc-accent); box-shadow:0 0 0 .2rem rgba(99,102,241,.15); }
.sc-btn { display:inline-flex; align-items:center; gap:7px; border:0; border-radius:10px; padding:9px 18px; font-size:13px; font-weight:700; cursor:pointer; transition:transform .15s,box-shadow .15s,opacity .15s; text-decoration:none; white-space:nowrap; }
.sc-btn:disabled { opacity:.55; cursor:not-allowed; transform:none !important; }
.sc-btn-primary { background:linear-gradient(135deg,var(--sc-accent),var(--sc-accent2)); color:#fff; box-shadow:0 3px 12px rgba(99,102,241,.3); }
.sc-btn-primary:hover:not(:disabled) { transform:translateY(-1px); color:#fff; }
.sc-btn-light { background:var(--sc-surface); color:var(--sc-ink); border:1.5px solid var(--sc-border); }
.sc-btn-light:hover:not(:disabled) { background:var(--sc-surface2); color:var(--sc-ink); }
.sc-btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; }
.sc-btn .spinner-border { width:14px; height:14px; border-width:2px; }
.sc-result { display:none; margin-top:12px; border-radius:10px; padding:10px 14px; font-size:13px; line-height:1.45; }
.sc-result.ok { display:block; background:#e7f6ef; color:#0f5f3c; border:1px solid #bfe8d3; }
.sc-result.bad { display:block; background:#fdecec; color:#8f1d1d; border:1px solid #f6c9c9; }
.sc-result.info { display:block; background:#eef2ff; color:#3730a3; border:1px solid #d7dcfb; }
.sc-result .sc-note { display:block; margin-top:4px; opacity:.85; }
.sc-health { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:10px; }
.sc-h { display:flex; gap:10px; border:1px solid var(--sc-border); border-radius:12px; padding:11px 13px; background:var(--sc-surface); }
.sc-h .sc-dot { margin-top:6px; }
.sc-h .sc-dot.info { background:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,.2); }
.sc-h-l { font-size:12px; color:var(--sc-muted); font-weight:600; }
.sc-h-d { font-size:13.5px; font-weight:700; color:var(--sc-ink); word-break:break-word; }
.sc-h-f { font-size:12px; color:#a35a00; margin-top:3px; }
.sc-h.bad .sc-h-f { color:#b42318; }
.sc-group-t { font-size:11.5px; text-transform:uppercase; letter-spacing:.06em; color:var(--sc-muted); font-weight:800; margin:16px 0 8px; }
.sc-group-t:first-child { margin-top:0; }
.sc-code { display:flex; align-items:center; gap:8px; background:#0f172a; color:#e2e8f0; border-radius:10px; padding:10px 12px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
.sc-code code { color:inherit; flex:1; overflow-x:auto; white-space:nowrap; background:none; padding:0; }
.sc-code button { flex:none; background:rgba(255,255,255,.12); border:0; color:#fff; border-radius:7px; padding:4px 10px; font-size:12px; font-weight:600; }
.sc-ips { display:flex; flex-wrap:wrap; gap:8px; }
.sc-ip { border:1.5px solid var(--sc-border); background:var(--sc-surface); border-radius:10px; padding:8px 12px; font-weight:700; font-size:13px; color:var(--sc-ink); text-align:left; line-height:1.25; }
.sc-ip small { display:block; font-weight:500; color:var(--sc-muted); font-size:11px; }
.sc-ip:hover { border-color:var(--sc-accent); }
.sc-ip.active { border-color:var(--sc-accent); background:var(--sc-soft); color:var(--sc-accent); }
.sc-qr { display:flex; gap:18px; align-items:center; flex-wrap:wrap; }
.sc-qr-img { width:150px; height:150px; flex:none; padding:8px; background:#fff; border:1px solid var(--sc-border); border-radius:12px; display:flex; align-items:center; justify-content:center; }
.sc-qr-img svg { width:100%; height:100%; }
.sc-presets { display:flex; flex-wrap:wrap; gap:6px; }
.sc-presets button { border:1.5px solid var(--sc-border); background:var(--sc-surface); border-radius:999px; padding:6px 13px; font-size:12.5px; font-weight:600; color:var(--sc-ink); }
.sc-presets button:hover, .sc-presets button.active { border-color:var(--sc-accent); color:var(--sc-accent); background:var(--sc-soft); }
.sc-tools { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:10px; }
.sc-tool { text-align:left; border:1.5px solid var(--sc-border); background:var(--sc-surface); border-radius:12px; padding:12px 14px; display:flex; gap:11px; align-items:flex-start; transition:border-color .15s,transform .15s; }
.sc-tool:hover:not(:disabled) { border-color:var(--sc-accent); transform:translateY(-1px); }
.sc-tool i { font-size:19px; color:var(--sc-accent); margin-top:1px; }
.sc-tool b { display:block; font-size:13.5px; color:var(--sc-ink); }
.sc-tool span { display:block; font-size:12px; color:var(--sc-muted); margin-top:2px; }
.sc-tool.busy { border-color:var(--sc-accent); }
.sc-console { background:#0b1020; color:#cbd5e1; border-radius:12px; padding:12px 14px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; max-height:340px; overflow:auto; white-space:pre-wrap; word-break:break-word; margin:0; }
.sc-console .ok { color:#6ee7b7; } .sc-console .bad { color:#fca5a5; } .sc-console .dim { color:#64748b; }
.sc-table { width:100%; font-size:13px; }
.sc-table td, .sc-table th { padding:8px 6px; border-bottom:1px solid var(--sc-border); }
.sc-table th { font-size:11.5px; text-transform:uppercase; color:var(--sc-muted); letter-spacing:.04em; }
.sc-switch { display:flex; align-items:center; gap:10px; cursor:pointer; margin:0; }
.sc-switch input { width:40px; height:22px; }
.sc-banner { border-radius:12px; padding:12px 16px; font-size:13px; margin-bottom:16px; display:flex; gap:10px; align-items:flex-start; }
.sc-banner.warn { background:#fff7e6; border:1px solid #fde3a7; color:#7a4a00; }
.sc-banner.info { background:#eef2ff; border:1px solid #d7dcfb; color:#3730a3; }
.sc-banner i { font-size:17px; margin-top:1px; }
.sc-mode { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
.sc-pw { position:relative; }
.sc-pw .form-control { padding-right:42px; }
.sc-pw button { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; color:var(--sc-muted); padding:6px; }
@keyframes scDown { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:none} }
@keyframes scUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
@media (max-width: 991px) {
    .sc-layout { grid-template-columns:1fr; }
    .sc-nav { position:static; display:flex; overflow-x:auto; gap:4px; padding:6px; }
    .sc-nav a { flex:none; padding:8px 12px; }
    .sc-nav .sc-badge { margin-left:4px; }
    .sc-hero { padding:22px 20px; }
}
@media (prefers-reduced-motion: reduce) { .sc-hero, .sc-panel { animation:none; } }
</style>

<div class="main-content">
<div class="page-content">
<div class="container-fluid sc-page">

    <div class="sc-hero">
        <h1><i class="bi bi-sliders2 me-2"></i>System Configuration</h1>
        <p>Set up this server: its network address, head office link, database, email and maintenance – no terminal needed.</p>
        <div class="sc-chips">
            <span class="sc-chip"><span class="sc-dot {{ $bad ? 'bad' : ($warn ? 'warn' : '') }}"></span>Health: <b>{{ $bad ? $bad . ' problem(s)' : ($warn ? $warn . ' to check' : 'All good') }}</b></span>
            <span class="sc-chip"><i class="bi bi-diagram-3"></i>Role: <b>{{ $modeLabel }}{{ $hq['branch'] ? ' · ' . $hq['branch'] : '' }}</b></span>
            <span class="sc-chip"><i class="bi bi-hdd-network"></i>Address: <b>{{ $data['server']['APP_URL'] }}</b></span>
            <span class="sc-chip"><i class="bi bi-envelope"></i>Email: <b>{{ $data['mail']['MAIL_MAILER'] === 'log' ? 'not sending (log only)' : strtoupper($data['mail']['MAIL_MAILER']) }}</b></span>
        </div>
    </div>

    @unless ($envWritable)
        <div class="sc-banner warn"><i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Settings are read-only.</strong> The web server can't write to <code>{{ $envPath }}</code>, so changes can't be saved from here.
                Give it write permission{{ PHP_OS_FAMILY === 'Windows' ? '' : ' (e.g. chmod 664 and the web server as owner)' }}, then reload this page. Tests and maintenance tools still work.</div>
        </div>
    @endunless

    <div class="sc-layout">
        <nav class="sc-nav" id="scNav">
            <a href="#overview" data-panel="overview"><i class="bi bi-heart-pulse"></i>Overview @if ($bad || $warn)<span class="sc-badge {{ $bad ? 'bad' : '' }}">{{ $bad ?: $warn }}</span>@endif</a>
            <a href="#server" data-panel="server"><i class="bi bi-hdd-network"></i>Server & network</a>
            <a href="#hq" data-panel="hq"><i class="bi bi-buildings"></i>Head office</a>
            <a href="#database" data-panel="database"><i class="bi bi-database"></i>Database</a>
            <a href="#email" data-panel="email"><i class="bi bi-envelope-at"></i>Email</a>
            <a href="#app" data-panel="app"><i class="bi bi-shop"></i>Store & app</a>
            <a href="#advanced" data-panel="advanced"><i class="bi bi-speedometer2"></i>Performance</a>
            <a href="#maintenance" data-panel="maintenance"><i class="bi bi-tools"></i>Maintenance</a>
        </nav>

        <div>
            {{-- ======================= OVERVIEW ======================= --}}
            <section class="sc-panel" id="panel-overview">
                <div class="sc-card">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-heart-pulse"></i></div><div><h5>System health</h5><p>What's working and what needs attention on this server.</p></div></div>
                    <div class="sc-card-b">
                        @foreach ($states->groupBy('group') as $group => $items)
                            <div class="sc-group-t">{{ $group }}</div>
                            <div class="sc-health">
                                @foreach ($items as $h)
                                    <div class="sc-h {{ $h['state'] }}">
                                        <span class="sc-dot {{ $h['state'] === 'ok' ? '' : $h['state'] }}"></span>
                                        <div style="min-width:0">
                                            <div class="sc-h-l">{{ $h['label'] }}</div>
                                            <div class="sc-h-d">{{ $h['detail'] }}</div>
                                            @if ($h['fix'])<div class="sc-h-f">{{ $h['fix'] }}</div>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="sc-card">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-clock-history"></i></div><div><h5>Keep background work running</h5><p>Sync with head office, queued emails and clean-ups run from the scheduler, once a minute.</p></div></div>
                    <div class="sc-card-b">
                        @if ($isWin)
                            <p class="mb-2" style="font-size:13px">In <b>Task Scheduler</b> create a task that runs every minute (trigger: daily, repeat every 1 minute) with this program and arguments:</p>
                        @else
                            <p class="mb-2" style="font-size:13px">Open Terminal, run <code>crontab -e</code> and add this line, then save:</p>
                        @endif
                        <div class="sc-code"><code id="cronLine">{{ $cron }}</code><button type="button" data-copy="#cronLine">Copy</button></div>
                        <div class="sc-help">The health check above turns green within a minute of the scheduler starting.</div>
                    </div>
                </div>
            </section>

            {{-- ======================= SERVER ======================= --}}
            <section class="sc-panel" id="panel-server">
                <form class="sc-card" data-save="{{ route('system-config.server') }}">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-hdd-network"></i></div><div><h5>This server's address</h5><p>The address other tills, phones and emails use to reach this ViteMart.</p></div></div>
                    <div class="sc-card-b">
                        <label class="sc-label">Detected network addresses <span class="text-muted fw-normal">– tap one to use it</span></label>
                        <div class="sc-ips mb-3" id="ipList">
                            @forelse ($suggest as $i => $u)
                                <button type="button" class="sc-ip" data-url="{{ $u }}">{{ $ips[$i] }}<small>{{ $u }}</small></button>
                            @empty
                                <span class="text-muted" style="font-size:13px">No network address found – is this computer connected to the network?</span>
                            @endforelse
                            <button type="button" class="sc-ip" data-url="{{ $access }}">This browser's address<small>{{ $access }}</small></button>
                        </div>
                        <label class="sc-label" for="appUrl">Server address (APP_URL)</label>
                        <input class="form-control" id="appUrl" name="APP_URL" value="{{ $data['server']['APP_URL'] }}" placeholder="http://192.168.1.10/vitemart-hybrid/public" required>
                        <div class="sc-help">Include the folder if ViteMart isn't at the web root, e.g. <code>http://192.168.1.10/vitemart-hybrid/public</code>. Use <code>https://…</code> if the server has a certificate.</div>
                        <label class="sc-switch mt-3" id="secureWrap" style="{{ str_starts_with(strtolower($data['server']['APP_URL']), 'https') ? '' : 'display:none' }}">
                            <input type="checkbox" class="form-check-input mt-0" name="SESSION_SECURE_COOKIE" value="1" {{ $data['server']['SESSION_SECURE_COOKIE'] ? 'checked' : '' }}>
                            <span style="font-size:13px"><b>Only send sign-in cookies over https</b> <span class="text-muted">– turn on once every till uses https</span></span>
                        </label>
                        <div class="sc-result"></div>
                    </div>
                    <div class="sc-card-f"><button class="sc-btn sc-btn-primary" type="submit" data-needs-env><i class="bi bi-check2-circle"></i>Save address</button></div>
                </form>

                <div class="sc-card">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-qr-code"></i></div><div><h5>Open the till on another computer or tablet</h5><p>Any device on the same network can use this link.</p></div></div>
                    <div class="sc-card-b sc-qr">
                        @if ($qr)<div class="sc-qr-img" aria-label="QR code for the till">{!! $qr !!}</div>@endif
                        <div style="min-width:0;flex:1">
                            <div class="sc-code mb-2"><code id="tillUrl">{{ $tillUrl }}</code><button type="button" data-copy="#tillUrl">Copy</button></div>
                            <ul class="sc-help ps-3 mb-0" style="font-size:12.5px;line-height:1.7">
                                <li>Give this computer a <b>fixed IP</b> (a DHCP reservation in the router) so the address never changes.</li>
                                <li>Allow <b>port {{ $port }}</b> through the firewall{{ PHP_OS_FAMILY === 'Darwin' ? ' (System Settings → Network → Firewall)' : (PHP_OS_FAMILY === 'Windows' ? ' (Windows Defender Firewall → allow Apache)' : '') }}.</li>
                                <li>Scan the QR code with a phone or tablet to open the till straight away.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ======================= HEAD OFFICE ======================= --}}
            <section class="sc-panel" id="panel-hq">
                @if ($hq['mode'] === 'standalone')
                    <div class="sc-banner info"><i class="bi bi-info-circle-fill"></i><div>This server is a <b>single store</b>. Connect it to a head office to share products, prices, staff and reports – or make it the head office for your other branches.</div></div>
                    <div class="sc-mode">
                        <form class="sc-card mb-0" data-save="{{ route('system-config.hq.connect') }}" data-confirm="Connect this store to head office? Its sales, stock and customers will start syncing.">
                            <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-link-45deg"></i></div><div><h5>Connect to head office</h5><p>Make this server a branch.</p></div></div>
                            <div class="sc-card-b">
                                <label class="sc-label">Head office address or IP</label>
                                <div class="input-group">
                                    <input class="form-control" name="central_url" placeholder="192.168.1.5/vitemart/public  or  https://hq.example.com" required>
                                    <button class="sc-btn sc-btn-light" type="button" data-test="{{ route('system-config.hq.test') }}"><i class="bi bi-broadcast"></i>Test</button>
                                </div>
                                <div class="sc-help">The address head office opens ViteMart with. A bare IP is fine – http:// is added for you.</div>
                                <div class="row g-2 mt-1">
                                    <div class="col-sm-5"><label class="sc-label">Branch code</label><input class="form-control text-uppercase" name="branch_code" placeholder="IKJ" required></div>
                                    <div class="col-sm-7"><label class="sc-label">Enrollment code</label><input class="form-control" name="code" placeholder="7KQ2-M9XA-PL3D" autocomplete="off" required></div>
                                </div>
                                <div class="sc-help">Head office creates both under <b>Branches → Add branch → Issue enrollment code</b> (valid 24 hours, single use).</div>
                                <div class="sc-result"></div>
                            </div>
                            <div class="sc-card-f"><button class="sc-btn sc-btn-primary" type="submit"><i class="bi bi-link"></i>Connect</button></div>
                        </form>
                        <form class="sc-card mb-0" data-save="{{ route('system-config.hq.central') }}" data-confirm="Make this server the head office? Every product here becomes a head-office product. This can't easily be undone.">
                            <div class="sc-card-h"><div class="sc-ico" style="background:linear-gradient(135deg,#0ea5e9,#6366f1)"><i class="bi bi-buildings"></i></div><div><h5>Make this the head office</h5><p>Other branches will connect to this server.</p></div></div>
                            <div class="sc-card-b">
                                <label class="sc-label">Company / organisation name</label>
                                <input class="form-control" name="org" value="{{ $hq['organization'] ?? $data['app']['APP_NAME'] }}" required>
                                <label class="sc-switch mt-3"><input type="checkbox" class="form-check-input mt-0" name="confirm" value="1" required><span style="font-size:13px">I understand this server becomes the head office for all branches.</span></label>
                                <div class="sc-help mt-2">Branches will connect to: <code>{{ $data['server']['APP_URL'] }}</code> – set the right address under <a href="#server" data-goto="server">Server & network</a> first.</div>
                                <div class="sc-result"></div>
                            </div>
                            <div class="sc-card-f"><button class="sc-btn sc-btn-light" type="submit"><i class="bi bi-buildings"></i>Make head office</button></div>
                        </form>
                    </div>
                @elseif ($hq['mode'] === 'branch')
                    <form class="sc-card" data-save="{{ route('system-config.hq.url') }}" data-confirm-fail="Head office didn't answer. Save the new address anyway?">
                        <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-buildings"></i></div><div><h5>Head office connection</h5><p>{{ $hq['organization'] }} · this branch: {{ $hq['branch'] }} · enrolled {{ $hq['enrolled_at']?->diffForHumans() ?? '—' }}</p></div></div>
                        <div class="sc-card-b">
                            <label class="sc-label">Head office address or IP</label>
                            <div class="input-group">
                                <input class="form-control" name="central_url" value="{{ $hq['central_url'] }}" required>
                                <button class="sc-btn sc-btn-light" type="button" data-test="{{ route('system-config.hq.test') }}"><i class="bi bi-broadcast"></i>Test</button>
                            </div>
                            <div class="sc-help">Change this when head office moves to a new IP address or domain. The branch checks the new address with head office before saving.</div>
                            <div class="sc-result"></div>
                        </div>
                        <div class="sc-card-f">
                            <button class="sc-btn sc-btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Save address</button>
                            @if (Route::has('mb.sync.index'))<a class="sc-btn sc-btn-light ms-auto" href="{{ route('mb.sync.index') }}"><i class="bi bi-arrow-repeat"></i>Sync status</a>@endif
                        </div>
                    </form>
                @else
                    <div class="sc-card">
                        <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-buildings"></i></div><div><h5>This server is the head office</h5><p>{{ $hq['organization'] }}</p></div></div>
                        <div class="sc-card-b">
                            <label class="sc-label">Branches connect to this address</label>
                            <div class="sc-code mb-2"><code id="hqAddr">{{ $data['server']['APP_URL'] }}</code><button type="button" data-copy="#hqAddr">Copy</button></div>
                            <div class="sc-help">If this is wrong, fix it under <a href="#server" data-goto="server">Server & network</a>. When head office moves to a new IP, update it on each branch's System Configuration page.</div>
                        </div>
                        <div class="sc-card-f">
                            @if (Route::has('mb.branches.index'))<a class="sc-btn sc-btn-primary" href="{{ route('mb.branches.index') }}"><i class="bi bi-diagram-3"></i>Branches & enrollment codes</a>@endif
                            @if (Route::has('mb.sync.index'))<a class="sc-btn sc-btn-light" href="{{ route('mb.sync.index') }}"><i class="bi bi-arrow-repeat"></i>Sync status</a>@endif
                        </div>
                    </div>
                @endif
            </section>

            {{-- ======================= DATABASE ======================= --}}
            <section class="sc-panel" id="panel-database">
                <div class="sc-banner warn"><i class="bi bi-exclamation-triangle-fill"></i><div>Changing the database points ViteMart at different data. <b>Test first</b> – nothing is saved unless the connection works. Back up under Maintenance before switching.</div></div>
                <form class="sc-card" data-save="{{ route('system-config.database') }}" data-confirm-fail="The test failed. Save these database settings anyway? ViteMart will stop working until the database is reachable.">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-database"></i></div><div><h5>Database server</h5><p>Where products, sales and stock are stored (MySQL / MariaDB).</p></div></div>
                    <div class="sc-card-b">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="sc-label">Type</label>
                                <select class="form-select" name="DB_CONNECTION">
                                    @foreach (['mysql' => 'MySQL', 'mariadb' => 'MariaDB'] as $v => $l)<option value="{{ $v }}" @selected($data['db']['DB_CONNECTION'] === $v)>{{ $l }}</option>@endforeach
                                </select></div>
                            <div class="col-md-5"><label class="sc-label">Server IP / host</label><input class="form-control" name="DB_HOST" value="{{ $data['db']['DB_HOST'] }}" placeholder="127.0.0.1" required>
                                <div class="sc-help">127.0.0.1 = this computer.</div></div>
                            <div class="col-md-3"><label class="sc-label">Port</label><input class="form-control" name="DB_PORT" value="{{ $data['db']['DB_PORT'] }}" inputmode="numeric" required></div>
                            <div class="col-md-4"><label class="sc-label">Database name</label><input class="form-control" name="DB_DATABASE" value="{{ $data['db']['DB_DATABASE'] }}" required></div>
                            <div class="col-md-4"><label class="sc-label">Username</label><input class="form-control" name="DB_USERNAME" value="{{ $data['db']['DB_USERNAME'] }}" autocomplete="off" required></div>
                            <div class="col-md-4"><label class="sc-label">Password</label>
                                <div class="sc-pw"><input type="password" class="form-control" name="DB_PASSWORD" placeholder="{{ $data['db']['has_password'] ? 'unchanged' : 'no password' }}" autocomplete="new-password"><button type="button" data-reveal tabindex="-1"><i class="bi bi-eye"></i></button></div>
                                <div class="sc-help">Leave blank to keep the current one.@if ($data['db']['has_password']) <label class="ms-1"><input type="checkbox" name="clear_password" value="1"> remove it</label>@endif</div></div>
                            <div class="col-12"><label class="sc-label">mysqldump folder <span class="text-muted fw-normal">(for database backups)</span></label>
                                <input class="form-control" name="DB_DUMP_PATH" value="{{ $data['db']['DB_DUMP_PATH'] }}" placeholder="{{ $dumpHints[0] ?? '/Applications/XAMPP/xamppfiles/bin' }}">
                                @if ($dumpHints)<div class="sc-help">Found on this computer: @foreach ($dumpHints as $hint)<a href="#" data-fill="DB_DUMP_PATH" data-value="{{ $hint }}"><code>{{ $hint }}</code></a> @endforeach</div>@endif</div>
                        </div>
                        <div class="sc-result"></div>
                    </div>
                    <div class="sc-card-f">
                        <button class="sc-btn sc-btn-light" type="button" data-test="{{ route('system-config.database.test') }}"><i class="bi bi-plug"></i>Test connection</button>
                        <button class="sc-btn sc-btn-primary" type="submit" data-needs-env><i class="bi bi-check2-circle"></i>Save</button>
                    </div>
                </form>
            </section>

            {{-- ======================= EMAIL ======================= --}}
            <section class="sc-panel" id="panel-email">
                <form class="sc-card" data-save="{{ route('system-config.mail') }}" id="mailForm">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-envelope-at"></i></div><div><h5>Email sending</h5><p>Used for emailed receipts, password resets and notifications.</p></div></div>
                    <div class="sc-card-b">
                        <label class="sc-label">Quick setup</label>
                        <div class="sc-presets mb-2" id="mailPresets">
                            @foreach ($mailPresets as $k => $p)
                                <button type="button" data-host="{{ $p[1] }}" data-port="{{ $p[2] }}" data-sec="{{ $p[3] }}" data-tip="{{ $p[4] }}">{{ $p[0] }}</button>
                            @endforeach
                        </div>
                        <div class="sc-help mb-3" id="presetTip"></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="sc-label">Send using</label>
                                <select class="form-select" name="MAIL_MAILER" id="mailMailer">
                                    <option value="smtp" @selected($data['mail']['MAIL_MAILER'] === 'smtp')>SMTP server</option>
                                    <option value="sendmail" @selected($data['mail']['MAIL_MAILER'] === 'sendmail')>Sendmail (this server)</option>
                                    <option value="log" @selected($data['mail']['MAIL_MAILER'] === 'log')>Don't send – write to log</option>
                                </select></div>
                            <div class="col-md-5 smtp-only"><label class="sc-label">SMTP host</label><input class="form-control" name="MAIL_HOST" value="{{ $data['mail']['MAIL_HOST'] === '127.0.0.1' && $data['mail']['MAIL_MAILER'] === 'log' ? '' : $data['mail']['MAIL_HOST'] }}" placeholder="smtp.gmail.com"></div>
                            <div class="col-md-3 smtp-only"><label class="sc-label">Port</label><input class="form-control" name="MAIL_PORT" value="{{ $data['mail']['MAIL_PORT'] }}" inputmode="numeric"></div>
                            <div class="col-md-4 smtp-only"><label class="sc-label">Security</label>
                                <select class="form-select" name="MAIL_ENCRYPTION">
                                    <option value="tls" @selected($data['mail']['MAIL_ENCRYPTION'] === 'tls')>STARTTLS (port 587)</option>
                                    <option value="ssl" @selected($data['mail']['MAIL_ENCRYPTION'] === 'ssl')>SSL/TLS (port 465)</option>
                                    <option value="none" @selected($data['mail']['MAIL_ENCRYPTION'] === 'none')>None</option>
                                </select></div>
                            <div class="col-md-4 smtp-only"><label class="sc-label">Username</label><input class="form-control" name="MAIL_USERNAME" value="{{ $data['mail']['MAIL_USERNAME'] }}" autocomplete="off" placeholder="you@yourshop.com"></div>
                            <div class="col-md-4 smtp-only"><label class="sc-label">Password</label>
                                <div class="sc-pw"><input type="password" class="form-control" name="MAIL_PASSWORD" placeholder="{{ $data['mail']['has_password'] ? 'unchanged' : 'password / app password' }}" autocomplete="new-password"><button type="button" data-reveal tabindex="-1"><i class="bi bi-eye"></i></button></div>
                                @if ($data['mail']['has_password'])<div class="sc-help">Leave blank to keep the current one.</div>@endif</div>
                            <div class="col-md-6"><label class="sc-label">From address</label><input type="email" class="form-control" name="MAIL_FROM_ADDRESS" value="{{ $data['mail']['MAIL_FROM_ADDRESS'] === 'hello@example.com' ? '' : $data['mail']['MAIL_FROM_ADDRESS'] }}" placeholder="receipts@yourshop.com" required>
                                <div class="sc-help">Most providers require this to be the same as the username.</div></div>
                            <div class="col-md-6"><label class="sc-label">From name</label><input class="form-control" name="MAIL_FROM_NAME" value="{{ $data['mail']['MAIL_FROM_NAME'] === '${APP_NAME}' ? '' : $data['mail']['MAIL_FROM_NAME'] }}" placeholder="{{ $data['app']['APP_NAME'] }} (store name)"></div>
                        </div>
                        <hr class="my-3">
                        <label class="sc-label">Send a test email to</label>
                        <div class="input-group" style="max-width:480px">
                            <input type="email" class="form-control" name="test_to" value="{{ auth()->user()->email }}" placeholder="you@example.com">
                            <button class="sc-btn sc-btn-light" type="button" data-test="{{ route('system-config.mail.test') }}"><i class="bi bi-send"></i>Send test</button>
                        </div>
                        <div class="sc-help">Uses the settings above before you save them.</div>
                        <div class="sc-result"></div>
                    </div>
                    <div class="sc-card-f"><button class="sc-btn sc-btn-primary" type="submit" data-needs-env><i class="bi bi-check2-circle"></i>Save email settings</button></div>
                </form>
            </section>

            {{-- ======================= APP ======================= --}}
            <section class="sc-panel" id="panel-app">
                <form class="sc-card" data-save="{{ route('system-config.app') }}">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-shop"></i></div><div><h5>Store & app</h5><p>Name, time zone and how the app behaves.</p></div></div>
                    <div class="sc-card-b">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="sc-label">App / store name</label><input class="form-control" name="APP_NAME" value="{{ $data['app']['APP_NAME'] }}" required>
                                <div class="sc-help">Shown in the browser tab, emails and backups.</div></div>
                            <div class="col-md-6"><label class="sc-label">Time zone</label>
                                <select class="form-select" name="APP_TIMEZONE">
                                    @foreach (['Africa/Lagos', 'UTC'] as $tz)<option value="{{ $tz }}" @selected($data['app']['APP_TIMEZONE'] === $tz)>{{ $tz }}</option>@endforeach
                                    <option disabled>──────────</option>
                                    @foreach ($timezones as $tz)@if (!in_array($tz, ['Africa/Lagos', 'UTC']))<option value="{{ $tz }}" @selected($data['app']['APP_TIMEZONE'] === $tz)>{{ $tz }}</option>@endif @endforeach
                                </select>
                                <div class="sc-help">Best set before the shop goes live: times already saved are not converted.</div></div>
                            <div class="col-md-6"><label class="sc-label">Environment</label>
                                <select class="form-select" name="APP_ENV">
                                    <option value="production" @selected($data['app']['APP_ENV'] === 'production')>Production – live shop</option>
                                    <option value="local" @selected($data['app']['APP_ENV'] === 'local')>Local – development / testing</option>
                                    <option value="staging" @selected($data['app']['APP_ENV'] === 'staging')>Staging – trial run</option>
                                </select></div>
                            <div class="col-md-6"><label class="sc-label">Sign-in lasts (minutes of inactivity)</label><input class="form-control" type="number" min="5" max="1440" name="SESSION_LIFETIME" value="{{ $data['app']['SESSION_LIFETIME'] }}" required></div>
                            <div class="col-12">
                                <label class="sc-switch"><input type="checkbox" class="form-check-input mt-0" name="APP_DEBUG" value="1" {{ $data['app']['APP_DEBUG'] ? 'checked' : '' }}>
                                    <span style="font-size:13px"><b>Show error details</b> <span class="text-muted">– only while setting up; turn off for a live shop (it can reveal passwords on error pages)</span></span></label>
                            </div>
                        </div>
                        @if (Route::has('settings.store.index'))
                            <div class="sc-banner info mt-3 mb-0"><i class="bi bi-info-circle-fill"></i><div>Currency, receipt text, logo and store contact details are in <a href="{{ route('settings.store.index') }}">Store Settings</a>.</div></div>
                        @endif
                        <div class="sc-result"></div>
                    </div>
                    <div class="sc-card-f"><button class="sc-btn sc-btn-primary" type="submit" data-needs-env><i class="bi bi-check2-circle"></i>Save</button></div>
                </form>
            </section>

            {{-- ======================= ADVANCED ======================= --}}
            <section class="sc-panel" id="panel-advanced">
                <form class="sc-card" data-save="{{ route('system-config.advanced') }}">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-speedometer2"></i></div><div><h5>Performance & background work</h5><p>Leave these alone unless you know you need them.</p></div></div>
                    <div class="sc-card-b">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="sc-label">Background jobs (queue)</label>
                                <select class="form-select" name="QUEUE_CONNECTION" data-redis>
                                    <option value="database" @selected($data['adv']['QUEUE_CONNECTION'] === 'database')>Database – needs scheduler/worker</option>
                                    <option value="sync" @selected($data['adv']['QUEUE_CONNECTION'] === 'sync')>Run immediately (no worker)</option>
                                    <option value="redis" @selected($data['adv']['QUEUE_CONNECTION'] === 'redis')>Redis</option>
                                </select></div>
                            <div class="col-md-4"><label class="sc-label">Cache</label>
                                <select class="form-select" name="CACHE_STORE" data-redis>
                                    @foreach (['database' => 'Database', 'file' => 'Files (fastest on one server)', 'redis' => 'Redis'] as $v => $l)<option value="{{ $v }}" @selected($data['adv']['CACHE_STORE'] === $v)>{{ $l }}</option>@endforeach
                                </select></div>
                            <div class="col-md-4"><label class="sc-label">Sign-in sessions</label>
                                <select class="form-select" name="SESSION_DRIVER" data-redis>
                                    @foreach (['database' => 'Database (needed for Login Control)', 'file' => 'Files', 'redis' => 'Redis'] as $v => $l)<option value="{{ $v }}" @selected($data['adv']['SESSION_DRIVER'] === $v)>{{ $l }}</option>@endforeach
                                </select></div>
                            <div class="col-md-4"><label class="sc-label">Log detail</label>
                                <select class="form-select" name="LOG_LEVEL">
                                    @foreach (['debug' => 'Everything (debug)', 'info' => 'Info', 'notice' => 'Notice', 'warning' => 'Warnings & errors', 'error' => 'Errors only', 'critical' => 'Critical only'] as $v => $l)<option value="{{ $v }}" @selected($data['adv']['LOG_LEVEL'] === $v)>{{ $l }}</option>@endforeach
                                </select></div>
                            <div class="col-md-8"><label class="sc-label">Two-factor sign-in for admin roles</label>
                                <select class="form-select" name="VITEMART_MFA_ENFORCE">
                                    <option value="" @selected($data['adv']['VITEMART_MFA_ENFORCE'] === '')>Automatic – on for head office & branches, off for a single store</option>
                                    <option value="true" @selected($data['adv']['VITEMART_MFA_ENFORCE'] === 'true')>Always required</option>
                                    <option value="false" @selected($data['adv']['VITEMART_MFA_ENFORCE'] === 'false')>Off</option>
                                </select></div>
                            <div class="col-12" id="redisBox">
                                <div class="row g-3">
                                    <div class="col-md-5"><label class="sc-label">Redis host</label><input class="form-control" name="REDIS_HOST" value="{{ $data['adv']['REDIS_HOST'] }}"></div>
                                    <div class="col-md-3"><label class="sc-label">Redis port</label><input class="form-control" name="REDIS_PORT" value="{{ $data['adv']['REDIS_PORT'] }}"></div>
                                    <div class="col-md-4"><label class="sc-label">Redis password</label><input type="password" class="form-control" name="REDIS_PASSWORD" placeholder="{{ $data['adv']['has_redis_password'] ? 'unchanged' : 'none' }}" autocomplete="new-password"></div>
                                </div>
                            </div>
                        </div>
                        <div class="sc-result"></div>
                    </div>
                    <div class="sc-card-f"><button class="sc-btn sc-btn-primary" type="submit" data-needs-env><i class="bi bi-check2-circle"></i>Save</button></div>
                </form>
            </section>

            {{-- ======================= MAINTENANCE ======================= --}}
            <section class="sc-panel" id="panel-maintenance">
                <div class="sc-card">
                    <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-tools"></i></div><div><h5>Maintenance tools</h5><p>The jobs you'd normally run in a terminal.</p></div></div>
                    <div class="sc-card-b">
                        <div class="sc-tools">
                            @foreach ($tools as $t)
                                <button type="button" class="sc-tool" data-tool="{{ $t['key'] }}" data-url="{{ route('system-config.tool', $t['key']) }}" data-label="{{ $t['label'] }}"
                                        @if (in_array($t['key'], ['migrate', 'rebuild_stock'])) data-confirm="{{ $t['key'] === 'migrate' ? 'Apply database updates now? Make a backup first if you haven\'t.' : 'Recalculate every product\'s stock? This can take a few minutes on a big catalogue.' }}" @endif>
                                    <i class="bi {{ $toolInfo[$t['key']][0] ?? 'bi-gear' }}"></i>
                                    <div><b>{{ $t['label'] }}</b><span>{{ $toolInfo[$t['key']][1] ?? '' }}</span></div>
                                </button>
                            @endforeach
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                            <span class="sc-label mb-0">Output</span>
                            <button type="button" class="sc-btn sc-btn-light py-1 px-2" id="loadLog" data-url="{{ route('system-config.log') }}"><i class="bi bi-journal-text"></i>Show error log</button>
                        </div>
                        <pre class="sc-console" id="console"><span class="dim">Results of the tools above appear here.</span></pre>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="sc-card h-100 mb-0">
                            <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-cloud-arrow-down"></i></div><div><h5>Database backups</h5><p>Newest first · stored on this server</p></div></div>
                            <div class="sc-card-b">
                                @forelse ($dbBackups as $b)
                                    <div class="d-flex align-items-center gap-2 py-2" style="border-bottom:1px solid var(--sc-border);font-size:13px">
                                        <i class="bi bi-file-zip text-muted"></i>
                                        <div class="flex-grow-1" style="min-width:0"><div class="fw-bold text-truncate">{{ $b['name'] }}</div><div class="text-muted" style="font-size:12px">{{ \Carbon\Carbon::createFromTimestamp($b['time'])->diffForHumans() }} · {{ number_format($b['size'] / 1048576, 1) }} MB</div></div>
                                        <a class="sc-btn sc-btn-light py-1 px-2" href="{{ route('system-config.db-backup', $b['name']) }}"><i class="bi bi-download"></i></a>
                                    </div>
                                @empty
                                    <div class="text-muted" style="font-size:13px">No backups yet. Use <b>Back up the database now</b> above{{ $data['db']['DB_DUMP_PATH'] ? '' : ' – set the mysqldump folder under Database first if it fails' }}.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sc-card h-100 mb-0">
                            <div class="sc-card-h"><div class="sc-ico"><i class="bi bi-clock-history"></i></div><div><h5>Settings history</h5><p>A copy is kept every time settings are saved here.</p></div></div>
                            <div class="sc-card-b">
                                @forelse (array_slice($backups, 0, 8) as $b)
                                    <div class="d-flex align-items-center gap-2 py-2" style="border-bottom:1px solid var(--sc-border);font-size:13px">
                                        <i class="bi bi-file-earmark-text text-muted"></i>
                                        <div class="flex-grow-1"><div class="fw-bold">{{ \Carbon\Carbon::createFromTimestamp($b['time'])->format('d M Y, H:i') }}</div><div class="text-muted" style="font-size:12px">{{ \Carbon\Carbon::createFromTimestamp($b['time'])->diffForHumans() }}</div></div>
                                        <button type="button" class="sc-btn sc-btn-light py-1 px-2" data-restore="{{ $b['name'] }}" data-needs-env><i class="bi bi-arrow-counterclockwise"></i>Restore</button>
                                    </div>
                                @empty
                                    <div class="text-muted" style="font-size:13px">Nothing saved from this page yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
</div>
</div>

<script>
(function () {
    const CSRF = @json(csrf_token());
    const ENV_WRITABLE = @json($envWritable);
    const RESTORE_URL = @json(route('system-config.env.restore'));
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));

    // ---------- sections
    const panels = ['overview', 'server', 'hq', 'database', 'email', 'app', 'advanced', 'maintenance'];
    function show(id) {
        if (!panels.includes(id)) id = 'overview';
        $$('.sc-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + id));
        $$('#scNav a').forEach(a => a.classList.toggle('active', a.dataset.panel === id));
        try { localStorage.setItem('sc_panel', id); } catch (e) {}
        if (window.innerWidth < 992) { const a = $('#scNav a.active'); if (a) $('#scNav').scrollLeft = a.offsetLeft - 12; }
    }
    $$('#scNav a, [data-goto]').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        const id = a.dataset.panel || a.dataset.goto;
        history.replaceState(null, '', '#' + id);
        show(id);
        if (window.innerWidth < 992) $('#scNav').scrollIntoView({behavior: 'smooth', block: 'start'});
    }));
    let start = location.hash.slice(1);
    if (!start) { try { start = localStorage.getItem('sc_panel') || ''; } catch (e) {} }
    show(start);

    if (!ENV_WRITABLE) $$('[data-needs-env]').forEach(b => { b.disabled = true; b.title = 'The settings file is read-only'; });

    // ---------- helpers
    async function post(url, body) {
        const res = await fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body, credentials: 'same-origin'});
        let data = {};
        try { data = await res.json(); } catch (e) {}
        if (res.status === 423) { location.reload(); throw new Error('Please confirm your password again.'); }
        if (res.status === 419) throw new Error('Your session expired – reload the page.');
        if (!res.ok || data.success === false) {
            const first = data.errors ? Object.values(data.errors)[0][0] : null;
            const err = new Error(first || data.message || ('Request failed (' + res.status + ')'));
            err.data = data;
            throw err;
        }
        return data;
    }
    function result(form, kind, msg, note) {
        const r = $('.sc-result', form);
        if (!r) return;
        r.className = 'sc-result ' + kind;
        r.innerHTML = (kind === 'ok' ? '<i class="bi bi-check-circle-fill me-1"></i>' : kind === 'bad' ? '<i class="bi bi-x-circle-fill me-1"></i>' : '<i class="bi bi-info-circle-fill me-1"></i>')
            + esc(msg) + (note ? '<span class="sc-note">' + esc(note) + '</span>' : '');
    }
    function busy(btn, on) {
        if (!btn) return;
        if (on) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border"></span>' + btn.textContent.trim(); }
        else { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; }
    }
    async function ask(text, danger = false) {
        if (window.Swal) {
            const r = await Swal.fire({icon: 'warning', title: 'Are you sure?', text, showCancelButton: true, confirmButtonText: 'Yes, continue', confirmButtonColor: danger ? '#dc2626' : '#6366f1'});
            return r.isConfirmed;
        }
        return confirm(text);
    }
    function formData(form) {
        const fd = new FormData(form);
        $$('input[type=checkbox]', form).forEach(c => { if (!c.checked && c.name) fd.set(c.name, '0'); });
        return fd;
    }

    // ---------- save forms
    $$('form[data-save]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (form.dataset.confirm && !await ask(form.dataset.confirm, true)) return;
            const btn = $('button[type=submit]', form);
            busy(btn, true);
            try {
                const d = await post(form.dataset.save, formData(form));
                result(form, 'ok', d.message, d.note || (d.test && d.test.message));
                if (d.output) consoleOut(d.output, true);
                if (d.reload) setTimeout(() => location.reload(), 1600);
            } catch (err) {
                const canForce = form.dataset.confirmFail && !(err.data && err.data.errors);
                result(form, 'bad', err.message);
                if (err.data && err.data.output) consoleOut(err.data.output, false);
                if (canForce && await ask(form.dataset.confirmFail, true)) {
                    const fd = formData(form); fd.set('force', '1');
                    try { const d = await post(form.dataset.save, fd); result(form, 'ok', d.message, d.note); }
                    catch (e2) { result(form, 'bad', e2.message); }
                }
            } finally { busy(btn, false); }
        });
    });

    // ---------- test buttons (use the form's current values)
    $$('[data-test]').forEach(btn => btn.addEventListener('click', async () => {
        const form = btn.closest('form');
        busy(btn, true);
        result(form, 'info', 'Testing…');
        try {
            const d = await post(btn.dataset.test, formData(form));
            const ok = d.ok !== undefined ? d.ok : d.success;
            result(form, ok ? 'ok' : 'bad', d.message);
        } catch (err) { result(form, 'bad', err.message); }
        finally { busy(btn, false); }
    }));

    // ---------- small UI bits
    $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
        const t = $(b.dataset.copy).textContent.trim();
        try { await navigator.clipboard.writeText(t); } catch (e) {
            const ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
        }
        const o = b.textContent; b.textContent = 'Copied'; setTimeout(() => b.textContent = o, 1400);
    }));
    $$('[data-reveal]').forEach(b => b.addEventListener('click', () => {
        const i = b.parentElement.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password';
        b.innerHTML = i.type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    }));
    $$('[data-fill]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); const f = a.closest('form'); f.elements[a.dataset.fill].value = a.dataset.value; }));

    const appUrl = $('#appUrl');
    function markIp() { $$('#ipList .sc-ip').forEach(b => b.classList.toggle('active', b.dataset.url.replace(/\/$/, '') === appUrl.value.trim().replace(/\/$/, ''))); $('#secureWrap').style.display = /^https:/i.test(appUrl.value.trim()) ? '' : 'none'; }
    $$('#ipList .sc-ip').forEach(b => b.addEventListener('click', () => { appUrl.value = b.dataset.url; markIp(); appUrl.focus(); }));
    appUrl?.addEventListener('input', markIp);
    if (appUrl) markIp();

    // email presets + smtp-only fields
    const mailForm = $('#mailForm');
    if (mailForm) {
        const mailer = $('#mailMailer');
        const sync = () => $$('.smtp-only', mailForm).forEach(el => el.style.display = mailer.value === 'smtp' ? '' : 'none');
        mailer.addEventListener('change', sync); sync();
        $$('#mailPresets button').forEach(b => b.addEventListener('click', () => {
            $$('#mailPresets button').forEach(x => x.classList.toggle('active', x === b));
            mailer.value = 'smtp'; sync();
            mailForm.elements.MAIL_HOST.value = b.dataset.host;
            mailForm.elements.MAIL_PORT.value = b.dataset.port;
            mailForm.elements.MAIL_ENCRYPTION.value = b.dataset.sec;
            $('#presetTip').innerHTML = '<i class="bi bi-lightbulb me-1"></i>' + esc(b.dataset.tip);
            mailForm.elements.MAIL_USERNAME.focus();
        }));
        mailForm.elements.MAIL_ENCRYPTION.addEventListener('change', e => {
            const p = mailForm.elements.MAIL_PORT;
            if (e.target.value === 'ssl' && (p.value === '587' || !p.value)) p.value = '465';
            if (e.target.value === 'tls' && (p.value === '465' || !p.value)) p.value = '587';
        });
    }

    // redis fields only when redis is chosen
    const redisSel = $$('select[data-redis]');
    const redisSync = () => { const box = $('#redisBox'); if (box) box.style.display = redisSel.some(s => s.value === 'redis') ? '' : 'none'; };
    redisSel.forEach(s => s.addEventListener('change', redisSync)); redisSync();

    // ---------- maintenance
    const con = $('#console');
    function consoleOut(text, ok, title) {
        const stamp = new Date().toLocaleTimeString();
        con.innerHTML = (title ? `<span class="${ok ? 'ok' : 'bad'}">● ${esc(title)}</span> <span class="dim">${stamp}</span>\n` : '') + esc(text);
        con.scrollTop = 0;
    }
    $$('[data-tool]').forEach(b => b.addEventListener('click', async () => {
        if (b.dataset.confirm && !await ask(b.dataset.confirm)) return;
        $$('[data-tool]').forEach(x => x.disabled = true);
        b.classList.add('busy');
        con.innerHTML = `<span class="dim">Running "${esc(b.dataset.label)}"… this can take a minute.</span>`;
        try { const d = await post(b.dataset.url, new FormData()); consoleOut(d.output, true, d.message); }
        catch (err) { consoleOut((err.data && err.data.output) || err.message, false, (err.data && err.data.message) || 'Failed'); }
        finally { $$('[data-tool]').forEach(x => x.disabled = false); b.classList.remove('busy'); }
    }));
    $('#loadLog')?.addEventListener('click', async e => {
        const b = e.currentTarget; busy(b, true);
        try {
            const res = await fetch(b.dataset.url + '?lines=200', {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (res.status === 423) return location.reload();
            const d = await res.json();
            consoleOut(d.output, true, 'Last 200 lines of storage/logs/laravel.log');
            con.scrollTop = con.scrollHeight;
        } catch (err) { consoleOut(err.message, false, 'Could not read the log'); }
        finally { busy(b, false); }
    });
    $$('[data-restore]').forEach(b => b.addEventListener('click', async () => {
        if (!await ask('Put back the settings saved at this time? Your current settings are kept as a copy first.', true)) return;
        busy(b, true);
        try { const fd = new FormData(); fd.set('name', b.dataset.restore); const d = await post(RESTORE_URL, fd); consoleOut(d.message, true, 'Settings restored'); setTimeout(() => location.reload(), 1200); }
        catch (err) { consoleOut(err.message, false, 'Restore failed'); busy(b, false); }
    }));
})();
</script>
@endsection
