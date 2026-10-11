<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS · {{ $store->store_name ?? 'Supermarket' }}</title>
    @if ($store?->logo)<link rel="icon" href="{{ $store->getLogoUrlAttribute() }}">@endif
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet">
    <style>
        :root {
            --pos-bg: #eef1f6;
            --pos-panel: #ffffff;
            --pos-ink: #1d2433;
            --pos-muted: #6b7385;
            --pos-line: #e3e7ef;
            --pos-accent: #0f766e;
            --pos-accent-ink: #ffffff;
            --pos-accent-soft: #e6f4f2;
            --pos-warn: #b45309;
            --pos-danger: #c0392b;
            --pos-top: #111827;
        }
        html, body { height: 100%; }
        body { margin: 0; background: var(--pos-bg); color: var(--pos-ink); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 14px; overflow: hidden; }
        .pos { display: grid; grid-template-rows: 52px 1fr; height: 100vh; }
        .pos-top { background: var(--pos-top); color: #e5e7eb; display: flex; align-items: center; gap: 10px; padding: 0 12px; }
        .pos-top .brand { font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
        .pos-top .chip { background: rgba(255,255,255,.08); border-radius: 999px; padding: 4px 10px; font-size: 12px; white-space: nowrap; }
        .pos-top .chip.ok { background: rgba(16,185,129,.18); color: #a7f3d0; }
        .pos-top .chip.warn { background: rgba(245,158,11,.2); color: #fde68a; }
        .pos-top .tbtn { background: transparent; border: 1px solid rgba(255,255,255,.15); color: #e5e7eb; border-radius: 8px; padding: 5px 10px; font-size: 13px; white-space: nowrap; }
        .pos-top .tbtn:hover { background: rgba(255,255,255,.08); }
        .pos-top .tbtn kbd { background: rgba(255,255,255,.12); color: #fff; font-size: 10px; padding: 1px 4px; margin-left: 4px; }
        .pos-main { display: grid; grid-template-columns: minmax(0, 1fr) 440px; min-height: 0; }
        .pos-left { display: flex; flex-direction: column; min-height: 0; padding: 12px; gap: 10px; }
        .pos-right { background: var(--pos-panel); border-left: 1px solid var(--pos-line); display: flex; flex-direction: column; min-height: 0; }

        .scan-wrap { position: relative; }
        .scan-wrap .bi-upc-scan { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 20px; color: var(--pos-muted); }
        #scan { height: 54px; font-size: 18px; padding-left: 46px; border-radius: 12px; border: 2px solid var(--pos-line); }
        #scan:focus { border-color: var(--pos-accent); box-shadow: 0 0 0 .2rem rgba(15,118,110,.15); }
        .scan-hint { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--pos-muted); }
        #results { position: absolute; left: 0; right: 0; top: 58px; z-index: 30; background: #fff; border-radius: 12px; box-shadow: 0 12px 32px rgba(17,24,39,.18); max-height: 60vh; overflow: auto; display: none; }
        #results .r { display: flex; gap: 10px; align-items: center; padding: 9px 12px; border-bottom: 1px solid var(--pos-line); cursor: pointer; }
        #results .r.active, #results .r:hover { background: var(--pos-accent-soft); }
        #results .r img, .tile img { object-fit: cover; }

        .cats { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; scrollbar-width: thin; }
        .cats button { border: 1px solid var(--pos-line); background: #fff; border-radius: 999px; padding: 6px 14px; white-space: nowrap; font-weight: 500; color: var(--pos-ink); }
        .cats button.active { background: var(--pos-ink); color: #fff; border-color: var(--pos-ink); }
        .grid { flex: 1; overflow: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; align-content: start; padding-bottom: 10px; }
        .tile { background: #fff; border: 1px solid var(--pos-line); border-radius: 12px; padding: 8px; cursor: pointer; display: flex; flex-direction: column; gap: 4px; text-align: left; transition: transform .06s; }
        .tile:active { transform: scale(.98); }
        .tile:hover { border-color: var(--pos-accent); }
        .tile .ph { height: 74px; border-radius: 8px; background: #f3f5f9; display: flex; align-items: center; justify-content: center; color: #b4bccb; font-size: 26px; overflow: hidden; }
        .tile .ph img { width: 100%; height: 100%; }
        .tile .t { font-weight: 600; font-size: 13px; line-height: 1.2; height: 2.4em; overflow: hidden; }
        .tile .p { font-weight: 700; color: var(--pos-accent); }
        .tile .s { font-size: 11px; color: var(--pos-muted); }
        .tile.out { opacity: .55; }

        .cust { padding: 10px 12px; border-bottom: 1px solid var(--pos-line); display: flex; gap: 8px; align-items: center; }
        .cust .who { flex: 1; min-width: 0; }
        .cust .who .n { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cust .who .m { font-size: 12px; color: var(--pos-muted); }
        .cart { flex: 1; overflow: auto; }
        .line { padding: 9px 12px; border-bottom: 1px solid var(--pos-line); display: grid; grid-template-columns: 1fr auto; gap: 4px 8px; cursor: pointer; }
        .line.sel { background: var(--pos-accent-soft); box-shadow: inset 3px 0 0 var(--pos-accent); }
        .line .nm { font-weight: 600; line-height: 1.25; }
        .line .vari { font-size: 12px; color: var(--pos-muted); }
        .line .tot { font-weight: 700; text-align: right; white-space: nowrap; }
        .line .ctl { grid-column: 1 / -1; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .qtybox { display: inline-flex; border: 1px solid var(--pos-line); border-radius: 8px; overflow: hidden; }
        .qtybox button { border: 0; background: #f3f5f9; width: 30px; font-size: 16px; line-height: 1; }
        .qtybox input { width: 62px; border: 0; text-align: center; font-weight: 600; }
        .line select { width: auto; max-width: 150px; }
        .line .disc { font-size: 12px; color: var(--pos-danger); }
        .line .warn { font-size: 12px; color: var(--pos-warn); }
        .empty-cart { text-align: center; color: var(--pos-muted); padding: 60px 20px; }
        .empty-cart i { font-size: 48px; display: block; margin-bottom: 8px; opacity: .5; }
        .totals { border-top: 1px solid var(--pos-line); padding: 10px 14px; }
        .totals .row2 { display: flex; justify-content: space-between; color: var(--pos-muted); }
        .totals .grand { display: flex; justify-content: space-between; align-items: baseline; font-size: 28px; font-weight: 800; color: var(--pos-ink); margin-top: 4px; }
        .actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; padding: 0 12px 8px; }
        .actions .btn { font-size: 12px; padding: 8px 4px; }
        .pay { margin: 0 12px 12px; height: 62px; font-size: 22px; font-weight: 800; border-radius: 12px; background: var(--pos-accent); color: var(--pos-accent-ink); border: 0; }
        .pay:disabled { opacity: .45; }
        .pay kbd { font-size: 11px; background: rgba(255,255,255,.2); }

        .paygrid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .due { font-size: 34px; font-weight: 800; }
        .methods { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
        .methods button { border: 2px solid var(--pos-line); background: #fff; border-radius: 10px; padding: 10px 4px; font-weight: 600; }
        .methods button.active { border-color: var(--pos-accent); background: var(--pos-accent-soft); color: var(--pos-accent); }
        .quick { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .quick button { border: 1px solid var(--pos-line); background: #f8fafc; border-radius: 8px; padding: 10px 4px; font-weight: 600; }
        #tender { font-size: 26px; font-weight: 700; height: 58px; text-align: right; }
        .paylist .pl { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--pos-line); }
        .change { font-size: 22px; font-weight: 800; color: var(--pos-accent); }
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .keypad button { height: 52px; font-size: 20px; font-weight: 600; border: 1px solid var(--pos-line); background: #fff; border-radius: 10px; }
        .offline { display: none; }
        .vm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em; }
        body.is-offline .offline { display: inline-block; }

        @media (max-width: 1100px) {
            .pos-main { grid-template-columns: minmax(0, 1fr) 380px; }
            .pos-top .hide-md { display: none; }
        }
        @media (max-width: 820px) {
            body { overflow: auto; }
            .pos { height: auto; min-height: 100vh; }
            .pos-main { grid-template-columns: 1fr; }
            .grid { max-height: 40vh; }
            .pos-right { border-left: 0; border-top: 1px solid var(--pos-line); }
            .cart { max-height: none; }
            .paygrid { grid-template-columns: 1fr; }
            .pos-top .brand { max-width: 120px; }
        }
        @media print { body * { visibility: hidden; } }
    </style>
</head>
<body>
<div class="pos">
    {{-- ===================== TOP BAR ===================== --}}
    <div class="pos-top">
        <span class="brand"><i class="bi bi-shop me-1"></i>{{ $store->store_name ?? 'Supermarket' }}</span>
        <span class="chip hide-md"><i class="bi bi-person me-1"></i>{{ $config['user']['name'] }}</span>
        @if (count($config['registers']))
            <span class="chip" id="regChip" style="cursor:pointer" title="Which till is this?"><i class="bi bi-display me-1"></i>{{ $config['register'] ? 'Till ' . $config['register']['code'] : 'Choose till' }}</span>
        @endif
        <span class="chip" id="shiftChip">…</span>
        <span class="chip warn offline"><i class="bi bi-wifi-off me-1"></i>Offline</span>
        <span class="flex-grow-1"></span>
        <span class="chip hide-md" id="clock"></span>
        <button class="tbtn" id="btnHeld" title="Held sales (F8)"><i class="bi bi-pause-circle me-1"></i>Held <span class="badge bg-warning text-dark" id="heldCount" style="display:none">0</span><kbd>F8</kbd></button>
        <button class="tbtn" id="btnRecall" title="Find a receipt to reprint, void or refund"><i class="bi bi-receipt me-1"></i><span class="hide-md">Receipts</span></button>
        <div class="dropdown">
            <button class="tbtn dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-list"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" id="mToday"><i class="bi bi-graph-up me-2"></i>Today's sales</a></li>
                <li><a class="dropdown-item" href="{{ $config['routes']['transactions'] }}"><i class="bi bi-list-ul me-2"></i>All transactions</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" id="mOpenShift"><i class="bi bi-unlock me-2"></i>Open shift</a></li>
                <li><a class="dropdown-item" href="#" id="mCashIn"><i class="bi bi-box-arrow-in-down me-2"></i>Cash in</a></li>
                <li><a class="dropdown-item" href="#" id="mCashOut"><i class="bi bi-box-arrow-up me-2"></i>Cash out / payout</a></li>
                <li><a class="dropdown-item" href="#" id="mCloseShift"><i class="bi bi-lock me-2"></i>Close shift (Z-report)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" id="mShortcuts"><i class="bi bi-keyboard me-2"></i>Keyboard shortcuts</a></li>
                @can('dashboard')<li><a class="dropdown-item" href="{{ $config['routes']['dashboard'] }}"><i class="bi bi-speedometer2 me-2"></i>Back to dashboard</a></li>@endcan
                <li>
                    <form method="POST" action="{{ $config['routes']['logout'] }}">@csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    <div class="pos-main">
        {{-- ===================== LEFT: SCAN + PRODUCTS ===================== --}}
        <div class="pos-left">
            <div class="scan-wrap">
                <i class="bi bi-upc-scan"></i>
                <input id="scan" class="form-control" autocomplete="off" placeholder="Scan barcode or type product name…  (3* then scan = 3 pieces)" autofocus>
                <span class="scan-hint d-none d-md-inline"><kbd>F2</kbd></span>
                <div id="results"></div>
            </div>
            <div class="cats" id="cats">
                <button class="active" data-cat="">Popular</button>
                @foreach ($categories as $c)
                    <button data-cat="{{ $c->id }}">{{ $c->name }}</button>
                @endforeach
            </div>
            <div class="grid" id="grid"></div>
        </div>

        {{-- ===================== RIGHT: CART ===================== --}}
        <div class="pos-right">
            <div class="cust">
                <i class="bi bi-person-circle fs-3 text-muted"></i>
                <div class="who">
                    <div class="n" id="custName">Walk-in customer</div>
                    <div class="m" id="custMeta">Retail prices</div>
                </div>
                <button class="btn btn-sm btn-light" id="btnCustomer"><i class="bi bi-search me-1"></i>Customer</button>
                <button class="btn btn-sm btn-light" id="btnCustClear" style="display:none" title="Remove customer"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="cart" id="cart"></div>
            <div class="totals">
                <div class="row2"><span>Items</span><span id="tItems">0</span></div>
                <div class="row2"><span>Subtotal</span><span id="tSub">0</span></div>
                <div class="row2" id="tDiscRow" style="display:none"><span>Discount <a href="#" id="rmDisc" class="text-danger">remove</a></span><span id="tDisc" class="text-danger">0</span></div>
                <div class="row2" id="tPtsRow" style="display:none"><span>Points redeemed</span><span id="tPts" class="text-danger">0</span></div>
                <div class="grand"><span>Total</span><span id="tTotal">0</span></div>
            </div>
            <div class="actions">
                <button class="btn btn-light" id="aHold" title="F4"><i class="bi bi-pause me-1"></i>Hold</button>
                <button class="btn btn-light" id="aDisc" title="F6"><i class="bi bi-percent me-1"></i>Discount</button>
                <button class="btn btn-light" id="aNote"><i class="bi bi-chat-left-text me-1"></i>Note</button>
                <button class="btn btn-light text-danger" id="aClear"><i class="bi bi-trash me-1"></i>Clear</button>
            </div>
            <button class="pay" id="btnPay" disabled>PAY <span id="payAmt"></span> <kbd>F9</kbd></button>
        </div>
    </div>
</div>

{{-- ===================== PAYMENT MODAL ===================== --}}
<div class="modal fade" id="payModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Take payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="paygrid">
                    <div>
                        <div class="text-muted">Amount due</div>
                        <div class="due" id="pDue">0</div>
                        <div class="text-muted mt-1">Still to pay: <strong id="pRemain">0</strong></div>
                        <div class="paylist mt-3" id="pList"></div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <span class="text-muted">Change</span>
                            <span class="change" id="pChange">0</span>
                        </div>
                        <div class="mt-3" id="pointsBox" style="display:none">
                            <label class="form-label fs-sm mb-1">Redeem loyalty points (<span id="pPts">0</span> available)</label>
                            <input type="number" min="0" class="form-control form-control-sm" id="redeem" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <div class="methods mb-2">
                            <button data-m="cash" class="active"><i class="bi bi-cash d-block fs-4"></i>Cash</button>
                            <button data-m="card"><i class="bi bi-credit-card d-block fs-4"></i>Card</button>
                            <button data-m="transfer"><i class="bi bi-bank d-block fs-4"></i>Transfer</button>
                            <button data-m="credit"><i class="bi bi-journal-text d-block fs-4"></i>Credit</button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text">{{ $config['currency'] }}</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="tender" placeholder="0.00">
                        </div>
                        <input type="text" class="form-control form-control-sm mb-2" id="payRef" placeholder="Reference (card slip / transfer ID)" style="display:none">
                        @if ($config['transferNeedsConfirmation'])
                            <div class="form-check mb-2 fs-sm" id="payConfirmedBox" style="display:none">
                                @if ($config['can']['confirm_payments'])
                                    <input class="form-check-input" type="checkbox" id="payConfirmed">
                                    <label class="form-check-label" for="payConfirmed">I have seen this money in the bank account</label>
                                @else
                                    <span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>A supervisor confirms transfers after checking the bank.</span>
                                @endif
                            </div>
                        @endif
                        <div class="quick mb-2" id="quick"></div>
                        <div class="keypad mb-2" id="keypad">
                            @foreach ([7,8,9,4,5,6,1,2,3,'00',0,'⌫'] as $k)<button type="button" data-k="{{ $k }}">{{ $k }}</button>@endforeach
                        </div>
                        <button class="btn btn-outline-primary w-100" id="addPayment"><i class="bi bi-plus-lg me-1"></i>Add this payment (split)</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Back</button>
                <button class="btn btn-success btn-lg px-5" id="completeSale"><span class="spinner-border spinner-border-sm me-1 d-none"></span>Complete sale <kbd>Enter</kbd></button>
            </div>
        </div>
    </div>
</div>

{{-- ===================== RECEIPT / DONE MODAL ===================== --}}
<div class="modal fade" id="doneModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size:56px"></i>
                <h4 class="mt-2 mb-1">Sale complete</h4>
                <div class="text-muted" id="dRef"></div>
                <div class="display-6 fw-bold mt-3" id="dChange"></div>
                <div class="text-muted">change</div>
            </div>
            <div class="modal-footer justify-content-center">
                <button class="btn btn-light" id="dPrint"><i class="bi bi-printer me-1"></i>Print receipt <kbd>P</kbd></button>
                <button class="btn btn-primary px-4" id="dNew">New sale <kbd>Enter</kbd></button>
            </div>
        </div>
    </div>
</div>

{{-- ===================== CUSTOMER MODAL ===================== --}}
<div class="modal fade" id="custModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Choose customer</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input class="form-control mb-2" id="custSearch" placeholder="Name, phone or loyalty card">
                <div class="list-group" id="custList"></div>
                <hr>
                <details>
                    <summary class="fw-semibold">New customer</summary>
                    <div class="row g-2 mt-1">
                        <div class="col-6"><input class="form-control form-control-sm" id="ncFirst" placeholder="First name"></div>
                        <div class="col-6"><input class="form-control form-control-sm" id="ncLast" placeholder="Last name"></div>
                        <div class="col-12"><input class="form-control form-control-sm" id="ncPhone" placeholder="Phone number"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary w-100" id="ncSave">Add customer</button></div>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

{{-- ===================== RECALL RECEIPT MODAL ===================== --}}
<div class="modal fade" id="recallModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Receipts – reprint, void or refund</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input class="form-control" id="recallId" placeholder="Scan the receipt barcode or type the receipt number">
                    <button class="btn btn-primary" id="recallGo">Find</button>
                </div>
                <div id="recallToday"></div>
                <div id="recallOrder"></div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== HELD SALES MODAL ===================== --}}
<div class="modal fade" id="heldModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Held sales</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="list-group" id="heldList"></div></div>
        </div>
    </div>
</div>

<iframe id="printFrame" style="position:absolute;width:0;height:0;border:0" title="receipt"></iframe>

<script src="{{ asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('theme/layouts/assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>
@include('layouts.partials.vm-helpers')
<script>
    // Keep the till's files on this PC (works without internet; see /pos-sw.js).
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
        navigator.serviceWorker.register('{{ asset('pos-sw.js') }}', {scope: '{{ url('/pos') }}'}).catch(() => {});
    }
</script>
<script>
(function () {
    'use strict';
    const CFG = @json($config);
    const R = CFG.routes;
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const M = n => VM.money(n);
    const round2 = n => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    const STORE_KEY = 'pos_cart_' + CFG.user.id;

    // ------------------------------------------------------------------ state
    let state = {
        lines: [],          // {uid, item, pack_id, qty, price, discount_type, discount_value, overridden}
        customer: null,
        discount: null,     // {type, value}
        note: '',
        heldId: null,
        selected: null,
    };
    let authToken = null;
    let payments = [];
    let method = 'cash';
    let lastOrder = null;
    let shift = null;
    let qtyMultiplier = null;

    function save() { try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch (e) {} }
    function load() {
        try { const s = JSON.parse(localStorage.getItem(STORE_KEY) || 'null'); if (s && Array.isArray(s.lines)) state = Object.assign(state, s); } catch (e) {}
    }

    // ------------------------------------------------------------------ helpers
    const packOf = l => (l.item.packs || []).find(p => String(p.id) === String(l.pack_id)) || (l.item.packs || [])[0] || {price: l.item.price, qty: 1, name: 'Piece'};
    function unitPrice(l) { return l.overridden ? Number(l.price) : Number(packOf(l).price); }
    function lineGross(l) { return round2(unitPrice(l) * Number(l.qty)); }
    function lineDiscount(l) {
        if (!l.discount_value) return 0;
        const g = lineGross(l);
        return round2(l.discount_type === 'fixed' ? Math.min(g, l.discount_value) : g * Math.min(100, l.discount_value) / 100);
    }
    function lineNet(l) { return round2(lineGross(l) - lineDiscount(l)); }
    function totals() {
        const sub = round2(state.lines.reduce((a, l) => a + lineNet(l), 0));
        let disc = 0;
        if (state.discount && state.discount.value) {
            disc = state.discount.type === 'fixed' ? Math.min(sub, state.discount.value) : sub * Math.min(100, state.discount.value) / 100;
        }
        disc = round2(disc);
        const pts = Number($('#redeem')?.value || 0);
        const ptsValue = state.customer && pts > 0 ? round2(Math.min(pts, state.customer.points) / (CFG.redeemRate || 100)) : 0;
        const total = round2(Math.max(0, sub - disc - ptsValue));
        const items = state.lines.reduce((a, l) => a + Number(l.qty), 0);
        return {sub, disc, ptsValue, total, items};
    }
    function beep(ok = true) {
        try {
            const ctx = beep.ctx || (beep.ctx = new (window.AudioContext || window.webkitAudioContext)());
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = ok ? 1150 : 220; o.type = ok ? 'sine' : 'square';
            g.gain.value = .06; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? .07 : .25));
        } catch (e) {}
    }

    // ------------------------------------------------------------------ render cart
    function render() {
        const cart = $('#cart');
        if (!state.lines.length) {
            cart.innerHTML = `<div class="empty-cart"><i class="bi bi-cart3"></i>Scan an item to start a sale</div>`;
        } else {
            cart.innerHTML = state.lines.map(l => {
                const pk = packOf(l);
                const packs = (l.item.packs || []);
                const stockBase = Number(l.item.stock);
                const need = Number(l.qty) * Number(pk.qty || 1);
                const low = l.item.track_stock && need > stockBase;
                const disc = lineDiscount(l);
                return `<div class="line ${state.selected === l.uid ? 'sel' : ''}" data-uid="${l.uid}">
                    <div>
                        <div class="nm">${VM.esc(l.item.title)}</div>
                        <div class="vari">${l.item.variant ? VM.esc(l.item.variant) + ' · ' : ''}${M(unitPrice(l))} / ${VM.esc(pk.name || 'unit')}${l.overridden ? ' <span class="badge bg-warning text-dark">price changed</span>' : ''}${l.item.on_promo ? ' <span class="badge bg-danger">promo</span>' : ''}</div>
                    </div>
                    <div class="tot">${M(lineNet(l))}</div>
                    <div class="ctl">
                        <div class="qtybox">
                            <button type="button" data-act="dec">−</button>
                            <input type="number" step="${l.item.is_weighted ? '0.001' : '1'}" min="0" value="${l.qty}" data-act="qty">
                            <button type="button" data-act="inc">+</button>
                        </div>
                        ${packs.length > 1 ? `<select class="form-select form-select-sm" data-act="pack">${packs.map(p => `<option value="${p.id}" ${String(p.id) === String(l.pack_id) ? 'selected' : ''}>${VM.esc(p.name)}${p.qty != 1 ? ' ×' + VM.qty(p.qty) : ''} – ${M(p.price)}</option>`).join('')}</select>` : ''}
                        <button type="button" class="btn btn-sm btn-light" data-act="disc" title="Line discount"><i class="bi bi-percent"></i></button>
                        <button type="button" class="btn btn-sm btn-light" data-act="price" title="Change price"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-danger ms-auto" data-act="rm" title="Remove (Del)"><i class="bi bi-x-lg"></i></button>
                        ${disc ? `<span class="disc w-100">Discount −${M(disc)}${l.discount_type === 'percent' ? ' (' + l.discount_value + '%)' : ''}</span>` : ''}
                        ${low ? `<span class="warn w-100"><i class="bi bi-exclamation-triangle"></i> Only ${VM.qty(stockBase)} ${VM.esc(l.item.base_unit)} in stock</span>` : ''}
                    </div>
                </div>`;
            }).join('');
        }
        const t = totals();
        $('#tItems').textContent = VM.qty(t.items);
        $('#tSub').textContent = M(t.sub);
        $('#tDiscRow').style.display = t.disc ? '' : 'none';
        $('#tDisc').textContent = '−' + M(t.disc) + (state.discount && state.discount.type === 'percent' ? ` (${state.discount.value}%)` : '');
        $('#tPtsRow').style.display = t.ptsValue ? '' : 'none';
        $('#tPts').textContent = '−' + M(t.ptsValue);
        $('#tTotal').textContent = M(t.total);
        $('#payAmt').textContent = state.lines.length ? M(t.total) : '';
        $('#btnPay').disabled = !state.lines.length;

        // customer
        if (state.customer) {
            $('#custName').textContent = state.customer.name;
            $('#custMeta').textContent = [state.customer.phone, state.customer.type === 'wholesale' ? 'Wholesale prices' : null, state.customer.points ? state.customer.points + ' pts' : null].filter(Boolean).join(' · ');
            $('#btnCustClear').style.display = '';
        } else {
            $('#custName').textContent = 'Walk-in customer';
            $('#custMeta').textContent = 'Retail prices';
            $('#btnCustClear').style.display = 'none';
        }
        save();
    }

    // ------------------------------------------------------------------ add items
    function addItem(item, qty = null, packId = null) {
        qty = qty ?? (item.qty || 1);
        packId = packId ?? item.pack_id ?? (item.packs && item.packs[0] ? item.packs[0].id : null);
        // Same item + same pack + not weighted → increase quantity
        const existing = !item.scale && state.lines.find(l => l.item.key === item.key && String(l.pack_id) === String(packId) && !l.overridden && !l.item.is_weighted);
        if (existing) {
            existing.qty = Number(existing.qty) + Number(qty);
            state.selected = existing.uid;
            // move to top
            state.lines = [existing, ...state.lines.filter(l => l !== existing)];
        } else {
            const uid = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
            state.lines.unshift({uid, item, pack_id: packId, qty: Number(qty), price: null, discount_type: null, discount_value: 0, overridden: false});
            state.selected = uid;
        }
        beep(true);
        render();
        $('#cart').scrollTop = 0;
    }

    // ------------------------------------------------------------------ scan / search
    const scan = $('#scan');
    const results = $('#results');
    let resultItems = [];
    let activeIdx = 0;

    function showResults(items) {
        resultItems = items;
        activeIdx = 0;
        if (!items.length) { results.innerHTML = '<div class="p-3 text-muted">No matching products</div>'; results.style.display = 'block'; return; }
        results.innerHTML = items.map((it, i) => `<div class="r ${i === 0 ? 'active' : ''}" data-i="${i}">
            ${it.thumbnail ? `<img src="${it.thumbnail}" width="40" height="40" class="rounded" alt="">` : '<div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-box text-muted"></i></div>'}
            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${VM.esc(it.title)}${it.variant ? ' – ' + VM.esc(it.variant) : ''}</div>
            <div class="fs-sm text-muted">${VM.esc(it.sku || '')} · ${it.track_stock ? VM.qty(it.stock) + ' ' + VM.esc(it.base_unit) + ' in stock' : 'not tracked'}</div></div>
            <div class="fw-bold">${M(it.price)}</div></div>`).join('');
        results.style.display = 'block';
    }
    function hideResults() { results.style.display = 'none'; resultItems = []; }
    results.addEventListener('mousedown', e => {
        const r = e.target.closest('.r'); if (!r) return;
        e.preventDefault();
        addItem(resultItems[r.dataset.i], qtyMultiplier);
        qtyMultiplier = null; scan.value = ''; hideResults(); scan.focus();
    });

    const liveSearch = VM.debounce(async () => {
        const q = scan.value.trim().replace(/^\d+(\.\d+)?\*/, '');
        if (q.length < 2 || /^\d{8,}$/.test(q)) { if (!q) hideResults(); return; }
        try {
            const r = await VM.api(R.lookup + '?q=' + encodeURIComponent(q) + customerParam());
            if (scan.value.trim().replace(/^\d+(\.\d+)?\*/, '') === q) showResults(r.items);
        } catch (e) {}
    }, 220);
    scan.addEventListener('input', liveSearch);

    async function submitScan() {
        let raw = scan.value.trim();
        if (!raw) {
            if (state.lines.length) openPay();
            return;
        }
        const m = raw.match(/^(\d+(?:\.\d+)?)\*(.*)$/);
        if (m) { qtyMultiplier = Number(m[1]); raw = m[2].trim(); if (!raw) { scan.value = m[1] + '*'; return; } }
        if (resultItems.length && results.style.display === 'block' && !/^\d{8,}$/.test(raw)) {
            addItem(resultItems[activeIdx], qtyMultiplier); qtyMultiplier = null; scan.value = ''; hideResults(); return;
        }
        try {
            const r = await VM.api(R.lookup + '?q=' + encodeURIComponent(raw) + customerParam());
            if (r.exact && r.items.length === 1) {
                const it = r.items[0];
                addItem(it, it.scale ? it.qty : qtyMultiplier);
                scan.value = ''; hideResults();
            } else if (r.items.length) {
                showResults(r.items);
            } else {
                beep(false);
                scan.select();
                VM.toast('No product found for "' + raw + '"', 'error');
            }
        } catch (e) { beep(false); VM.error(e); }
        qtyMultiplier = null;
    }
    scan.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); submitScan(); }
        else if (e.key === 'ArrowDown' && resultItems.length) { e.preventDefault(); activeIdx = Math.min(resultItems.length - 1, activeIdx + 1); highlight(); }
        else if (e.key === 'ArrowUp' && resultItems.length) { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); highlight(); }
        else if (e.key === 'Escape') { hideResults(); scan.value = ''; }
    });
    function highlight() { $$('.r', results).forEach((r, i) => r.classList.toggle('active', i === activeIdx)); $$('.r', results)[activeIdx]?.scrollIntoView({block: 'nearest'}); }
    scan.addEventListener('blur', () => setTimeout(hideResults, 150));
    const customerParam = () => state.customer ? '&customer_id=' + state.customer.id : '';

    // ------------------------------------------------------------------ product grid
    async function loadGrid(cat = '') {
        $('#grid').innerHTML = '<div class="text-muted p-3">Loading…</div>';
        try {
            const r = await VM.api(R.browse + '?category=' + cat + customerParam());
            $('#grid').innerHTML = r.items.map((it, i) => `<button class="tile ${it.track_stock && it.stock <= 0 ? 'out' : ''}" data-i="${i}">
                <div class="ph">${it.thumbnail ? `<img src="${it.thumbnail}" alt="" loading="lazy">` : '<i class="bi bi-box"></i>'}</div>
                <div class="t">${VM.esc(it.title)}${it.variant ? ' – ' + VM.esc(it.variant) : ''}</div>
                <div class="p">${M(it.price)}</div>
                <div class="s">${it.track_stock ? VM.qty(it.stock) + ' ' + VM.esc(it.base_unit) : '&nbsp;'}</div>
            </button>`).join('') || '<div class="text-muted p-3">No products in this category.</div>';
            $$('#grid .tile').forEach(t => t.addEventListener('click', () => { addItem(r.items[t.dataset.i], qtyMultiplier); qtyMultiplier = null; scan.focus(); }));
        } catch (e) { $('#grid').innerHTML = '<div class="text-danger p-3">Could not load products.</div>'; }
    }
    $$('#cats button').forEach(b => b.addEventListener('click', () => {
        $$('#cats button').forEach(x => x.classList.remove('active')); b.classList.add('active'); loadGrid(b.dataset.cat);
    }));

    // ------------------------------------------------------------------ cart events
    $('#cart').addEventListener('click', async e => {
        const lineEl = e.target.closest('.line'); if (!lineEl) return;
        const l = state.lines.find(x => x.uid === lineEl.dataset.uid); if (!l) return;
        state.selected = l.uid;
        const act = e.target.closest('[data-act]')?.dataset.act;
        const step = l.item.is_weighted ? 0.1 : 1;
        if (act === 'inc') { l.qty = round2(Number(l.qty) + step); }
        else if (act === 'dec') { l.qty = Math.max(step, round2(Number(l.qty) - step)); }
        else if (act === 'rm') { state.lines = state.lines.filter(x => x !== l); }
        else if (act === 'disc') { await lineDiscountDialog(l); }
        else if (act === 'price') { await priceDialog(l); }
        else if (act === 'qty' || act === 'pack') { return; }
        render();
    });
    $('#cart').addEventListener('change', e => {
        const lineEl = e.target.closest('.line'); if (!lineEl) return;
        const l = state.lines.find(x => x.uid === lineEl.dataset.uid); if (!l) return;
        if (e.target.dataset.act === 'qty') { const v = Number(e.target.value); if (v > 0) l.qty = v; else state.lines = state.lines.filter(x => x !== l); }
        if (e.target.dataset.act === 'pack') { l.pack_id = e.target.value; l.overridden = false; }
        render();
    });

    async function lineDiscountDialog(l) {
        const r = await Swal.fire({
            title: 'Discount on ' + l.item.title,
            html: `<div class="btn-group mb-2" role="group">
                    <input type="radio" class="btn-check" name="dt" id="dtp" value="percent" ${l.discount_type !== 'fixed' ? 'checked' : ''}><label class="btn btn-outline-primary" for="dtp">%</label>
                    <input type="radio" class="btn-check" name="dt" id="dtf" value="fixed" ${l.discount_type === 'fixed' ? 'checked' : ''}><label class="btn btn-outline-primary" for="dtf">${CFG.currency}</label>
                   </div><input id="dv" type="number" min="0" step="0.01" class="swal2-input" value="${l.discount_value || ''}" placeholder="0">
                   <div class="text-muted fs-sm">Your limit without approval: ${CFG.maxDiscount}%</div>`,
            showCancelButton: true, confirmButtonText: 'Apply',
            preConfirm: () => ({type: document.querySelector('input[name=dt]:checked').value, value: Number(document.getElementById('dv').value || 0)}),
        });
        if (r.isConfirmed) { l.discount_type = r.value.type; l.discount_value = r.value.value; }
    }
    async function priceDialog(l) {
        const r = await Swal.fire({title: 'Change price', input: 'number', inputValue: unitPrice(l), inputLabel: l.item.title + ' – per ' + packOf(l).name,
            showCancelButton: true, confirmButtonText: 'Set price',
            footer: l.item.allow_price_override || CFG.can.price ? '' : 'A supervisor PIN will be needed when you pay.'});
        if (r.isConfirmed && r.value !== '') { l.price = Number(r.value); l.overridden = Math.abs(l.price - Number(packOf(l).price)) > 0.004; }
    }

    // ------------------------------------------------------------------ cart actions
    $('#aClear').addEventListener('click', async () => {
        if (!state.lines.length) return;
        if (!await VM.confirm('Clear this sale?', 'All items will be removed.', 'Clear')) return;
        resetSale();
    });
    $('#aDisc').addEventListener('click', orderDiscountDialog);
    $('#rmDisc').addEventListener('click', e => { e.preventDefault(); state.discount = null; render(); });
    async function orderDiscountDialog() {
        if (!state.lines.length) return;
        const d = state.discount || {type: 'percent', value: ''};
        const r = await Swal.fire({
            title: 'Discount on whole sale',
            html: `<div class="btn-group mb-2"><input type="radio" class="btn-check" name="odt" id="odp" value="percent" ${d.type !== 'fixed' ? 'checked' : ''}><label class="btn btn-outline-primary" for="odp">%</label>
                   <input type="radio" class="btn-check" name="odt" id="odf" value="fixed" ${d.type === 'fixed' ? 'checked' : ''}><label class="btn btn-outline-primary" for="odf">${CFG.currency}</label></div>
                   <input id="odv" type="number" min="0" step="0.01" class="swal2-input" value="${d.value}" placeholder="0">
                   <div class="text-muted fs-sm">Your limit without approval: ${CFG.maxDiscount}%</div>`,
            showCancelButton: true, confirmButtonText: 'Apply',
            preConfirm: () => ({type: document.querySelector('input[name=odt]:checked').value, value: Number(document.getElementById('odv').value || 0)}),
        });
        if (r.isConfirmed) { state.discount = r.value.value ? r.value : null; render(); }
    }
    $('#aNote').addEventListener('click', async () => {
        const r = await Swal.fire({title: 'Note on this sale', input: 'textarea', inputValue: state.note || '', showCancelButton: true});
        if (r.isConfirmed) { state.note = r.value; save(); VM.toast('Note saved'); }
    });
    function resetSale() {
        state.lines = []; state.customer = null; state.discount = null; state.note = ''; state.heldId = null; state.selected = null;
        authToken = null; payments = [];
        const red = $('#redeem'); if (red) red.value = '';
        render(); scan.focus();
    }

    // ------------------------------------------------------------------ hold / recall
    $('#aHold').addEventListener('click', holdSale);
    async function holdSale() {
        if (!state.lines.length) return;
        const r = await Swal.fire({title: 'Hold this sale', input: 'text', inputPlaceholder: 'Label (e.g. customer name) – optional', showCancelButton: true, confirmButtonText: 'Hold'});
        if (!r.isConfirmed) return;
        try {
            await VM.api(R.held, {method: 'POST', body: {label: r.value, customer_id: state.customer?.id, cart: {lines: state.lines, customer: state.customer, discount: state.discount, note: state.note}, total: totals().total}});
            VM.toast('Sale held'); resetSale(); refreshHeldCount();
        } catch (e) { VM.error(e); }
    }
    async function refreshHeldCount() {
        try { const list = await VM.api(R.held, {headers: {'X-Background-Poll': '1'}}); $('#heldCount').textContent = list.length; $('#heldCount').style.display = list.length ? '' : 'none'; return list; } catch (e) { return []; }
    }
    $('#btnHeld').addEventListener('click', openHeld);
    async function openHeld() {
        const list = await refreshHeldCount();
        $('#heldList').innerHTML = list.length ? list.map(h => `<div class="list-group-item d-flex align-items-center gap-2">
            <div class="flex-grow-1"><div class="fw-semibold">${VM.esc(h.label)}</div><div class="fs-sm text-muted">${h.items} lines · ${M(h.total)} · ${VM.esc(h.time)}${h.customer ? ' · ' + VM.esc(h.customer) : ''}</div></div>
            <button class="btn btn-sm btn-primary" data-resume="${h.id}">Resume</button>
            <button class="btn btn-sm btn-light text-danger" data-del="${h.id}"><i class="bi bi-trash"></i></button></div>`).join('')
            : '<div class="text-muted text-center py-4">No held sales</div>';
        bootstrap.Modal.getOrCreateInstance('#heldModal').show();
    }
    $('#heldList').addEventListener('click', async e => {
        const id = e.target.closest('[data-resume]')?.dataset.resume, del = e.target.closest('[data-del]')?.dataset.del;
        if (id) {
            if (state.lines.length && !await VM.confirm('Replace the current sale?', 'Hold or finish it first if you need it.')) return;
            try {
                const h = await VM.api(R.held + '/' + id);
                state.lines = h.cart.lines || []; state.customer = h.cart.customer || null; state.discount = h.cart.discount || null; state.note = h.cart.note || '';
                state.heldId = h.id;
                bootstrap.Modal.getInstance('#heldModal').hide(); render();
            } catch (err) { VM.error(err); }
        }
        if (del) { if (!await VM.confirm('Delete this held sale?')) return; await VM.api(R.held + '/' + del, {method: 'DELETE'}); openHeld(); }
    });

    // ------------------------------------------------------------------ customers
    $('#btnCustomer').addEventListener('click', () => { bootstrap.Modal.getOrCreateInstance('#custModal').show(); setTimeout(() => $('#custSearch').focus(), 300); searchCustomers(); });
    $('#btnCustClear').addEventListener('click', () => setCustomer(null));
    const searchCustomers = VM.debounce(async () => {
        try {
            const list = await VM.api(R.customers + '?q=' + encodeURIComponent($('#custSearch').value));
            $('#custList').innerHTML = list.map((c, i) => `<button class="list-group-item list-group-item-action" data-i="${i}">
                <div class="d-flex justify-content-between"><strong>${VM.esc(c.name)}</strong>${c.type === 'wholesale' ? '<span class="badge bg-info">Wholesale</span>' : ''}</div>
                <div class="fs-sm text-muted">${VM.esc(c.phone || '')}${c.points ? ' · ' + c.points + ' pts' : ''}${c.owing ? ' · owes ' + M(c.owing) : ''}</div></button>`).join('') || '<div class="text-muted p-2">No customers found</div>';
            $$('#custList [data-i]').forEach(b => b.addEventListener('click', () => { setCustomer(list[b.dataset.i]); bootstrap.Modal.getInstance('#custModal').hide(); }));
        } catch (e) {}
    }, 250);
    $('#custSearch').addEventListener('input', searchCustomers);
    $('#ncSave').addEventListener('click', async () => {
        try {
            const r = await VM.api(R.customerQuick, {method: 'POST', body: {first_name: $('#ncFirst').value, last_name: $('#ncLast').value, phone_number: $('#ncPhone').value}});
            setCustomer({id: r.customer.id, name: r.customer.name, phone: r.customer.phone_number, points: 0, type: 'regular'});
            bootstrap.Modal.getInstance('#custModal').hide();
        } catch (e) { VM.error(e); }
    });
    async function setCustomer(c) {
        const wasWholesale = state.customer?.type === 'wholesale';
        state.customer = c;
        const isWholesale = c?.type === 'wholesale';
        if (wasWholesale !== isWholesale && state.lines.length) {
            // Re-price the cart for wholesale / retail
            for (const l of state.lines) {
                try {
                    const r = await VM.api(R.lookup + '?q=' + encodeURIComponent(l.item.barcode || l.item.sku) + customerParam());
                    const fresh = r.items.find(i => i.key === l.item.key);
                    if (fresh) l.item = fresh;
                } catch (e) {}
            }
            VM.toast(isWholesale ? 'Wholesale prices applied' : 'Retail prices applied', 'info');
        }
        render();
    }

    // ------------------------------------------------------------------ payment
    const payModal = new bootstrap.Modal('#payModal');
    $('#btnPay').addEventListener('click', openPay);
    function openPay() {
        if (!state.lines.length) return;
        if (CFG.requireShift && !shift) { openShiftDialog(); return; }
        payments = [];
        method = 'cash';
        $$('.methods button').forEach(b => b.classList.toggle('active', b.dataset.m === 'cash'));
        $('#pointsBox').style.display = state.customer && state.customer.points > 0 ? '' : 'none';
        $('#pPts').textContent = state.customer?.points || 0;
        updatePay(true);
        payModal.show();
        setTimeout(() => { $('#tender').focus(); $('#tender').select(); }, 350);
    }
    function remaining() { return round2(totals().total - payments.reduce((a, p) => a + p.amount, 0)); }
    function updatePay(resetTender = false) {
        const t = totals();
        $('#pDue').textContent = M(t.total);
        const rem = Math.max(0, remaining());
        $('#pRemain').textContent = M(rem);
        if (resetTender) $('#tender').value = rem.toFixed(2);
        $('#pList').innerHTML = payments.map((p, i) => `<div class="pl"><span class="text-capitalize">${p.method}${p.reference ? ' · ' + VM.esc(p.reference) : ''}</span><span>${M(p.amount)} <a href="#" data-rm="${i}" class="text-danger ms-1"><i class="bi bi-x"></i></a></span></div>`).join('');
        const tender = Number($('#tender').value || 0);
        const totalPaid = payments.reduce((a, p) => a + p.amount, 0) + tender;
        const change = method === 'cash' || payments.some(p => p.method === 'cash') ? Math.max(0, round2(totalPaid - t.total)) : 0;
        $('#pChange').textContent = M(change);
        // quick cash buttons
        const due = rem;
        const notes = [due, Math.ceil(due / 500) * 500, Math.ceil(due / 1000) * 1000, Math.ceil(due / 1000) * 1000 + 1000, 5000, 10000]
            .filter((v, i, a) => v >= due && a.indexOf(v) === i).slice(0, 6);
        $('#quick').innerHTML = method === 'cash' ? notes.map((v, i) => `<button type="button" data-v="${v}">${i === 0 ? 'Exact' : M(v, 0)}</button>`).join('') : '';
        $('#payRef').style.display = method === 'cash' ? 'none' : '';
        if ($('#payConfirmedBox')) $('#payConfirmedBox').style.display = method === 'transfer' ? '' : 'none';
    }
    $('#pList').addEventListener('click', e => { const i = e.target.closest('[data-rm]')?.dataset.rm; if (i !== undefined) { e.preventDefault(); payments.splice(i, 1); updatePay(true); } });
    $('#quick').addEventListener('click', e => { const v = e.target.closest('[data-v]')?.dataset.v; if (v) { $('#tender').value = Number(v).toFixed(2); updatePay(); } });
    $$('.methods button').forEach(b => b.addEventListener('click', () => {
        if (b.dataset.m === 'credit' && !state.customer) { VM.toast('Choose a customer to sell on credit', 'warning'); return; }
        method = b.dataset.m; $$('.methods button').forEach(x => x.classList.toggle('active', x === b));
        $('#tender').value = Math.max(0, remaining()).toFixed(2); updatePay(); $('#tender').focus();
    }));
    $('#tender').addEventListener('input', () => updatePay());
    $('#redeem')?.addEventListener('input', () => { render(); updatePay(true); });
    $('#keypad').addEventListener('click', e => {
        const k = e.target.closest('[data-k]')?.dataset.k; if (k === undefined) return;
        const t = $('#tender');
        if (t.dataset.fresh !== '0') { t.value = ''; t.dataset.fresh = '0'; }
        t.value = k === '⌫' ? t.value.slice(0, -1) : t.value + k;
        updatePay();
    });
    $('#payModal').addEventListener('shown.bs.modal', () => { $('#tender').dataset.fresh = '1'; });
    $('#addPayment').addEventListener('click', () => {
        const amt = Number($('#tender').value || 0);
        if (amt <= 0) return;
        if (method !== 'cash' && amt > remaining() + 0.004) { VM.toast('Card/transfer cannot be more than what is left to pay', 'warning'); return; }
        payments.push({method, amount: round2(amt), reference: $('#payRef').value || null, confirmed: method === 'transfer' && !!$('#payConfirmed')?.checked});
        $('#payRef').value = '';
        if ($('#payConfirmed')) $('#payConfirmed').checked = false;
        updatePay(true);
    });

    $('#completeSale').addEventListener('click', completeSale);
    let clientRef = null;
    async function completeSale() {
        const btn = $('#completeSale');
        if (btn.disabled) return;
        const tender = Number($('#tender').value || 0);
        const pays = payments.slice();
        if (tender > 0) pays.push({method, amount: round2(tender), reference: $('#payRef').value || null, confirmed: method === 'transfer' && !!$('#payConfirmed')?.checked});
        if (CFG.cardNeedsRef && pays.some(p => p.method === 'card' && !p.reference)) { VM.toast('Enter the card terminal reference from the slip', 'warning'); $('#payRef').focus(); return; }
        const paid = pays.reduce((a, p) => a + p.amount, 0);
        if (paid + 0.004 < totals().total) { VM.toast('Payment is short by ' + M(totals().total - paid), 'warning'); return; }

        clientRef = clientRef || (CFG.user.id + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8));
        const body = {
            items: state.lines.map(l => ({
                product_id: l.item.product_id, variation_id: l.item.variation_id, pack_id: l.pack_id, qty: Number(l.qty),
                price: l.overridden ? Number(l.price) : null, discount_type: l.discount_value ? l.discount_type || 'percent' : null, discount_value: l.discount_value || null,
            })),
            customer_id: state.customer?.id || null,
            discount_type: state.discount?.type || null,
            discount_value: state.discount?.value || null,
            payments: pays,
            redeem_points: Number($('#redeem')?.value || 0) || null,
            note: state.note || null,
            held_sale_id: state.heldId,
            auth_token: authToken,
            client_ref: clientRef,
            register_id: CFG.register?.id || null,
        };
        btn.disabled = true; $('.spinner-border', btn).classList.remove('d-none');
        try {
            const res = await VM.api(R.checkout, {method: 'POST', body});
            lastOrder = res;
            clientRef = null;
            payModal.hide();
            resetSale();
            $('#dRef').textContent = 'Receipt ' + res.order_id + ' · ' + M(res.total);
            $('#dChange').textContent = M(res.change);
            if (res.pending_payments > 0) VM.toast('Transfer of ' + M(res.pending_payments) + ' is waiting for a supervisor to confirm', 'info');
            bootstrap.Modal.getOrCreateInstance('#doneModal').show();
            refreshShift();
        } catch (e) {
            if (e.status === 403 && e.data && e.data.needs_authorization) {
                const ok = await askSupervisor(e.data.needs_authorization, e.data.message);
                if (ok) { btn.disabled = false; $('.spinner-border', btn).classList.add('d-none'); return completeSale(); }
            } else {
                clientRef = null;
                VM.error(e);
            }
        } finally {
            btn.disabled = false; $('.spinner-border', btn).classList.add('d-none');
        }
    }

    async function askSupervisor(action, message) {
        const r = await Swal.fire({
            title: 'Supervisor approval', icon: 'warning', html: VM.esc(message) + '<br><span class="text-muted fs-sm">Ask a supervisor to enter their PIN.</span>',
            input: 'password', inputAttributes: {inputmode: 'numeric', maxlength: 6, autocomplete: 'off'}, inputPlaceholder: 'PIN',
            showCancelButton: true, confirmButtonText: 'Approve',
            preConfirm: async pin => {
                try { const res = await VM.api(R.authorize, {method: 'POST', body: {pin, action}}); return res; }
                catch (err) { Swal.showValidationMessage(err.message); return false; }
            },
        });
        if (r.isConfirmed && r.value) { authToken = r.value.token; VM.toast('Approved by ' + r.value.supervisor); return true; }
        return false;
    }

    // ------------------------------------------------------------------ receipt
    function printReceipt(orderId) {
        const f = $('#printFrame');
        f.src = R.receipt + '/' + encodeURIComponent(orderId) + '?autoprint=1';
    }
    $('#dPrint').addEventListener('click', () => lastOrder && printReceipt(lastOrder.order_id));
    $('#dNew').addEventListener('click', () => { bootstrap.Modal.getInstance('#doneModal').hide(); });
    $('#doneModal').addEventListener('hidden.bs.modal', () => scan.focus());

    // ------------------------------------------------------------------ recall / void / refund
    $('#btnRecall').addEventListener('click', async () => {
        $('#recallOrder').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance('#recallModal').show();
        setTimeout(() => $('#recallId').focus(), 300);
        try {
            const r = await VM.api(R.today);
            $('#recallToday').innerHTML = `<div class="fs-sm text-muted mb-1">Recent sales today</div><div class="list-group mb-3">` +
                (r.data.recent || []).map(o => `<button class="list-group-item list-group-item-action d-flex justify-content-between" data-oid="${VM.esc(o.id)}">
                    <span><span class="vm-mono">${VM.esc(o.id)}</span> <span class="text-muted fs-sm">${o.time}</span> ${o.status === 'cancelled' ? '<span class="badge bg-danger">void</span>' : ''}</span><strong>${M(o.total)}</strong></button>`).join('') + '</div>';
            $$('#recallToday [data-oid]').forEach(b => b.addEventListener('click', () => showOrder(b.dataset.oid)));
        } catch (e) {}
    });
    $('#recallGo').addEventListener('click', () => showOrder($('#recallId').value.trim()));
    $('#recallId').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); showOrder($('#recallId').value.trim()); } });
    async function showOrder(id) {
        if (!id) return;
        try {
            const o = await VM.api(R.order + '/' + encodeURIComponent(id));
            const cancelled = o.status === 'cancelled';
            $('#recallToday').innerHTML = '';
            $('#recallOrder').innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><div class="fw-bold vm-mono">${VM.esc(o.id)}</div><div class="fs-sm text-muted">${VM.esc(o.date)} · ${VM.esc(o.cashier || '')}${o.customer ? ' · ' + VM.esc(o.customer) : ''}</div></div>
                    <span class="badge ${cancelled ? 'bg-danger' : 'bg-success'} text-capitalize">${VM.esc(o.status)}</span>
                </div>
                ${cancelled ? `<div class="alert alert-danger py-2">Voided: ${VM.esc(o.void_reason || '')}</div>` : ''}
                <table class="table table-sm align-middle"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Total</th>${cancelled ? '' : '<th class="text-end" style="width:110px">Return qty</th>'}</tr></thead><tbody>
                ${o.items.map(i => `<tr><td>${VM.esc(i.title)}<div class="fs-xs text-muted">${VM.esc(i.unit || '')} · ${M(i.price)}${i.returned ? ' · ' + VM.qty(i.returned) + ' returned' : ''}</div></td>
                    <td class="text-end">${VM.qty(i.qty)}</td><td class="text-end">${M(i.total)}</td>
                    ${cancelled ? '' : `<td><input type="number" min="0" max="${i.qty - i.returned}" step="any" class="form-control form-control-sm text-end ret" data-id="${i.id}" ${i.qty - i.returned <= 0 ? 'disabled' : ''} placeholder="0"></td>`}</tr>`).join('')}
                </tbody><tfoot><tr><th colspan="2">Total</th><th class="text-end">${M(o.total)}</th>${cancelled ? '' : '<th></th>'}</tr>
                ${o.refunded ? `<tr><td colspan="2" class="text-danger">Refunded</td><td class="text-end text-danger">−${M(o.refunded)}</td>${cancelled ? '' : '<td></td>'}</tr>` : ''}</tfoot></table>
                <div class="fs-sm text-muted mb-3">Paid by ${o.payments.map(p => VM.esc(p.method) + ' ' + M(p.amount)).join(', ') || '—'}</div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-light" id="rPrint"><i class="bi bi-printer me-1"></i>Reprint</button>
                    ${cancelled ? '' : `<button class="btn btn-outline-warning" id="rReturn"><i class="bi bi-arrow-return-left me-1"></i>Refund selected items</button>
                    <button class="btn btn-outline-danger ms-auto" id="rVoid"><i class="bi bi-x-octagon me-1"></i>Void whole sale</button>`}
                </div>`;
            $('#rPrint').addEventListener('click', () => printReceipt(o.id));
            $('#rVoid')?.addEventListener('click', () => voidOrder(o));
            $('#rReturn')?.addEventListener('click', () => returnItems(o));
        } catch (e) { $('#recallOrder').innerHTML = `<div class="alert alert-warning">${VM.esc(e.message)}</div>`; }
    }
    async function voidOrder(o, token = null) {
        const reason = token ? voidOrder.reason : await VM.prompt('Void sale ' + o.id, 'Reason (required)');
        if (!reason) return;
        voidOrder.reason = reason;
        try {
            const r = await VM.api(R.void + '/' + encodeURIComponent(o.id), {method: 'POST', body: {reason, auth_token: token || authToken}});
            VM.toast(r.message); showOrder(o.id); refreshShift();
        } catch (e) {
            if (e.status === 403 && e.data?.needs_authorization && await askSupervisor('void', e.data.message)) return voidOrder(o, authToken);
            VM.error(e);
        }
    }
    async function returnItems(o, token = null) {
        const lines = {};
        $$('#recallOrder .ret').forEach(i => { if (Number(i.value) > 0) lines[i.dataset.id] = Number(i.value); });
        if (!Object.keys(lines).length) { VM.toast('Enter the quantity to return on each item', 'warning'); return; }
        let opts = returnItems.opts;
        if (!token) {
            const r = await Swal.fire({title: 'Refund items', html: `<input id="rr" class="swal2-input" placeholder="Reason">
                <select id="rm" class="swal2-select"><option value="cash">Refund cash</option><option value="card">Refund to card</option><option value="transfer">Refund by transfer</option></select>
                <label class="d-block mt-2"><input type="checkbox" id="rs" checked> Put items back on the shelf</label>`,
                showCancelButton: true, preConfirm: () => {
                    const reason = document.getElementById('rr').value; if (!reason) { Swal.showValidationMessage('Reason is required'); return false; }
                    return {reason, method: document.getElementById('rm').value, restock: document.getElementById('rs').checked};
                }});
            if (!r.isConfirmed) return;
            opts = returnItems.opts = r.value;
        }
        try {
            const res = await VM.api(R.return + '/' + encodeURIComponent(o.id), {method: 'POST', body: Object.assign({lines, auth_token: token || authToken}, opts)});
            Swal.fire({icon: 'success', title: 'Refund recorded', text: res.message}); showOrder(o.id); refreshShift();
        } catch (e) {
            if (e.status === 403 && e.data?.needs_authorization && await askSupervisor('return', e.data.message)) return returnItems(o, authToken);
            VM.error(e);
        }
    }

    // ------------------------------------------------------------------ shift
    async function refreshShift() {
        try {
            const r = await VM.api(R.shiftCurrent, {headers: {'X-Background-Poll': '1'}});
            shift = r.open ? r.shift : null;
            const chip = $('#shiftChip');
            if (shift) { chip.className = 'chip ok'; chip.innerHTML = `<i class="bi bi-unlock me-1"></i>Shift open · ${shift.orders} sales · ${M(shift.sales, 0)}`; }
            else { chip.className = 'chip warn'; chip.innerHTML = `<i class="bi bi-lock me-1"></i>No shift open`; }
        } catch (e) {}
    }
    $('#shiftChip').style.cursor = 'pointer';
    $('#shiftChip').addEventListener('click', () => shift ? shiftSummary() : openShiftDialog());
    $('#mOpenShift').addEventListener('click', e => { e.preventDefault(); openShiftDialog(); });
    async function openShiftDialog() {
        if (shift) { VM.toast('Your shift is already open', 'info'); return; }
        const r = await Swal.fire({title: 'Open shift', input: 'number', inputLabel: 'Opening cash float in the drawer', inputValue: 0, showCancelButton: true, confirmButtonText: 'Open shift'});
        if (!r.isConfirmed) return;
        if (CFG.registers.length > 1 && !CFG.register) { VM.toast('First choose which till this is', 'warning'); return pickRegister(); }
        try { await VM.api(R.shiftOpen, {method: 'POST', body: {opening_float: Number(r.value || 0), cash_register_id: CFG.register?.id || null}}); VM.toast('Shift opened'); refreshShift(); }
        catch (e) { VM.error(e); }
    }
    async function cashMove(type) {
        if (!shift) { VM.toast('Open a shift first', 'warning'); return; }
        const r = await Swal.fire({title: type === 'in' ? 'Cash in' : 'Cash out / payout',
            html: '<input id="ca" type="number" class="swal2-input" placeholder="Amount"><input id="cr" class="swal2-input" placeholder="Reason">',
            showCancelButton: true, preConfirm: () => ({amount: Number(document.getElementById('ca').value), reason: document.getElementById('cr').value})});
        if (!r.isConfirmed) return;
        try { await VM.api(R.shiftCash, {method: 'POST', body: Object.assign({type}, r.value)}); VM.toast('Recorded'); refreshShift(); } catch (e) { VM.error(e); }
    }
    $('#mCashIn').addEventListener('click', e => { e.preventDefault(); cashMove('in'); });
    $('#mCashOut').addEventListener('click', e => { e.preventDefault(); cashMove('out'); });
    async function pickRegister() {
        if (!CFG.registers.length) return;
        if (shift) { VM.toast('Close your shift before switching tills', 'warning'); return; }
        const opts = {};
        CFG.registers.forEach(r => { opts[r.id] = `${r.code} – ${r.name}${r.busy ? ' (open: ' + r.busy + ')' : ''}`; });
        const r = await Swal.fire({title: 'Which till is this?', input: 'select', inputOptions: opts, inputValue: CFG.register?.id || '',
            text: 'This computer remembers it.', showCancelButton: true, confirmButtonText: 'Use this till'});
        if (!r.isConfirmed || !r.value) return;
        try { await VM.api(R.register, {method: 'POST', body: {register_id: Number(r.value)}}); location.reload(); } catch (e) { VM.error(e); }
    }
    $('#regChip')?.addEventListener('click', pickRegister);

    async function shiftSummary() {
        await refreshShift(); if (!shift) return;
        const methods = Object.entries(shift.by_method || {}).map(([m, v]) => `<tr><td class="text-capitalize">${m}</td><td class="text-end">${M(v.total)}</td></tr>`).join('');
        Swal.fire({title: 'Shift since ' + shift.opened_at, html: `<table class="table table-sm text-start">
            <tr><td>Sales</td><td class="text-end">${shift.orders} · ${M(shift.sales)}</td></tr>${methods}
            <tr><td>Opening float</td><td class="text-end">${M(shift.opening_float)}</td></tr>
            <tr><td>Cash in / out</td><td class="text-end">${M(shift.cash_in)} / −${M(shift.cash_out)}</td></tr>
            <tr><td>Refunds</td><td class="text-end">−${M(shift.refunds)}</td></tr>
            <tr class="fw-bold"><td>Cash expected in drawer</td><td class="text-end">${M(shift.expected_cash)}</td></tr></table>`});
    }
    $('#mCloseShift').addEventListener('click', async e => {
        e.preventDefault();
        await refreshShift();
        if (!shift) { VM.toast('No open shift', 'info'); return; }
        const r = await Swal.fire({title: 'Close shift', html: `Count the cash in the drawer.<br><span class="text-muted fs-sm">Expected: ${M(shift.expected_cash)}</span>
            <input id="cc" type="number" step="0.01" class="swal2-input" placeholder="Counted cash"><textarea id="cn" class="swal2-textarea" placeholder="Notes (optional)"></textarea>`,
            showCancelButton: true, confirmButtonText: 'Close shift', preConfirm: () => {
                const v = document.getElementById('cc').value; if (v === '') { Swal.showValidationMessage('Enter the counted cash'); return false; }
                return {counted_cash: Number(v), notes: document.getElementById('cn').value};
            }});
        if (!r.isConfirmed) return;
        try {
            const res = await VM.api(R.shiftClose + '/' + shift.id + '/close', {method: 'POST', body: r.value});
            await Swal.fire({icon: Math.abs(res.variance) < 0.01 ? 'success' : 'warning', title: 'Shift closed',
                html: 'Variance: <b>' + M(res.variance) + '</b>', confirmButtonText: 'Open Z-report'});
            window.open(res.report_url, '_blank');
            refreshShift();
        } catch (err) { VM.error(err); }
    });
    $('#mToday').addEventListener('click', async e => {
        e.preventDefault();
        try {
            const r = await VM.api(R.today);
            const d = r.data;
            Swal.fire({title: "Today's sales", html: `<div class="display-6 fw-bold">${M(d.total_sales)}</div><div class="text-muted mb-2">${d.total_orders} sales · average ${M(d.average_sale)}</div>` +
                Object.entries(d.by_payment_method || {}).map(([m, v]) => `<div class="d-flex justify-content-between"><span class="text-capitalize">${m}</span><strong>${M(v)}</strong></div>`).join('')});
        } catch (err) { VM.error(err); }
    });
    $('#mShortcuts').addEventListener('click', e => {
        e.preventDefault();
        Swal.fire({title: 'Keyboard shortcuts', html: `<table class="table table-sm text-start">
            <tr><td><kbd>F2</kbd></td><td>Scan / search box</td></tr>
            <tr><td><kbd>3*</kbd> then scan</td><td>Add 3 of the scanned item</td></tr>
            <tr><td><kbd>Enter</kbd> on empty box</td><td>Go to payment</td></tr>
            <tr><td><kbd>+</kbd> / <kbd>-</kbd></td><td>Change quantity of the selected line</td></tr>
            <tr><td><kbd>Del</kbd></td><td>Remove the selected line</td></tr>
            <tr><td><kbd>F4</kbd></td><td>Hold sale</td></tr>
            <tr><td><kbd>F6</kbd></td><td>Discount on whole sale</td></tr>
            <tr><td><kbd>F8</kbd></td><td>Held sales</td></tr>
            <tr><td><kbd>F9</kbd></td><td>Pay</td></tr>
            <tr><td><kbd>Enter</kbd> in payment</td><td>Complete sale</td></tr></table>`});
    });

    // ------------------------------------------------------------------ keyboard
    document.addEventListener('keydown', e => {
        const modalOpen = document.querySelector('.modal.show') || document.querySelector('.swal2-container');
        if (e.key === 'F2') { e.preventDefault(); if (!modalOpen) scan.focus(); return; }
        if (document.querySelector('#payModal.show')) {
            if (e.key === 'Enter' && !document.querySelector('.swal2-container')) { e.preventDefault(); completeSale(); }
            return;
        }
        if (document.querySelector('#doneModal.show')) {
            if (e.key === 'Enter' || e.key === 'Escape') { e.preventDefault(); bootstrap.Modal.getInstance('#doneModal').hide(); }
            if (e.key.toLowerCase() === 'p') { e.preventDefault(); lastOrder && printReceipt(lastOrder.order_id); }
            return;
        }
        if (modalOpen) return;
        if (e.key === 'F4') { e.preventDefault(); holdSale(); }
        else if (e.key === 'F6') { e.preventDefault(); orderDiscountDialog(); }
        else if (e.key === 'F8') { e.preventDefault(); openHeld(); }
        else if (e.key === 'F9') { e.preventDefault(); openPay(); }
        else if (document.activeElement === scan && scan.value === '' && (e.key === '+' || e.key === '-' || e.key === 'Delete')) {
            const l = state.lines.find(x => x.uid === state.selected) || state.lines[0];
            if (!l) return;
            e.preventDefault();
            const step = l.item.is_weighted ? 0.1 : 1;
            if (e.key === '+') l.qty = round2(Number(l.qty) + step);
            if (e.key === '-') l.qty = Math.max(step, round2(Number(l.qty) - step));
            if (e.key === 'Delete') state.lines = state.lines.filter(x => x !== l);
            render();
        }
    });
    // Keep focus on the scanner box when clicking empty space
    document.addEventListener('click', e => {
        if (!e.target.closest('input,select,textarea,button,a,.modal,.swal2-container,.line,.dropdown-menu')) scan.focus();
    });

    // ------------------------------------------------------------------ misc
    function tick() { $('#clock').textContent = new Date().toLocaleString(undefined, {weekday: 'short', hour: '2-digit', minute: '2-digit'}); }
    setInterval(tick, 15000); tick();
    window.addEventListener('online', () => document.body.classList.remove('is-offline'));
    window.addEventListener('offline', () => document.body.classList.add('is-offline'));
    if (!navigator.onLine) document.body.classList.add('is-offline');
    setInterval(refreshShift, 60000);

    load();
    render();
    loadGrid('');
    refreshShift();
    refreshHeldCount();
    scan.focus();
})();
</script>
</body>
</html>
