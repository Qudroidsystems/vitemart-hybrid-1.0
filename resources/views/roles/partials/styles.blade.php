{{-- Shared look for the role pages (ported from the CSS Kabba portal). --}}
@once
<style>
.rol-page {
    --rol-primary:  #0f172a;
    --rol-ink:      var(--cb-heading, #0f172a);
    --rol-accent:   #6366f1;
    --rol-accent2:  #8b5cf6;
    --rol-success:  #10b981;
    --rol-warning:  #f59e0b;
    --rol-danger:   #ef4444;
    --rol-info:     #0ea5e9;
    --rol-border:   var(--cb-border, #e2e8f0);
    --rol-surface:  var(--cb-surface, #ffffff);
    --rol-surface2: var(--cb-surface-2, #f8fafc);
    --rol-soft:     #f0f4ff;
    --rol-muted:    var(--cb-muted, #64748b);
    --rol-radius:   14px;
    --rol-shadow:   0 4px 20px rgba(15,23,42,.07);
    --rol-font:     'DM Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-family: var(--rol-font);
}
[data-bs-theme="dark"] .rol-page { --rol-soft: #232a45; --rol-shadow: 0 4px 20px rgba(0,0,0,.35); }




@keyframes slideDown  { from{opacity:0;transform:translateY(-22px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeUp     { from{opacity:0;transform:translateY(14px)}  to{opacity:1;transform:translateY(0)} }
@keyframes scaleIn    { from{opacity:0;transform:scale(.9)}         to{opacity:1;transform:scale(1)} }
@keyframes pulse      { 0%,100%{transform:scale(1)} 50%{transform:scale(1.06)} }
@keyframes fadeIn     { from{opacity:0} to{opacity:1} }

/* ── Hero ────────────────────────────────────────── */
.rol-hero {
    background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 45%,#312e81 100%);
    border-radius:var(--rol-radius); padding:30px 36px; margin-bottom:24px;
    position:relative; overflow:hidden;
    animation:slideDown .5s cubic-bezier(.22,1,.36,1);
}
.rol-hero::before {
    content:''; position:absolute; top:-80px; right:-80px;
    width:260px; height:260px; background:rgba(99,102,241,.14);
    border-radius:50%; animation:pulse 4s ease-in-out infinite;
}
.rol-hero h1 { font-size:22px; font-weight:800; color:#fff; margin:0 0 5px; position:relative; z-index:1; }
.rol-hero p  { font-size:13px; color:rgba(255,255,255,.65); margin:0; position:relative; z-index:1; }
.rol-hero-actions { position:relative; z-index:1; margin-top:18px; display:flex; gap:8px; flex-wrap:wrap; }

/* ── Buttons ─────────────────────────────────────── */
.rol-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; font-family:var(--rol-font); transition:transform .15s,box-shadow .15s; text-decoration:none; }
.rol-btn-primary { background:linear-gradient(135deg,var(--rol-accent),var(--rol-accent2)); color:#fff; box-shadow:0 3px 12px rgba(99,102,241,.3); }
.rol-btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(99,102,241,.4); color:#fff; }
.rol-btn-success { background:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 3px 12px rgba(16,185,129,.3); }
.rol-btn-success:hover { transform:translateY(-1px); color:#fff; }
.rol-btn-danger  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; box-shadow:0 3px 12px rgba(239,68,68,.3); }
.rol-btn-danger:hover  { transform:translateY(-1px); color:#fff; }
.rol-btn-back    { background:#fff; color:var(--rol-primary); border:1.5px solid var(--rol-border); }
.rol-btn-back:hover { background:var(--rol-surface2); color:var(--rol-primary); }

/* ── Cards ───────────────────────────────────────── */
.rol-card {
    background:var(--rol-surface); border:1px solid var(--rol-border);
    border-radius:var(--rol-radius); overflow:hidden;
    box-shadow:var(--rol-shadow);
    animation:fadeUp .4s ease both;
}
.rol-card:nth-child(1){animation-delay:.05s}
.rol-card:nth-child(2){animation-delay:.1s}
.rol-card-header {
    padding:14px 20px; border-bottom:1px solid var(--rol-border);
    background:var(--rol-surface); display:flex; align-items:center; justify-content:space-between;
}
.rol-card-header h5 { font-size:14px; font-weight:700; color:var(--rol-primary); margin:0; }
.rol-card-body { padding:18px 20px; }

/* ── Permission list (sidebar) ───────────────────── */
.perm-list-item {
    display:flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:8px; font-size:12.5px; font-weight:500;
    color:var(--rol-primary); transition:background .12s; margin-bottom:2px;
}
.perm-list-item:hover { background:#f0f4ff; }
.perm-list-item i { color:var(--rol-accent); font-size:13px; flex-shrink:0; }

.edit-role-btn {
    width:100%; display:flex; align-items:center; justify-content:center; gap:6px;
    background:#f0f4ff; color:var(--rol-accent); border:1.5px solid #c7d2fe;
    border-radius:9px; padding:9px; font-size:13px; font-weight:600;
    cursor:pointer; transition:all .15s; margin-bottom:16px;
    font-family:var(--rol-font);
}
.edit-role-btn:hover { background:var(--rol-accent); color:#fff; }

/* ── Users table ─────────────────────────────────── */
#usersDataTable { font-size:13px; }
#usersDataTable thead th {
    background:var(--rol-primary); color:#fff; padding:11px 16px;
    font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    white-space:nowrap; border:none;
}
#usersDataTable tbody td {
    padding:10px 16px; vertical-align:middle;
    border-bottom:1px solid var(--rol-border); transition:background .12s;
}
#usersDataTable tbody tr:hover td { background:#f5f3ff; }
#usersDataTable tbody tr { animation:fadeIn .3s ease both; }

.usr-avatar-sm {
    width:34px; height:34px; border-radius:50%;
    background:linear-gradient(135deg,var(--rol-accent) 0%,var(--rol-accent2) 100%);
    display:inline-flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:700; color:#fff;
    border:2px solid var(--rol-border); flex-shrink:0;
}

.rol-checkbox { accent-color:var(--rol-accent); width:15px; height:15px; cursor:pointer; }

/* ── Remove btn in table ─────────────────────────── */
.remove-user-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:30px; height:30px; border-radius:7px;
    background:#fef2f2; color:#dc2626; border:none; cursor:pointer;
    font-size:13px; transition:all .18s;
}
.remove-user-btn:hover { background:#dc2626; color:#fff; transform:scale(1.1); }

/* ── User cards (add modal) ──────────────────────── */
.user-card-sel {
    border:2px solid var(--rol-border); border-radius:10px;
    padding:12px 14px; cursor:pointer; transition:all .18s;
    display:flex; align-items:center; gap:12px; background:#fff;
    margin-bottom:10px;
}
.user-card-sel:hover { border-color:var(--rol-accent); background:#f5f3ff; transform:translateY(-2px); box-shadow:0 4px 12px rgba(99,102,241,.1); }
.user-card-sel.selected { border-color:var(--rol-accent); background:#f0f4ff; }
.user-card-sel .ucs-info { flex:1; min-width:0; }
.user-card-sel .ucs-name { font-size:13px; font-weight:700; color:var(--rol-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-card-sel .ucs-meta { font-size:11px; color:var(--rol-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-card-sel .ucs-check { flex-shrink:0; }
.user-card-sel .ucs-check input { width:16px; height:16px; accent-color:var(--rol-accent); cursor:pointer; }

/* ── Modal - Improved Scrolling ───────────────────────────────────────── */
.rol-modal .modal-dialog-scrollable .modal-content {
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

.rol-modal .modal-dialog-scrollable .modal-header {
    flex-shrink: 0;
}

.rol-modal .modal-dialog-scrollable .modal-body {
    flex: 1;
    overflow-y: auto;
    min-height: 200px;
    max-height: calc(85vh - 130px);
}

.rol-modal .modal-dialog-scrollable .modal-footer {
    flex-shrink: 0;
}

.rol-modal .modal-content {
    border:none; border-radius:18px; overflow:hidden;
    box-shadow:0 24px 64px rgba(15,23,42,.2);
    font-family:var(--rol-font);
    animation:scaleIn .25s cubic-bezier(.22,1,.36,1);
}
.rol-modal .modal-header {
    background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 60%,#312e81 100%);
    padding:20px 24px; border:none;
}
.rol-modal .modal-title { color:#fff; font-weight:700; font-size:16px; }
.rol-modal .modal-header .btn-close { filter:invert(1); opacity:.8; }
.rol-modal .modal-body  { padding:22px 24px; }
.rol-modal .modal-footer { padding:14px 24px; border-top:1px solid var(--rol-border); background:var(--rol-surface2); }

.rol-form-label { font-size:11.5px; font-weight:700; color:var(--rol-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; display:block; }
.rol-form-control { border:1.5px solid var(--rol-border); border-radius:9px; padding:9px 13px; font-size:13px; font-family:var(--rol-font); width:100%; transition:border-color .2s,box-shadow .2s; }
.rol-form-control:focus { border-color:var(--rol-accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); outline:none; }

/* ── Permission table (edit modal) ──────────────── */
.perm-table { width:100%; border-collapse:separate; border-spacing:0; }
.perm-table thead th {
    background:var(--rol-primary); color:#fff; padding:10px 14px;
    font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
    position: sticky;
    top: 0;
    z-index: 10;
}
.perm-table thead th:first-child { border-radius:8px 0 0 0; }
.perm-table thead th:last-child  { border-radius:0 8px 0 0; }
.perm-table tbody tr:hover td { background:#f5f3ff; }
.perm-table tbody td { padding:8px 14px; border-bottom:1px solid #f1f5f9; font-size:13px; vertical-align:middle; }
.perm-table .perm-group { font-weight:700; color:var(--rol-primary); font-size:12.5px; }
.perm-check { accent-color:var(--rol-accent); width:15px; height:15px; cursor:pointer; }

/* Responsive table handling */
@media (max-width: 768px) {
    .perm-table {
        min-width: 600px;
    }
    .perm-table .perm-group {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 5;
    }
    .perm-table tr:hover .perm-group {
        background: #f5f3ff;
    }
}

/* ── Nav tabs ────────────────────────────────────── */
.rol-nav-tabs { border-bottom:2px solid var(--rol-border); margin-bottom:16px; display:flex; gap:4px; }
.rol-nav-tabs .nav-link {
    border:none; background:none; color:var(--rol-muted);
    font-size:13px; font-weight:600; padding:10px 16px; border-radius:8px 8px 0 0;
    cursor:pointer; transition:all .15s; font-family:var(--rol-font);
    position:relative; display:flex; align-items:center; gap:6px;
}
.rol-nav-tabs .nav-link:hover { color:var(--rol-accent); background:#f0f4ff; }
.rol-nav-tabs .nav-link.active { color:var(--rol-accent); background:#f0f4ff; }
.rol-nav-tabs .nav-link.active::after {
    content:''; position:absolute; bottom:-2px; left:0; right:0;
    height:2px; background:var(--rol-accent); border-radius:2px 2px 0 0;
}

/* ── Delete confirm modal ────────────────────────── */
.del-icon-wrap {
    width:70px; height:70px; border-radius:50%;
    background:#fef2f2; border:3px solid #fecaca;
    display:flex; align-items:center; justify-content:center;
    font-size:30px; color:var(--rol-danger); margin:0 auto 16px;
    animation:pulse 1.5s ease infinite;
}

/* ── DataTable overrides ─────────────────────────── */
.dataTables_wrapper .dataTables_filter input {
    border:1.5px solid var(--rol-border); border-radius:9px; padding:7px 14px;
    font-size:13px; margin-left:8px; font-family:var(--rol-font);
}
.dataTables_wrapper .dataTables_filter input:focus {
    border-color:var(--rol-accent); outline:none;
    box-shadow:0 0 0 3px rgba(99,102,241,.1);
}
.dataTables_wrapper .dataTables_info { font-size:12px; color:var(--rol-muted); }
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius:7px !important; font-size:13px !important; padding:4px 10px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background:var(--rol-accent) !important; border-color:var(--rol-accent) !important; color:#fff !important;
}

/* ── Info banner ─────────────────────────────────── */
.rol-info-banner { background:#eff6ff; border:1px solid #bfdbfe; border-radius:9px; padding:10px 14px; font-size:12.5px; color:#1d4ed8; margin-bottom:14px; }

/* Sticky permission header */
.permission-section-header {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 10;
    padding: 8px 0 12px 0;
    margin-top: -8px;
}

/* ── Role cards (index) ── */
/* ── Role cards ──────────────────────────────────── */
.rol-rcard {
    background:var(--rol-surface);
    border:1px solid var(--rol-border);
    border-radius:var(--rol-radius);
    overflow:hidden;
    box-shadow:var(--rol-shadow);
    transition:transform .2s, box-shadow .2s, border-color .2s;
    animation:fadeUp .4s ease both;
    display:flex; flex-direction:column;
}
.rol-rcard:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(15,23,42,.12); border-color:#c7d2fe; }
.rol-rcard:nth-child(1){animation-delay:.05s}
.rol-rcard:nth-child(2){animation-delay:.10s}
.rol-rcard:nth-child(3){animation-delay:.15s}
.rol-rcard:nth-child(4){animation-delay:.20s}
.rol-rcard:nth-child(5){animation-delay:.25s}

.rol-rcard-top {
    background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 80%,#312e81 100%);
    padding:20px 18px 16px;
    position:relative; overflow:hidden;
}
.rol-rcard-top::before {
    content:''; position:absolute; top:-30px; right:-30px;
    width:100px; height:100px; background:rgba(99,102,241,.18);
    border-radius:50%;
}
.rol-rcard-icon {
    width:46px; height:46px; border-radius:12px;
    background:rgba(255,255,255,.14);
    display:flex; align-items:center; justify-content:center;
    font-size:20px; color:#fff; margin-bottom:12px;
    backdrop-filter:blur(4px);
    position:relative; z-index:1;
}
.rol-rcard-name {
    font-size:16px; font-weight:800; color:#fff;
    position:relative; z-index:1; margin-bottom:4px;
}
.rol-rcard-count {
    font-size:12px; color:rgba(255,255,255,.65);
    position:relative; z-index:1;
    display:flex; align-items:center; gap:5px;
}
.rol-rcard-count strong { color:rgba(255,255,255,.9); font-size:18px; }

.rol-rcard-body { padding:14px 18px; flex:1; }
.rol-perm-tag {
    display:inline-flex; align-items:center; gap:4px;
    background:#f0f4ff; color:#4338ca; border:1px solid #c7d2fe;
    border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600;
    margin:2px; transition:background .15s;
}
.rol-perm-tag:hover { background:#e0e7ff; }
.rol-perm-more { color:var(--rol-accent); font-size:11.5px; font-weight:600; text-decoration:none; }
.rol-perm-more:hover { text-decoration:underline; }

.rol-rcard-footer {
    padding:12px 18px;
    border-top:1px solid var(--rol-border);
    background:var(--rol-surface2);
    display:flex; align-items:center; justify-content:space-between;
}
.rol-users-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;
    border-radius:20px; padding:3px 12px; font-size:11.5px; font-weight:600;
}
.rol-view-link { font-size:12.5px; font-weight:600; color:var(--rol-accent); text-decoration:none; display:flex; align-items:center; gap:4px; }
.rol-view-link:hover { color:var(--rol-accent2); }

/* ── Dropdown ────────────────────────────────────── */
.rol-dropdown .dropdown-toggle {
    background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25);
    color:#fff; border-radius:8px; padding:5px 9px; font-size:14px;
    transition:background .15s;
}
.rol-dropdown .dropdown-toggle:hover { background:rgba(255,255,255,.25); }
.rol-dropdown .dropdown-menu {
    border-radius:10px; border:1px solid var(--rol-border);
    box-shadow:0 8px 24px rgba(15,23,42,.12); padding:6px;
    animation:scaleIn .15s ease;
}
.rol-dropdown .dropdown-item {
    border-radius:7px; font-size:13px; font-weight:500; padding:8px 12px;
    display:flex; align-items:center; gap:8px; transition:background .12s;
}
.rol-dropdown .dropdown-item:hover { background:#f0f4ff; color:var(--rol-accent); }
.rol-dropdown .dropdown-item.text-danger:hover { background:#fef2f2; color:var(--rol-danger); }


/* ── Alerts ──────────────────────────────────────── */
.rol-alert-success { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:9px; padding:10px 14px; font-size:13px; color:#16a34a; }
.rol-alert-danger  { background:#fef2f2; border:1px solid #fecaca; border-radius:9px; padding:10px 14px; font-size:13px; color:#b91c1c; }

/* ── Empty state ─────────────────────────────────── */
.rol-empty {
    text-align:center; padding:60px 24px;
    animation:fadeUp .5s ease;
}
.rol-empty-icon { font-size:56px; opacity:.2; margin-bottom:14px; display:block; }
.rol-empty h4   { font-weight:700; color:var(--rol-muted); font-size:17px; }
.rol-empty p    { font-size:13px; color:var(--rol-muted); }

.rol-role-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, var(--rol-accent), var(--rol-accent2));
    color: #fff; border-radius: 10px; padding: 10px 16px;
    font-size: 14px; font-weight: 700;
    box-shadow: 0 3px 12px rgba(99,102,241,.3);
}


/* Text colours that must follow the theme (Kabba's are light-only) */
.rol-page .rol-card-header h5, .rol-page .perm-list-item, .rol-page .user-card-sel .ucs-name,
.rol-page .perm-table .perm-group { color: var(--rol-ink); }
.rol-page .user-card-sel { background: var(--rol-surface); }
.rol-page .rol-btn-back { background: var(--rol-surface); color: var(--rol-ink); }
.rol-page .perm-list-item:hover, .rol-page .user-card-sel.selected, .rol-page .edit-role-btn,
.rol-page .rol-nav-tabs .nav-link:hover, .rol-page .rol-nav-tabs .nav-link.active { background: var(--rol-soft); }
.rol-page #usersDataTable tbody tr:hover td { background: var(--rol-soft); }
.rol-page .rol-perm-tag { background: var(--rol-soft); }
.rol-page .rol-hero h1 { font-family: var(--cb-font-display, inherit); }

/* Users table search + paging (replaces DataTables, which ViteMart doesn't ship) */
.rol-tools { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:12px 20px; border-bottom:1px solid var(--rol-border); }
.rol-tools .rol-search { position:relative; flex:1 1 220px; max-width:320px; }
.rol-tools .rol-search input { width:100%; border:1.5px solid var(--rol-border); border-radius:9px; padding:7px 12px 7px 32px; font-size:13px; background:var(--rol-surface); color:inherit; }
.rol-tools .rol-search input:focus { border-color:var(--rol-accent); outline:none; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.rol-tools .rol-search i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--rol-muted); }
.rol-pager { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:12px 20px; font-size:12px; color:var(--rol-muted); }
.rol-pager .pg { display:flex; gap:4px; }
.rol-pager button { border:1px solid var(--rol-border); background:var(--rol-surface); color:inherit; border-radius:7px; min-width:32px; height:30px; font-size:12.5px; font-weight:600; }
.rol-pager button.active { background:var(--rol-accent); border-color:var(--rol-accent); color:#fff; }
.rol-pager button:disabled { opacity:.45; }
.ucs-roles { display:flex; flex-wrap:wrap; gap:3px; margin-top:3px; }
.ucs-roles span { font-size:9.5px; font-weight:700; background:var(--rol-surface2); color:var(--rol-muted); border:1px solid var(--rol-border); border-radius:10px; padding:0 6px; }
.usr-avatar-sm img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.rol-modal-search { position:relative; margin-bottom:12px; }
.rol-modal-search input { width:100%; border:1.5px solid var(--rol-border); border-radius:9px; padding:8px 12px 8px 34px; font-size:13px; }
.rol-modal-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--rol-muted); }
/* Keep the picker's search bar covering the modal's top padding while scrolling */
.rol-modal .modal-body .pm-toolbar { top: -22px; margin-top: -22px; padding-top: 22px; padding-bottom: 10px; background: var(--rol-surface); }
@media (prefers-reduced-motion: reduce) { .rol-page *, .rol-page *::before { animation: none !important; } }
</style>
@endonce
