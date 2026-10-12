@extends('layouts.master')
@section('content')
@php
    $statusLabel = ['checking' => ['Checking', '#eef2ff', '#3730a3'], 'checked' => ['Ready to save', '#fff7e6', '#92400e'], 'applying' => ['Saving', '#eef2ff', '#3730a3'],
        'done' => ['Saved', '#e7f6ef', '#0f7a4a'], 'failed' => ['Failed', '#fde8e8', '#b42318'], 'cancelled' => ['Cancelled', '#f1f3f7', '#64748b']];
@endphp
<style>
.px-page { --px-accent:#0f766e; --px-accent2:#14b8a6; --px-ink:var(--cb-heading,#0f172a); --px-muted:var(--cb-muted,#64748b); --px-border:var(--cb-border,#e2e8f0); --px-surface:var(--cb-surface,#fff); --px-surface2:var(--cb-surface-2,#f8fafc); --px-soft:#ecfdf5; font-family:'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; }
[data-bs-theme="dark"] .px-page { --px-soft:#0f2e2a; }
.px-hero { background:linear-gradient(135deg,#0f172a 0%,#134e4a 55%,#0f766e 100%); border-radius:14px; padding:26px 30px; margin-bottom:20px; color:#fff; position:relative; overflow:hidden; animation:pxDown .45s cubic-bezier(.22,1,.36,1); }
.px-hero::after { content:''; position:absolute; right:-60px; top:-60px; width:220px; height:220px; border-radius:50%; background:rgba(20,184,166,.18); }
.px-hero h1 { font-size:22px; font-weight:800; margin:0 0 4px; color:#fff; position:relative; z-index:1; }
.px-hero p { margin:0; color:rgba(255,255,255,.72); font-size:13px; position:relative; z-index:1; max-width:700px; }
.px-steps { display:flex; gap:8px; flex-wrap:wrap; margin-top:16px; position:relative; z-index:1; }
.px-steps span { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.16); border-radius:999px; padding:5px 12px; font-size:12.5px; }
.px-steps b { display:inline-block; width:20px; height:20px; line-height:20px; text-align:center; border-radius:50%; background:#fff; color:#0f766e; font-size:11px; margin-right:6px; }
.px-card { background:var(--px-surface); border:1px solid var(--px-border); border-radius:14px; box-shadow:0 4px 20px rgba(15,23,42,.05); height:100%; display:flex; flex-direction:column; }
.px-card-h { padding:16px 20px; border-bottom:1px solid var(--px-border); display:flex; gap:12px; align-items:center; }
.px-card-h h5 { margin:0; font-size:15.5px; font-weight:800; color:var(--px-ink); }
.px-card-h p { margin:2px 0 0; font-size:12.5px; color:var(--px-muted); }
.px-ico { width:40px; height:40px; flex:none; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:19px; color:#fff; background:linear-gradient(135deg,var(--px-accent),var(--px-accent2)); }
.px-card-b { padding:18px 20px; flex:1; }
.px-card-f { padding:14px 20px; border-top:1px solid var(--px-border); background:var(--px-surface2); border-radius:0 0 14px 14px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.px-label { font-size:12.5px; font-weight:700; color:var(--px-ink); margin-bottom:5px; display:block; }
.px-help { font-size:12px; color:var(--px-muted); margin-top:4px; }
.px-page .form-select, .px-page .form-control { border-radius:10px; min-height:42px; }
.px-btn { display:inline-flex; align-items:center; gap:7px; border:0; border-radius:10px; padding:9px 16px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; transition:transform .15s; white-space:nowrap; }
.px-btn:disabled { opacity:.55; cursor:not-allowed; }
.px-btn-primary { background:linear-gradient(135deg,var(--px-accent),var(--px-accent2)); color:#fff; box-shadow:0 3px 12px rgba(15,118,110,.3); }
.px-btn-primary:hover:not(:disabled) { transform:translateY(-1px); color:#fff; }
.px-btn-light { background:var(--px-surface); color:var(--px-ink); border:1.5px solid var(--px-border); }
.px-btn-light:hover { background:var(--px-surface2); color:var(--px-ink); }
.px-btn .spinner-border { width:14px; height:14px; border-width:2px; }
.px-seg { display:inline-flex; background:var(--px-surface2); border:1px solid var(--px-border); border-radius:10px; padding:3px; gap:2px; }
.px-seg label { margin:0; }
.px-seg input { display:none; }
.px-seg span { display:block; padding:6px 13px; border-radius:8px; font-size:13px; font-weight:700; color:var(--px-muted); cursor:pointer; }
.px-seg input:checked + span { background:var(--px-surface); color:var(--px-accent); box-shadow:0 1px 3px rgba(15,23,42,.12); }
.px-drop { border:2px dashed var(--px-border); border-radius:14px; padding:26px 16px; text-align:center; cursor:pointer; transition:border-color .15s, background .15s; background:var(--px-surface2); }
.px-drop:hover, .px-drop.over { border-color:var(--px-accent); background:var(--px-soft); }
.px-drop i { font-size:34px; color:var(--px-accent); }
.px-drop .px-file { font-weight:800; color:var(--px-ink); margin-top:6px; word-break:break-all; }
.px-checks { display:flex; flex-direction:column; gap:6px; font-size:13px; margin-top:14px; }
.px-checks label { display:flex; gap:8px; align-items:flex-start; cursor:pointer; margin:0; }
.px-checks input { margin-top:3px; }
.px-err { display:none; margin-top:12px; border-radius:10px; padding:10px 14px; font-size:13px; background:#fdecec; color:#8f1d1d; border:1px solid #f6c9c9; }
#work { display:none; margin-top:20px; animation:pxUp .3s ease-out; }
.px-progress { height:12px; border-radius:999px; background:var(--px-surface2); border:1px solid var(--px-border); overflow:hidden; }
.px-progress > div { height:100%; width:0; background:linear-gradient(90deg,var(--px-accent),var(--px-accent2)); transition:width .4s ease; }
.px-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px; }
.px-tile { border:1px solid var(--px-border); border-radius:12px; padding:12px 14px; background:var(--px-surface); }
.px-tile b { display:block; font-size:24px; font-weight:800; line-height:1.1; }
.px-tile span { font-size:12px; color:var(--px-muted); font-weight:600; }
.px-tile.new b { color:#0f766e; } .px-tile.upd b { color:#4f46e5; } .px-tile.err b { color:#dc2626; } .px-tile.stk b { color:#b45309; }
.px-note { border-radius:12px; padding:11px 14px; font-size:13px; margin-bottom:10px; display:flex; gap:10px; }
.px-note.warn { background:#fff7e6; border:1px solid #fde3a7; color:#7a4a00; }
.px-note.info { background:#eef2ff; border:1px solid #d7dcfb; color:#3730a3; }
.px-note.ok { background:#e7f6ef; border:1px solid #bfe8d3; color:#0f5f3c; }
.px-tabs { display:flex; gap:4px; border-bottom:1px solid var(--px-border); margin-bottom:12px; flex-wrap:wrap; }
.px-tabs button { border:0; background:none; padding:9px 14px; font-weight:700; font-size:13px; color:var(--px-muted); border-bottom:2.5px solid transparent; margin-bottom:-1px; }
.px-tabs button.active { color:var(--px-accent); border-bottom-color:var(--px-accent); }
.px-tabs .n { font-size:11px; background:var(--px-surface2); border-radius:999px; padding:1px 7px; margin-left:4px; }
.px-table { width:100%; font-size:13px; }
.px-table th { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--px-muted); padding:8px; border-bottom:1px solid var(--px-border); white-space:nowrap; }
.px-table td { padding:8px; border-bottom:1px solid var(--px-border); vertical-align:top; }
.px-old { color:#94a3b8; text-decoration:line-through; }
.px-new { color:#0f766e; font-weight:700; }
.px-chg { display:inline-block; margin:0 10px 3px 0; white-space:nowrap; }
.px-lvl { font-size:11px; font-weight:800; border-radius:999px; padding:1px 8px; }
.px-lvl.error { background:#fde8e8; color:#b42318; } .px-lvl.warning { background:#fff4de; color:#a35a00; }
.px-scroll { max-height:420px; overflow:auto; }
.px-recent td { font-size:12.5px; }
@keyframes pxDown { from{opacity:0;transform:translateY(-14px)} to{opacity:1;transform:none} }
@keyframes pxUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
@media (prefers-reduced-motion: reduce) { .px-hero, #work { animation:none; } .px-progress > div { transition:none; } }
</style>

<div class="main-content">
<div class="page-content">
<div class="container-fluid px-page">

    <div class="px-hero">
        <h1><i class="bi bi-file-earmark-spreadsheet me-2"></i>Import / Export Products</h1>
        <p>Download your products to Excel, change prices, add new products or count stock in the spreadsheet, then upload it. You'll see exactly what will change before anything is saved.</p>
        <div class="px-steps"><span><b>1</b>Download</span><span><b>2</b>Edit in Excel</span><span><b>3</b>Upload &amp; check</span><span><b>4</b>Save</span></div>
    </div>

    <div class="row g-3">
        {{-- ================= DOWNLOAD ================= --}}
        <div class="col-lg-5">
            <form class="px-card" method="GET" action="{{ route('products.spreadsheet.export') }}">
                <div class="px-card-h"><div class="px-ico"><i class="bi bi-download"></i></div><div><h5>Download products</h5><p>{{ number_format($counts->total ?? 0) }} products · {{ number_format($counts->active ?? 0) }} active</p></div></div>
                <div class="px-card-b">
                    <label class="px-label">Which products</label>
                    <div class="px-seg mb-3">
                        <label><input type="radio" name="status" value="all" checked><span>All</span></label>
                        <label><input type="radio" name="status" value="active"><span>Active</span></label>
                        <label><input type="radio" name="status" value="inactive"><span>Inactive</span></label>
                    </div>
                    <label class="px-label" for="pxCat">Category</label>
                    <select class="form-select mb-3" name="category_id" id="pxCat">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <label class="px-label">File type</label>
                    <div class="px-seg">
                        <label><input type="radio" name="format" value="xlsx" checked><span>Excel (.xlsx)</span></label>
                        <label><input type="radio" name="format" value="csv"><span>CSV</span></label>
                    </div>
                    <div class="px-help mt-2">The Excel file has dropdowns for category, brand and unit, and an Instructions sheet.</div>
                </div>
                <div class="px-card-f">
                    <button class="px-btn px-btn-primary" type="submit"><i class="bi bi-file-earmark-arrow-down"></i>Download</button>
                    <a class="px-btn px-btn-light" href="{{ route('products.spreadsheet.template') }}"><i class="bi bi-file-earmark-plus"></i>Blank template</a>
                </div>
            </form>
        </div>

        {{-- ================= UPLOAD ================= --}}
        <div class="col-lg-7">
            <form class="px-card" id="upForm" autocomplete="off">
                <div class="px-card-h"><div class="px-ico"><i class="bi bi-upload"></i></div><div><h5>Upload a spreadsheet</h5><p>Your downloaded file, the template, or a supplier's list with SKUs or barcodes.</p></div></div>
                <div class="px-card-b">
                    @if ($canImport)
                        <label class="px-drop" id="drop">
                            <input type="file" name="file" id="fileIn" accept=".xlsx,.csv" hidden>
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="fw-bold mt-1">Drop the file here, or click to choose</div>
                            <div class="px-help">Excel (.xlsx) or .csv · up to 50 MB</div>
                            <div class="px-file" id="fileName"></div>
                        </label>
                        <div class="px-checks">
                            <label><input type="checkbox" class="form-check-input" name="update_existing" value="1" checked><span><b>Update existing products</b> – matched by ID, SKU, barcode or exact name</span></label>
                            <label><input type="checkbox" class="form-check-input" name="create_new" value="1" checked><span><b>Add new products</b> – rows that don't match any product</span></label>
                            <label><input type="checkbox" class="form-check-input" name="create_missing" value="1" checked><span><b>Create missing categories, brands and units</b></span></label>
                        </div>
                        <div class="px-err" id="upErr"></div>
                    @else
                        <div class="px-note info mb-0"><i class="bi bi-lock"></i><div>You can download products. Uploading needs the <b>Import products</b> permission.</div></div>
                    @endif
                </div>
                @if ($canImport)
                    <div class="px-card-f"><button class="px-btn px-btn-primary" type="submit" id="upBtn" disabled><i class="bi bi-search"></i>Upload &amp; check</button><span class="px-help m-0">Nothing is saved until you confirm.</span></div>
                @endif
            </form>
        </div>
    </div>

    {{-- ================= CHECK / SAVE ================= --}}
    <div class="px-card" id="work">
        <div class="px-card-h"><div class="px-ico" id="workIco"><i class="bi bi-hourglass-split"></i></div><div style="min-width:0"><h5 id="workTitle">Checking…</h5><p id="workSub"></p></div></div>
        <div class="px-card-b">
            <div id="progressBox"><div class="px-progress mb-2"><div id="bar"></div></div><div class="px-help" id="phase"></div></div>
            <div id="resultBox" style="display:none">
                <div class="px-tiles" id="tiles"></div>
                <div id="notes"></div>
                <div class="px-tabs" id="tabs"></div>
                <div class="px-scroll" id="tabBody"></div>
            </div>
        </div>
        <div class="px-card-f" id="workFoot" style="display:none"></div>
    </div>

    {{-- ================= RECENT ================= --}}
    @if ($recent->isNotEmpty())
        <div class="px-card mt-3" style="height:auto">
            <div class="px-card-h"><div class="px-ico" style="background:#94a3b8"><i class="bi bi-clock-history"></i></div><div><h5>Recent uploads</h5></div></div>
            <div class="px-card-b p-0">
                <div class="table-responsive">
                <table class="px-table px-recent mb-0">
                    <thead><tr><th class="ps-3">File</th><th>By</th><th>When</th><th>Rows</th><th>Result</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($recent as $r)
                        @php $st = $statusLabel[$r->status] ?? [$r->status, '#f1f3f7', '#64748b']; $res = $r->result ?? []; @endphp
                        <tr>
                            <td class="ps-3 fw-bold">{{ $r->original_name }}</td>
                            <td>{{ trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: '—' }}</td>
                            <td>{{ $r->created_at->diffForHumans() }}</td>
                            <td>{{ number_format($r->total_rows) }}</td>
                            <td><span class="px-lvl" style="background:{{ $st[1] }};color:{{ $st[2] }}">{{ $st[0] }}</span>
                                @if ($r->status === 'done')<span class="text-muted ms-1">{{ $res['created'] ?? 0 }} added · {{ $res['updated'] ?? 0 }} changed{{ ($res['failed'] ?? 0) ? ' · ' . $res['failed'] . ' not saved' : '' }}</span>@endif</td>
                            <td class="text-end pe-3">
                                @if ($canImport && in_array($r->status, ['checking', 'checked', 'applying']))
                                    <button type="button" class="px-btn px-btn-light py-1 px-2" data-resume="{{ route('products.spreadsheet.show', $r) }}">{{ $r->status === 'checked' ? 'Review' : 'Continue' }}</button>
                                @elseif ($canImport && in_array($r->status, ['done', 'checked']) && (($r->counts['error'] ?? 0) + ($r->counts['warning'] ?? 0) + ($res['failed'] ?? 0)) > 0)
                                    <a class="px-btn px-btn-light py-1 px-2" href="{{ route('products.spreadsheet.issues', $r) }}"><i class="bi bi-filetype-csv"></i>Problems</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    @endif
</div>
</div>
</div>

<script>
(function () {
    const CSRF = @json(csrf_token());
    const UPLOAD = @json(route('products.spreadsheet.upload'));
    const PRODUCTS = @json(route('products.index'));
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const nf = n => Number(n || 0).toLocaleString();
    let state = null, stopped = false;

    async function post(url, body) {
        const res = await fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: body || new FormData(), credentials: 'same-origin'});
        let d = {}; try { d = await res.json(); } catch (e) {}
        if (!res.ok || d.success === false) {
            const first = d.errors ? Object.values(d.errors)[0][0] : null;
            throw new Error(first || d.message || ('Request failed (' + res.status + ')'));
        }
        return d;
    }

    // ---------------- file picking
    const form = $('#upForm'), fileIn = $('#fileIn'), drop = $('#drop'), upBtn = $('#upBtn');
    function pick(f) {
        if (!f) return;
        const dt = new DataTransfer(); dt.items.add(f); fileIn.files = dt.files;
        $('#fileName').textContent = f.name + ' · ' + (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.ceil(f.size / 1024) + ' KB');
        upBtn.disabled = false; $('#upErr').style.display = 'none';
    }
    if (drop) {
        fileIn.addEventListener('change', () => pick(fileIn.files[0]));
        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
        drop.addEventListener('drop', e => pick(e.dataTransfer.files[0]));
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (!fileIn.files[0]) return;
            upBtn.disabled = true; upBtn.innerHTML = '<span class="spinner-border"></span>Uploading…';
            const fd = new FormData();
            fd.append('file', fileIn.files[0]);
            ['update_existing', 'create_new', 'create_missing'].forEach(k => fd.append(k, form.elements[k].checked ? '1' : '0'));
            try {
                const d = await post(UPLOAD, fd);
                state = d.import; render(); $('#work').scrollIntoView({behavior: 'smooth', block: 'start'}); run();
            } catch (err) {
                $('#upErr').textContent = err.message; $('#upErr').style.display = 'block';
            } finally { upBtn.disabled = false; upBtn.innerHTML = '<i class="bi bi-search"></i>Upload &amp; check'; }
        });
    }
    $$('[data-resume]').forEach(b => b.addEventListener('click', async () => {
        const res = await fetch(b.dataset.resume, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
        const d = await res.json(); state = d.import; render(); $('#work').scrollIntoView({behavior: 'smooth', block: 'start'}); run();
    }));

    // ---------------- stepping
    async function run() {
        stopped = false;
        while (state && ['checking', 'applying'].includes(state.status) && !stopped) {
            try { const d = await post(state.urls.step); state = d.import; render(); if (d.busy) await new Promise(r => setTimeout(r, 1500)); }
            catch (err) { await new Promise(r => setTimeout(r, 2500)); if (!navigator.onLine) continue; $('#phase').textContent = 'Connection problem – retrying… (' + err.message + ')'; }
        }
    }
    window.addEventListener('beforeunload', e => { if (state && state.status === 'applying') { e.preventDefault(); e.returnValue = ''; } });

    // ---------------- rendering
    function render() {
        const w = $('#work'); w.style.display = 'block';
        const s = state, c = s.counts || {}, busy = ['checking', 'applying'].includes(s.status);
        const icons = {checking: 'bi-hourglass-split', checked: 'bi-clipboard-check', applying: 'bi-hourglass-split', done: 'bi-check2-circle', failed: 'bi-x-octagon', cancelled: 'bi-slash-circle'};
        $('#workIco').innerHTML = '<i class="bi ' + (icons[s.status] || 'bi-file-earmark') + '"></i>';
        $('#workTitle').textContent = {checking: 'Checking your spreadsheet…', checked: 'Check complete – review before saving', applying: 'Saving…', done: 'Saved', failed: 'Something went wrong', cancelled: 'Cancelled'}[s.status] || s.status;
        $('#workSub').textContent = s.name + ' · ' + nf(s.total) + ' row' + (s.total === 1 ? '' : 's');
        $('#progressBox').style.display = busy ? '' : 'none';
        $('#bar').style.width = s.progress + '%';
        $('#phase').textContent = (s.phase || '') + (s.status === 'applying' ? ' – keep this page open.' : '');

        const box = $('#resultBox'), foot = $('#workFoot');
        if (s.status === 'failed') {
            box.style.display = ''; $('#tiles').innerHTML = ''; $('#tabs').innerHTML = ''; $('#tabBody').innerHTML = '';
            $('#notes').innerHTML = note('warn', 'bi-x-octagon', esc(s.error || 'The upload failed.'));
            foot.style.display = 'none';
            return;
        }
        if (busy || s.status === 'cancelled') { box.style.display = 'none'; foot.style.display = 'none'; return; }
        box.style.display = '';

        if (s.status === 'done') {
            const r = s.result || {};
            $('#tiles').innerHTML = tile('new', r.created, 'added') + tile('upd', r.updated, 'changed') + tile('', r.unchanged, 'unchanged') + tile('err', (r.failed || 0) + (r.skipped || 0), 'not saved');
            let n = note('ok', 'bi-check-circle-fill', 'Products saved. Every price change is in the price history.');
            (r.adjustments || []).forEach(a => {
                const link = a.id && s.urls.adjustment ? `<a href="${s.urls.adjustment.replace('__ID__', a.id)}">${esc(a.reference)}</a>` : esc(a.reference || '');
                n += a.status === 'failed'
                    ? note('warn', 'bi-exclamation-triangle', `${esc(a.type)} for ${nf(a.items)} product(s) could not be recorded: ${esc(a.error || '')}`)
                    : note(a.status === 'pending' ? 'warn' : 'info', 'bi-box-seam', `${esc(a.type)} ${link} – ${nf(a.items)} product(s)` + (a.status === 'pending' ? ' – <b>waiting for a supervisor to approve it</b> under Stock Adjustments before stock changes.' : ' – stock updated.'));
            });
            $('#notes').innerHTML = n;
            renderTabs(true);
            foot.style.display = '';
            foot.innerHTML = `<a class="px-btn px-btn-primary" href="${PRODUCTS}"><i class="bi bi-box-seam"></i>Go to products</a>`
                + ((r.failed || 0) + (r.skipped || 0) ? `<a class="px-btn px-btn-light" href="${s.urls.issues}"><i class="bi bi-filetype-csv"></i>Download problems</a>` : '')
                + `<button class="px-btn px-btn-light ms-auto" type="button" onclick="location.reload()"><i class="bi bi-upload"></i>Upload another file</button>`;
            return;
        }

        // checked
        $('#tiles').innerHTML = tile('new', c.create, 'new products') + tile('upd', c.update, 'will change') + tile('', c.unchanged, 'unchanged')
            + tile('stk', c.stock, 'stock changes') + tile('err', c.error, 'with problems (skipped)') + (c.skip ? tile('', c.skip, 'skipped') : '');
        let n = '';
        if (s.columns && s.columns.ignored && s.columns.ignored.length) n += note('info', 'bi-info-circle', 'These columns aren\'t used and were ignored: <b>' + s.columns.ignored.map(esc).join(', ') + '</b>.');
        if (c.new_names && c.new_names.length) n += note('info', 'bi-plus-circle', 'Will be created: ' + c.new_names.map(esc).join(', ') + '.');
        if (s.seen_before) n += note('warn', 'bi-exclamation-triangle-fill', 'This exact file was already saved on ' + esc(s.seen_before.at) + '. Saving it again adds any "Add stock (received)" quantities a second time.');
        if (c.error) n += note('warn', 'bi-exclamation-triangle', `<b>${nf(c.error)} row(s) have problems</b> and will be skipped – see the Problems tab, fix them in the spreadsheet and upload it again later.`);
        if (c.stock) n += note('info', 'bi-box-seam', `${nf(c.stock)} row(s) change stock. These are recorded as stock entries (received / stock count) so the stock history stays correct.`);
        $('#notes').innerHTML = n;
        renderTabs(false);
        const total = (c.create || 0) + (c.update || 0) + (c.stock || 0);
        foot.style.display = '';
        foot.innerHTML = (total ? `<button class="px-btn px-btn-primary" type="button" id="saveBtn"><i class="bi bi-check2-circle"></i>Save ${nf((c.create || 0) + (c.update || 0))} product(s)${c.stock ? ' + stock' : ''}</button>` : '<span class="px-help m-0">Nothing to save.</span>')
            + ((c.error || c.warning) ? `<a class="px-btn px-btn-light" href="${s.urls.issues}"><i class="bi bi-filetype-csv"></i>Download problems</a>` : '')
            + `<button class="px-btn px-btn-light ms-auto" type="button" id="cancelBtn"><i class="bi bi-x-lg"></i>Cancel</button>`;
        $('#saveBtn')?.addEventListener('click', async e => {
            e.currentTarget.disabled = true; e.currentTarget.innerHTML = '<span class="spinner-border"></span>Saving…';
            try { const d = await post(s.urls.apply); state = d.import; render(); run(); }
            catch (err) { alert(err.message); render(); }
        });
        $('#cancelBtn').addEventListener('click', async () => { await post(s.urls.cancel).catch(() => {}); location.reload(); });
    }
    const tile = (cls, n, label) => `<div class="px-tile ${cls}"><b>${nf(n)}</b><span>${esc(label)}</span></div>`;
    const note = (cls, icon, html) => `<div class="px-note ${cls}"><i class="bi ${icon}"></i><div>${html}</div></div>`;

    let tab = null;
    function renderTabs(done) {
        const p = state.preview || {}, issues = state.issues || [];
        const tabs = [];
        if (!done && (p.updates || []).length) tabs.push(['updates', 'Changes', state.counts.update]);
        if (!done && (p.creates || []).length) tabs.push(['creates', 'New products', state.counts.create]);
        if (issues.length) tabs.push(['issues', 'Problems', issues.length + (issues.length >= 300 ? '+' : '')]);
        if (!tabs.length) { $('#tabs').innerHTML = ''; $('#tabBody').innerHTML = ''; return; }
        if (!tab || !tabs.find(t => t[0] === tab)) tab = tabs[0][0];
        $('#tabs').innerHTML = tabs.map(t => `<button type="button" data-tab="${t[0]}" class="${t[0] === tab ? 'active' : ''}">${t[1]}<span class="n">${typeof t[2] === 'number' ? nf(t[2]) : t[2]}</span></button>`).join('');
        $$('#tabs button').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; renderTabs(done); }));
        let html = '';
        if (tab === 'updates') {
            html = `<table class="px-table"><thead><tr><th>Row</th><th>Product</th><th>What changes</th></tr></thead><tbody>` + p.updates.map(u => `<tr><td>${u.row}</td><td><b>${esc(u.title)}</b><div class="px-help m-0">ID ${u.id}</div></td><td>`
                + Object.entries(u.changes).map(([k, v]) => `<span class="px-chg">${esc(k)}: <span class="px-old">${esc(v[0])}</span> → <span class="px-new">${esc(v[1])}</span></span>`).join('')
                + (u.more ? `<span class="px-help">+${u.more} more</span>` : '') + (u.stock ? `<span class="px-chg">Stock: <span class="px-new">${esc(u.stock)}</span></span>` : '') + `</td></tr>`).join('')
                + `</tbody></table>` + (state.counts.update > p.updates.length ? `<div class="px-help p-2">Showing the first ${p.updates.length} of ${nf(state.counts.update)}.</div>` : '');
        } else if (tab === 'creates') {
            html = `<table class="px-table"><thead><tr><th>Row</th><th>Product</th><th>Price</th><th>Category</th><th>Unit</th><th>Stock</th></tr></thead><tbody>` + p.creates.map(u => `<tr><td>${u.row}</td><td><b>${esc(u.title)}</b></td><td>${u.price !== null ? nf(u.price) : ''}</td><td>${esc(u.category || '—')}</td><td>${esc(u.unit || 'Piece')}</td><td>${esc(u.stock || '')}</td></tr>`).join('')
                + `</tbody></table>` + (state.counts.create > p.creates.length ? `<div class="px-help p-2">Showing the first ${p.creates.length} of ${nf(state.counts.create)}.</div>` : '');
        } else {
            html = `<table class="px-table"><thead><tr><th>Row</th><th></th><th>Product</th><th>Problem</th></tr></thead><tbody>` + issues.map(i => `<tr><td>${i.row}</td><td><span class="px-lvl ${i.level}">${i.level === 'error' ? 'Skipped' : 'Note'}</span></td><td>${esc(i.title)}</td><td>${esc(i.message)}</td></tr>`).join('') + `</tbody></table>`;
        }
        $('#tabBody').innerHTML = html;
    }

    @if (request('import'))
        fetch(@json(route('products.spreadsheet.show', (int) request('import'))), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'}).then(r => r.json()).then(d => { if (d.import) { state = d.import; render(); run(); } });
    @endif
})();
</script>
@endsection
