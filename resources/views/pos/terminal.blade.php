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
        .pos-main { display: grid; grid-template-columns: minmax(0, 1fr) var(--cart-w, 480px); min-height: 0; }
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
        .tile.out { opacity: .6; cursor: not-allowed; }
        .tile.out .ph { filter: grayscale(1); }
        .stk { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 1px 8px; line-height: 1.6; white-space: nowrap; }
        .stk.ok { background: #e7f6ef; color: #0f7a4a; }
        .stk.low { background: #fff4de; color: #a35a00; }
        .stk.none { background: #fde8e8; color: #b42318; }
        .stk.nt { background: #f1f3f7; color: var(--pos-muted); font-weight: 600; }
        .tile .s { margin-top: auto; }
        #results .r.out { opacity: .6; }
        .qm-stock { margin-top: 4px; }

        /* ---- Cart: sizes follow --cs (cart text size: .9 normal, 1 large, 1.15 extra large) ---- */
        .pos-right { --cs: 1; }
        body.cart-normal .pos-right { --cs: .88; }
        body.cart-xl { --cart-w: 540px; }
        body.cart-xl .pos-right { --cs: 1.15; }
        .cust { padding: calc(10px * var(--cs)) 14px; border-bottom: 1px solid var(--pos-line); display: flex; gap: 8px; align-items: center; }
        .cust .who { flex: 1; min-width: 0; }
        .cust .who .n { font-weight: 700; font-size: calc(16px * var(--cs)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cust .who .m { font-size: calc(13px * var(--cs)); color: var(--pos-muted); }
        .cart { flex: 1; overflow: auto; }
        .line { padding: calc(12px * var(--cs)) 14px; border-bottom: 1px solid var(--pos-line); display: grid; grid-template-columns: 1fr auto; gap: 6px 10px; cursor: pointer; }
        .line.sel { background: var(--pos-accent-soft); box-shadow: inset 4px 0 0 var(--pos-accent); }
        .line .nm { font-weight: 700; font-size: calc(18px * var(--cs)); line-height: 1.25; color: var(--pos-ink); }
        .line .vari { font-size: calc(14.5px * var(--cs)); color: var(--pos-muted); margin-top: 2px; }
        .line .tot { font-weight: 800; font-size: calc(19px * var(--cs)); text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .line .ctl { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .qtybox { display: inline-flex; border: 1.5px solid var(--pos-line); border-radius: 10px; overflow: hidden; }
        .qtybox button { border: 0; background: #f3f5f9; width: calc(40px * var(--cs)); height: calc(40px * var(--cs)); font-size: calc(22px * var(--cs)); line-height: 1; font-weight: 600; }
        .qtybox input { width: calc(74px * var(--cs)); border: 0; text-align: center; font-weight: 800; font-size: calc(18px * var(--cs)); font-variant-numeric: tabular-nums; }
        .line select { width: auto; flex: 1 1 110px; min-width: 0; max-width: 200px; font-size: calc(14.5px * var(--cs)); padding-top: calc(7px * var(--cs)); padding-bottom: calc(7px * var(--cs)); }
        .line .ctl .btn-sm { font-size: calc(15px * var(--cs)); padding: calc(6px * var(--cs)) calc(10px * var(--cs)); }
        .line .disc { font-size: calc(14px * var(--cs)); font-weight: 600; color: var(--pos-danger); }
        .line .warn { font-size: calc(14px * var(--cs)); font-weight: 600; color: var(--pos-warn); }
        .line .badge { font-size: calc(11.5px * var(--cs)); }
        .empty-cart { text-align: center; color: var(--pos-muted); padding: 60px 20px; font-size: calc(16px * var(--cs)); }
        .empty-cart i { font-size: 56px; display: block; margin-bottom: 8px; opacity: .5; }
        .totals { border-top: 1px solid var(--pos-line); padding: calc(10px * var(--cs)) 16px; }
        .totals .row2 { display: flex; justify-content: space-between; color: var(--pos-muted); font-size: calc(16px * var(--cs)); font-variant-numeric: tabular-nums; }
        .totals .row2 span:last-child { color: var(--pos-ink); font-weight: 600; }
        .totals .row2 .text-danger { color: var(--pos-danger) !important; }
        .totals .grand { display: flex; justify-content: space-between; align-items: baseline; font-size: calc(34px * var(--cs)); font-weight: 800; color: var(--pos-ink); margin-top: 4px; font-variant-numeric: tabular-nums; }
        .actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; padding: 0 12px 8px; }
        .actions .btn { font-size: calc(13.5px * var(--cs)); padding: 9px 4px; font-weight: 600; }
        .pay { margin: 0 12px 12px; height: calc(64px * var(--cs)); font-size: calc(24px * var(--cs)); font-weight: 800; border-radius: 12px; background: var(--pos-accent); color: var(--pos-accent-ink); border: 0; }
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
        /* quantity dialog */
        #qtyModal .modal-content { border: 0; border-radius: 16px; overflow: hidden; }
        .qm-head { display: flex; gap: 12px; align-items: center; }
        .qm-ph { width: 56px; height: 56px; flex: none; border-radius: 10px; background: #f3f5f9; display: flex; align-items: center; justify-content: center; color: #b4bccb; font-size: 24px; overflow: hidden; }
        .qm-ph img { width: 100%; height: 100%; object-fit: cover; }
        .qm-title { font-weight: 700; font-size: 17px; line-height: 1.25; }
        .qm-meta { font-size: 12px; color: var(--pos-muted); }
        .qm-label { font-size: 12px; color: var(--pos-muted); margin-bottom: 4px; display: block; }
        .qm-packs { display: flex; flex-wrap: wrap; gap: 6px; }
        .qm-packs button { border: 2px solid var(--pos-line); background: #fff; border-radius: 10px; padding: 6px 12px; font-weight: 600; text-align: left; line-height: 1.2; color: var(--pos-ink); }
        .qm-packs button small { display: block; font-weight: 500; font-size: 11px; color: var(--pos-muted); }
        .qm-packs button.active { border-color: var(--pos-accent); background: var(--pos-accent-soft); color: var(--pos-accent); }
        .qm-qty { display: flex; border: 2px solid var(--pos-line); border-radius: 12px; overflow: hidden; }
        .qm-qty:focus-within { border-color: var(--pos-accent); box-shadow: 0 0 0 .2rem rgba(15,118,110,.15); }
        .qm-qty button { width: 64px; flex: none; border: 0; background: #f3f5f9; font-size: 26px; color: var(--pos-ink); }
        .qm-qty button:active { background: var(--pos-accent-soft); }
        .qm-qty input { flex: 1; min-width: 0; border: 0; outline: 0; text-align: center; font-size: 34px; font-weight: 800; height: 68px; color: var(--pos-ink); }
        .qm-quick { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; }
        .qm-quick button { border: 1px solid var(--pos-line); background: #f8fafc; border-radius: 8px; padding: 9px 0; font-weight: 600; color: var(--pos-ink); }
        .qm-quick button:hover { border-color: var(--pos-accent); }
        .qm-entry { display: inline-flex; background: #f3f5f9; border-radius: 10px; padding: 3px; gap: 2px; }
        .qm-entry button { border: 0; background: transparent; border-radius: 8px; padding: 6px 14px; font-weight: 700; font-size: 13px; color: var(--pos-muted); }
        .qm-entry button.active { background: #fff; color: var(--pos-accent); box-shadow: 0 1px 3px rgba(15,23,42,.12); }
        .qm-unit-tag { flex: none; align-self: center; padding: 0 14px; font-weight: 700; font-size: 18px; color: var(--pos-muted); }
        .qm-conv { font-size: 13px; color: var(--pos-accent); font-weight: 600; min-height: 18px; margin: -2px 0 8px; }
        .qm-sum { font-size: 12.5px; color: var(--pos-muted); }
        .qm-total { display: flex; justify-content: space-between; align-items: baseline; border-top: 1px dashed var(--pos-line); padding-top: 10px; }
        .qm-total strong { font-size: 26px; font-weight: 800; }
        .qm-note { font-size: 12px; margin-top: 4px; }
        .qm-add { background: var(--pos-accent); color: var(--pos-accent-ink); border: 0; font-weight: 700; padding-left: 22px; padding-right: 22px; }
        .qm-add:hover, .qm-add:focus { background: #0b5f58; color: #fff; }
        .qm-add:disabled { background: var(--pos-accent); opacity: .45; }

        /* ===================== motion ===================== */
        :root { --ease-out: cubic-bezier(.22, 1, .36, 1); --ease-spring: cubic-bezier(.34, 1.56, .64, 1); }
        @keyframes vmDropIn { from { opacity: 0; transform: translateY(-8px) scaleY(.96); } to { opacity: 1; transform: none; } }
        @keyframes vmDropOut { to { opacity: 0; transform: translateY(-6px) scaleY(.97); } }
        @keyframes vmRowIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: none; } }
        @keyframes vmFadeUp { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes vmLineIn { from { opacity: 0; transform: translateX(28px); } to { opacity: 1; transform: none; } }
        @keyframes vmFlash { 0% { background: rgba(15,118,110,.24); } 100% { background: var(--pos-accent-soft); } }
        @keyframes vmBump { 0% { transform: scale(1); } 35% { transform: scale(1.16); } 100% { transform: scale(1); } }
        @keyframes vmShake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(3px); } 30%, 50%, 70% { transform: translateX(-6px); } 40%, 60% { transform: translateX(6px); } }
        @keyframes vmShimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
        @keyframes vmScanBar { 0% { left: -35%; } 100% { left: 100%; } }
        @keyframes vmPopIn { 0% { transform: scale(.5); opacity: 0; } 60% { transform: scale(1.08); opacity: 1; } 100% { transform: scale(1); } }
        @keyframes vmDraw { to { stroke-dashoffset: 0; } }
        @keyframes vmRing { from { transform: scale(.7); opacity: .6; } to { transform: scale(1.7); opacity: 0; } }

        /* search / scan dropdown */
        #results { transform-origin: top center; }
        #results.anim-open { animation: vmDropIn .2s var(--ease-out); }
        #results.anim-open .r, #results.anim-open > .p-3 { animation: vmRowIn .24s var(--ease-out) both; animation-delay: calc(min(var(--i, 0), 10) * 24ms); }
        #results .r.fresh { animation: vmRowIn .14s ease-out both; animation-delay: 0ms; }
        #results.anim-close { animation: vmDropOut .12s ease-in forwards; pointer-events: none; }
        #results .r { transition: background-color .12s; }
        .scan-progress { position: absolute; left: 14px; right: 14px; bottom: 2px; height: 3px; overflow: hidden; border-radius: 3px; opacity: 0; transition: opacity .2s; pointer-events: none; }
        .scan-progress::after { content: ''; position: absolute; top: 0; left: -35%; width: 35%; height: 100%; border-radius: 3px; background: var(--pos-accent); animation: vmScanBar .9s ease-in-out infinite; }
        .scan-wrap.loading .scan-progress { opacity: 1; }
        #scan { transition: border-color .15s, box-shadow .15s; }
        #scan.ok-flash { border-color: #10b981; box-shadow: 0 0 0 .25rem rgba(16,185,129,.2); }
        #scan.err-shake { border-color: var(--pos-danger); box-shadow: 0 0 0 .25rem rgba(192,57,43,.15); animation: vmShake .4s; }

        /* product grid */
        .tile { transition: transform .14s var(--ease-out), border-color .14s, box-shadow .14s; }
        .tile:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(17,24,39,.08); }
        .tile:active { transform: scale(.96); }
        .grid.anim .tile { animation: vmFadeUp .3s var(--ease-out) both; animation-delay: calc(min(var(--i, 0), 24) * 18ms); }
        .tile.skel { pointer-events: none; }
        .tile.skel .ph, .tile.skel .t, .tile.skel .p { background: linear-gradient(90deg, #eef1f6 25%, #f8fafc 50%, #eef1f6 75%); background-size: 200% 100%; animation: vmShimmer 1.2s linear infinite; border-radius: 6px; }
        .tile.skel .t { height: 14px; width: 85%; }
        .tile.skel .p { height: 14px; width: 45%; }
        .cats button { transition: background-color .15s, color .15s, border-color .15s, transform .1s; }
        .cats button:active { transform: scale(.94); }

        /* cart */
        .line { transition: background-color .2s, opacity .17s ease-in, transform .17s ease-in; }
        .line.anim-new { animation: vmLineIn .3s var(--ease-out), vmFlash .9s ease-out; }
        .line.anim-bump { animation: vmFlash .9s ease-out; }
        .line.anim-bump .qtybox input, .line.anim-bump .tot, .line.anim-new .tot { animation: vmBump .34s var(--ease-spring); }
        .line.removing { opacity: 0; transform: translateX(36px); }
        .empty-cart { animation: vmFadeUp .35s var(--ease-out); }
        .bump { display: inline-block; animation: vmBump .34s var(--ease-spring); }
        .pay { transition: opacity .2s, transform .1s, box-shadow .2s; }
        .pay:not(:disabled):hover { box-shadow: 0 8px 22px rgba(15,118,110,.32); }
        .pay:not(:disabled):active { transform: scale(.98); }
        .pay.ready { animation: vmBump .4s var(--ease-spring); }
        .fly-dot { position: fixed; z-index: 2000; width: 44px; height: 44px; border-radius: 50%; background: var(--pos-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 8px 18px rgba(15,118,110,.35); pointer-events: none; overflow: hidden; border: 2px solid #fff; }
        .fly-dot img { width: 100%; height: 100%; object-fit: cover; }

        /* quantity + sale-complete dialogs */
        #qtyModal.fade .modal-dialog { transform: scale(.94) translateY(10px); transition: transform .24s var(--ease-out); }
        #qtyModal.show .modal-dialog { transform: none; }
        .qm-packs button, .qm-quick button { transition: border-color .12s, background-color .12s, transform .1s; }
        .qm-packs button:active, .qm-quick button:active, .qm-qty button:active { transform: scale(.93); }
        .qm-note:not(:empty) { animation: vmRowIn .2s ease-out; }
        .qm-qty.err-shake-box { border-color: var(--pos-danger); animation: vmShake .4s; }
        #doneModal.fade .modal-dialog { transform: scale(.88); transition: transform .32s var(--ease-spring); }
        #doneModal.show .modal-dialog { transform: none; }
        .done-check { position: relative; width: 76px; height: 76px; margin: 0 auto; }
        .done-check svg { width: 76px; height: 76px; display: block; }
        .done-check circle { fill: #10b981; transform-origin: center; }
        .done-check path { fill: none; stroke: #fff; stroke-width: 6; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; }
        .done-check .ringfx { position: absolute; inset: 0; border-radius: 50%; border: 3px solid #10b981; opacity: 0; }
        #doneModal.show .done-check circle { animation: vmPopIn .45s var(--ease-spring) both; }
        #doneModal.show .done-check path { animation: vmDraw .35s .3s ease-out forwards; }
        #doneModal.show .done-check .ringfx { animation: vmRing .75s .22s ease-out; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-delay: 0ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }

        .offline { display: none; }
        .vm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em; }
        body.is-offline .offline { display: inline-block; }

        @media (max-width: 1100px) {
            .pos-main { grid-template-columns: minmax(0, 1fr) var(--cart-w-md, 420px); }
            body.cart-xl { --cart-w-md: 460px; }
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
                <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="#" id="mCartSize"><span><i class="bi bi-fonts me-2"></i>Cart text size</span><span class="badge bg-secondary ms-3" id="mCartSizeLbl">Large</span></a></li>
                <li><a class="dropdown-item" href="#" id="mAskQty"><i class="bi bi-check2-square me-2" id="mAskQtyIcon"></i>Ask quantity before adding</a></li>
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
                <div class="scan-progress"></div>
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
                <div class="done-check" aria-hidden="true"><span class="ringfx"></span><svg viewBox="0 0 76 76"><circle cx="38" cy="38" r="36"/><path d="M23 39l11 11 20-22"/></svg></div>
                <h4 class="mt-2 mb-1">Sale complete</h4>
                <div class="text-muted" id="dRef"></div>
                <div class="display-6 fw-bold mt-3" id="dChange"></div>
                <div class="text-muted">change</div>
                <div class="done-email mt-4 text-start" id="dEmailBox">
                    <label class="form-label fs-sm fw-semibold mb-1" for="dEmail"><i class="bi bi-envelope me-1"></i>Email the receipt to the customer</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="dEmail" placeholder="customer@example.com" autocomplete="off">
                        <button class="btn btn-outline-primary" id="dEmailBtn" type="button"><span class="spinner-border spinner-border-sm d-none me-1"></span><i class="bi bi-send me-1"></i>Send</button>
                    </div>
                    <div class="form-check mt-1 d-none" id="dSaveWrap">
                        <input class="form-check-input" type="checkbox" id="dSave" checked>
                        <label class="form-check-label fs-sm" for="dSave">Save this email to <span id="dSaveName"></span></label>
                    </div>
                    <div class="fs-sm mt-1" id="dEmailMsg"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button class="btn btn-light" id="dPrint"><i class="bi bi-printer me-1"></i>Print receipt <kbd>P</kbd></button>
                <button class="btn btn-light" id="dEmailFocus"><i class="bi bi-envelope me-1"></i>Email <kbd>E</kbd></button>
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

{{-- ===================== QUANTITY MODAL ===================== --}}
<div class="modal fade" id="qtyModal" tabindex="-1" aria-labelledby="qmTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="qm-head mb-3">
                    <div class="qm-ph" id="qmPh"></div>
                    <div class="flex-grow-1" style="min-width:0">
                        <div class="qm-title" id="qmTitle"></div>
                        <div class="qm-meta" id="qmMeta"></div>
                        <div class="qm-stock" id="qmStock"></div>
                    </div>
                    <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="qmPackWrap" class="mb-3">
                    <span class="qm-label">Sell as</span>
                    <div class="qm-packs" id="qmPacks"></div>
                </div>
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-1">
                    <label class="qm-label mb-0" for="qmQty" id="qmLabel">Quantity</label>
                    <div class="qm-entry" id="qmEntry" role="group" aria-label="Enter by"></div>
                </div>
                <div class="qm-qty mb-2">
                    <button type="button" id="qmDec" aria-label="Less">&minus;</button>
                    <input type="text" id="qmQty" inputmode="decimal" autocomplete="off">
                    <span class="qm-unit-tag" id="qmUnit"></span>
                    <button type="button" id="qmInc" aria-label="More">+</button>
                </div>
                <div class="qm-conv" id="qmConv"></div>
                <div class="qm-quick mb-3" id="qmQuick"></div>
                <div class="qm-total"><span class="qm-sum" id="qmEach"></span><strong id="qmTotal"></strong></div>
                <div class="qm-note text-success" id="qmInCart"></div>
                <div class="qm-note" id="qmWarn" style="color:var(--pos-warn)"></div>
            </div>
            <div class="modal-footer">
                <span class="text-muted me-auto" style="font-size:12px"><kbd>Enter</kbd> add &middot; <kbd>Tab</kbd> unit &middot; <kbd>Esc</kbd> cancel</span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn qm-add" id="qmAdd"><i class="bi bi-cart-plus me-1"></i>Add to cart</button>
            </div>
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

    // ------------------------------------------------------------------ motion helpers
    const reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    let flash = null;        // {uid, kind: 'new'|'bump'} – line to animate on the next render
    let pickFrom = null;     // screen rect the last picked product came from (for the fly-to-cart dot)
    let lastTotal = null;
    let lastCount = 0;
    function restart(el, cls) { if (!el) return; el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }
    function countUp(el, from, to, ms = 260) {
        if (!el) return;
        cancelAnimationFrame(el._raf);
        if (reduceMotion || from === to) { el.textContent = M(to); return; }
        const t0 = performance.now();
        const step = now => {
            const k = Math.min(1, (now - t0) / ms), e = 1 - Math.pow(1 - k, 3);
            el.textContent = M(round2(from + (to - from) * e));
            if (k < 1) el._raf = requestAnimationFrame(step);
        };
        el._raf = requestAnimationFrame(step);
    }
    function flyToCart(item) {
        const from = pickFrom; pickFrom = null;
        if (reduceMotion || !from || !document.body.animate) return;
        const to = $('#cart').getBoundingClientRect();
        const dot = document.createElement('div');
        dot.className = 'fly-dot';
        dot.innerHTML = item.thumbnail ? `<img src="${item.thumbnail}" alt="">` : '<i class="bi bi-bag-plus"></i>';
        const sx = from.left + from.width / 2 - 22, sy = from.top + from.height / 2 - 22;
        const dx = (to.left + 48 - 22) - sx, dy = (to.top + 28 - 22) - sy;
        dot.style.left = sx + 'px'; dot.style.top = sy + 'px';
        document.body.appendChild(dot);
        dot.animate([
            {transform: 'translate(0,0) scale(.5)', opacity: 0},
            {transform: `translate(${dx * .45}px, ${dy * .45 - 90}px) scale(1.05)`, opacity: 1, offset: .45},
            {transform: `translate(${dx}px, ${dy}px) scale(.3)`, opacity: .15},
        ], {duration: 540, easing: 'cubic-bezier(.45,.05,.55,.95)'}).onfinish = () => dot.remove();
    }
    function flashScan(ok) {
        const el = document.getElementById('scan'); if (!el) return;
        const cls = ok ? 'ok-flash' : 'err-shake';
        el.classList.remove('ok-flash', 'err-shake'); void el.offsetWidth; el.classList.add(cls);
        clearTimeout(flashScan.t); flashScan.t = setTimeout(() => el.classList.remove(cls), ok ? 380 : 480);
    }
    let loadingCount = 0;
    function searching(on) {
        loadingCount = Math.max(0, loadingCount + (on ? 1 : -1));
        document.querySelector('.scan-wrap')?.classList.toggle('loading', loadingCount > 0);
    }

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
        flashScan(ok);
        try {
            const ctx = beep.ctx || (beep.ctx = new (window.AudioContext || window.webkitAudioContext)());
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = ok ? 1150 : 220; o.type = ok ? 'sine' : 'square';
            g.gain.value = .06; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? .07 : .25));
        } catch (e) {}
    }

    // ------------------------------------------------------------------ stock
    // Stock is what this till's location holds (from the server). Unless the shop allows
    // negative stock, the cart can never ask for more than that.
    function stockUnit(it) {
        const b = (it.packs || []).find(p => p.is_base) || (it.packs || [])[0];
        for (const t of b ? [b.name, b.short] : []) {
            const v = String(t || '').trim().toLowerCase().replace(/\.$/, '');
            if (/^(kg|kgs|kilo|kilograms?|kilogrammes?)$/.test(v)) return 'kg';
            if (/^(g|gm|gms|gr|grams?|grammes?)$/.test(v)) return 'g';
            if (/^(l|lt|ltr|litres?|liters?)$/.test(v)) return 'L';
            if (/^(ml|millilitres?|milliliters?)$/.test(v)) return 'ml';
        }
        return it.base_unit || (b && (b.short || b.name)) || '';
    }
    const enforceStock = it => !!it.track_stock && !CFG.allowNegative;
    const lineBase = l => Number(l.qty) * Number(packOf(l).qty || 1);
    const inCartBase = (it, except = null) => state.lines.filter(l => l.item.key === it.key && l !== except).reduce((a, l) => a + lineBase(l), 0);
    const stockLeft = (it, except = null) => Math.round((Number(it.stock) - inCartBase(it, except)) * 1000) / 1000;
    function stockBadge(it, extra = '') {
        if (!it.track_stock) return `<span class="stk nt">Stock not tracked</span>`;
        const n = Number(it.stock), u = VM.esc(stockUnit(it));
        if (n <= 0) return `<span class="stk none"><i class="bi bi-x-circle"></i>Out of stock</span>`;
        return `<span class="stk ${n <= 5 ? 'low' : 'ok'}">${VM.qty(n)} ${u} ${extra || 'in stock'}</span>`;
    }
    function stockRefused(it, needBase, except = null) {
        if (!enforceStock(it)) return false;
        const left = stockLeft(it, except);
        if (needBase <= left + 0.0001) return false;
        beep(false);
        VM.toast(left <= 0
            ? (Number(it.stock) <= 0 ? it.title + ' is out of stock' : 'All ' + VM.qty(it.stock) + ' ' + stockUnit(it) + ' of ' + it.title + ' are already in the cart')
            : except ? 'Only ' + VM.qty(it.stock) + ' ' + stockUnit(it) + ' of ' + it.title + ' in stock'
            : 'Only ' + VM.qty(left) + ' ' + stockUnit(it) + ' of ' + it.title + ' left', 'error');
        return true;
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
                const low = l.item.track_stock && inCartBase(l.item) > stockBase + 0.0001;
                const disc = lineDiscount(l);
                const anim = flash && flash.uid === l.uid ? ' anim-' + flash.kind : '';
                return `<div class="line ${state.selected === l.uid ? 'sel' : ''}${anim}" data-uid="${l.uid}">
                    <div>
                        <div class="nm">${VM.esc(l.item.title)}</div>
                        <div class="vari">${l.item.variant ? VM.esc(l.item.variant) + ' · ' : ''}${M(unitPrice(l))} / ${VM.esc(pk.name || 'unit')}${l.item.track_stock ? ' · <span class="' + (low ? 'text-danger fw-bold' : '') + '">' + VM.qty(stockBase) + ' ' + VM.esc(stockUnit(l.item)) + ' in stock</span>' : ''}${l.overridden ? ' <span class="badge bg-warning text-dark">price changed</span>' : ''}${l.item.on_promo ? ' <span class="badge bg-danger">promo</span>' : ''}</div>
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
                        ${low ? `<span class="warn w-100"><i class="bi bi-exclamation-triangle"></i> Only ${VM.qty(stockBase)} ${VM.esc(stockUnit(l.item))} in stock</span>` : ''}
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
        flash = null;
        if (lastTotal !== null && lastTotal !== t.total) {
            countUp($('#tTotal'), lastTotal, t.total);
            restart($('#tTotal'), 'bump');
        } else {
            $('#tTotal').textContent = M(t.total);
        }
        lastTotal = t.total;
        $('#payAmt').textContent = state.lines.length ? M(t.total) : '';
        $('#btnPay').disabled = !state.lines.length;
        if (state.lines.length && !lastCount) restart($('#btnPay'), 'ready');
        lastCount = state.lines.length;

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
    const packKey = id => (id === null || id === undefined || id === '' ? '' : String(id));
    const sameLine = (l, item, packId) => l.item.key === item.key && packKey(l.pack_id) === packKey(packId) && !l.overridden;
    function addItem(item, qty = null, packId = null) {
        qty = qty ?? (item.qty || 1);
        packId = packId ?? item.pack_id ?? (item.packs && item.packs[0] ? item.packs[0].id : null);
        const pk = (item.packs || []).find(p => packKey(p.id) === packKey(packId)) || {qty: 1};
        if (stockRefused(item, Number(qty) * Number(pk.qty || 1))) return false;
        // Same item + same pack → one line, quantities (or weights) added together.
        // Scale-label barcodes stay separate: each label is its own weighed bag.
        const existing = !item.scale && state.lines.find(l => !l.item.scale && sameLine(l, item, packId));
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + Number(qty)) * 1000) / 1000;
            state.selected = existing.uid;
            flash = {uid: existing.uid, kind: 'bump'};
            // move to top
            state.lines = [existing, ...state.lines.filter(l => l !== existing)];
        } else {
            const uid = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
            state.lines.unshift({uid, item, pack_id: packId, qty: Number(qty), price: null, discount_type: null, discount_value: 0, overridden: false});
            state.selected = uid;
            flash = {uid, kind: 'new'};
        }
        beep(true);
        render();
        flyToCart(item);
        $('#cart').scrollTop = 0;
        return true;
    }

    // ------------------------------------------------------------------ quantity dialog
    // Every product picked from the grid, the search list or a barcode scan opens this
    // dialog first so the cashier can set quantity / pack before it goes into the cart.
    // Skipped when the quantity is already known (3* prefix, weighed scale labels) or
    // when "Ask quantity before adding" is switched off in the menu.
    let askQty = true;
    try { askQty = localStorage.getItem('pos_ask_qty') !== '0'; } catch (e) {}
    const qtyModalEl = $('#qtyModal');
    const qmInput = $('#qmQty');
    const qm = {item: null, packId: null, initial: 1, pendingScan: null, lastKeyAt: 0, burst: null};

    function pickItem(item, qty = null) {
        // Items sold by weight/volume always ask, so the cashier enters how much is being bought.
        if (enforceStock(item) && stockLeft(item) <= 0) { stockRefused(item, 1); scan.focus(); return; }
        const measured = item.is_weighted || (item.packs || []).some(p => measureOf(p));
        if ((!askQty && !measured) || item.scale || qty != null) { addItem(item, qty); scan.focus(); return; }
        openQtyDialog(item);
    }
    const qmPacks = () => (qm.item.packs || []);
    const qmPack = () => qmPacks().find(p => String(p.id) === String(qm.packId)) || qmPacks()[0] || {price: qm.item.price, qty: 1, name: qm.item.base_unit || 'unit'};

    // ---- units: a kg pack can be keyed in kg or g, a litre pack in L or ml, or by money amount
    const MEASURES = [
        {re: /^(kg|kgs|kilo|kilogram|kilogramme)s?$/i, sym: 'kg', fam: 'w', f: 1},
        {re: /^(g|gm|gms|gr|gram|gramme)s?$/i, sym: 'g', fam: 'w', f: 0.001},
        {re: /^(lb|lbs|pound)s?$/i, sym: 'lb', fam: 'w', f: 0.45359237},
        {re: /^(l|lt|ltr|litre|liter)s?$/i, sym: 'L', fam: 'v', f: 1},
        {re: /^(ml|millilitre|milliliter)s?$/i, sym: 'ml', fam: 'v', f: 0.001},
        {re: /^(cl|centilitre|centiliter)s?$/i, sym: 'cl', fam: 'v', f: 0.01},
    ];
    const CUR = {symbol: String(VM.currencyLabel || CFG.currency || '').trim(), code: VM.currencyCode || ''};
    const SUBUNIT = {w: {sym: 'g', f: 0.001}, v: {sym: 'ml', f: 0.001}};
    function measureOf(pack) {
        if (!pack) return null;
        for (const raw of [pack.name, pack.short]) {
            const t = String(raw || '').trim().replace(/\.$/, '');
            const m = t && MEASURES.find(x => x.re.test(t));
            if (m) return m;
        }
        return null;
    }
    const baseSym = it => { const b = (it.packs || []).find(p => p.is_base) || (it.packs || [])[0]; return (b && measureOf(b)?.sym) || it.base_unit || ''; };
    const unitSym = pk => measureOf(pk)?.sym || pk.short || pk.name || 'unit';
    const fmt3 = n => VM.qty(Math.round(n * 1000) / 1000);
    // Entry modes for the current pack: [{id, label, sym, toPack(v), fromPack(q), step, quick, decimals}]
    function qmModes() {
        const pk = qmPack(), m = measureOf(pk), price = Number(pk.price) || 0;
        const fractional = !!(m || qm.item.is_weighted);
        const modes = [{id: 'pack', label: m ? m.sym : (pk.short || pk.name || 'Qty'), sym: unitSym(pk), title: m ? (m.fam === 'w' ? 'Weight' : 'Volume') : 'Quantity',
            toPack: v => v, fromPack: q => q, step: fractional ? (m && m.sym === 'g' || m && m.sym === 'ml' ? 50 : 0.1) : 1,
            quick: fractional ? (m && (m.sym === 'g' || m.sym === 'ml') ? [100, 250, 500, 750, 1000, 1500] : [0.25, 0.5, 1, 1.5, 2, 5]) : [1, 2, 3, 5, 10, 12]}];
        if (m) {
            const sub = SUBUNIT[m.fam];
            if (m.f === 1) {
                const r = m.f / sub.f; // e.g. 1000 g per kg
                modes.push({id: 'sub', label: sub.sym, sym: sub.sym, title: m.fam === 'w' ? 'Weight' : 'Volume',
                    toPack: v => v / r, fromPack: q => q * r, step: 50, quick: [100, 250, 500, 750, 1000, 1500].map(x => x * (r / 1000))});
            }
        }
        if (fractional && price > 0) {
            const nice = [200, 500, 1000, 2000, 5000, 10000].map(x => x * Math.max(1, Math.pow(10, Math.floor(Math.log10(price)) - 3)));
            modes.push({id: 'amount', label: (CUR.symbol || '').trim() + ' Amount', sym: (CUR.symbol || '').trim(), title: 'Amount', prefix: true,
                toPack: v => v / price, fromPack: q => round2(q * price), step: nice[0] / 2, quick: nice});
        }
        return modes;
    }
    const qmMode = () => qmModes().find(x => x.id === qm.entry) || qmModes()[0];
    const qmStep = () => qmMode().step;
    // Quantity in pack units for what's in the box (3 decimals, as stored on the sale)
    function qmPackQty() {
        const v = Number(String(qmInput.value).trim().replace(/,/g, ''));
        if (!(v > 0) || !isFinite(v)) return 0;
        const q = qmMode().toPack(v);
        const pk = qmPack(), fractional = !!(measureOf(pk) || qm.item.is_weighted);
        if (!fractional) return q;
        // By amount: round down so the customer is never charged more than the amount asked for
        return qm.entry === 'amount' ? Math.floor(q * 1000 + 1e-6) / 1000 : Math.round(q * 1000) / 1000;
    }
    function renderQtyEntry() {
        const modes = qmModes();
        if (!modes.some(x => x.id === qm.entry)) qm.entry = modes[0].id;
        $('#qmEntry').style.display = modes.length > 1 ? '' : 'none';
        $('#qmEntry').innerHTML = modes.map(x => `<button type="button" data-entry="${x.id}" class="${x.id === qm.entry ? 'active' : ''}">${VM.esc(x.label)}</button>`).join('');
        const md = qmMode();
        $('#qmQuick').innerHTML = md.quick.map(v => `<button type="button" data-v="${v}">${md.prefix ? VM.esc(md.sym) : ''}${VM.qty(v)}${md.prefix ? '' : (md.id === 'pack' && !measureOf(qmPack()) ? '' : ' ' + VM.esc(md.sym))}</button>`).join('');
    }
    function switchEntry(id) {
        const q = qmPackQty();
        qm.entry = id;
        renderQtyEntry();
        if (q > 0) { const v = qmMode().fromPack(q); qmInput.value = qm.entry === 'amount' ? round2(v) : fmt3(v); }
        updateQtyDialog(); qmInput.focus(); qmInput.select();
    }

    function openQtyDialog(item, qty = null) {
        qm.item = item;
        qm.packId = item.pack_id ?? (item.packs && item.packs[0] ? item.packs[0].id : null);
        qm.initial = Number(qty ?? item.qty ?? 1) || 1;
        qm.pendingScan = null;
        qm.burst = null;
        $('#qmTitle').textContent = item.title + (item.variant ? ' – ' + item.variant : '');
        $('#qmMeta').textContent = item.sku || '';
        const inCart = inCartBase(item);
        $('#qmStock').innerHTML = stockBadge(item) + (item.track_stock && inCart > 0 ? ` <span class="stk nt">${VM.qty(inCart)} ${VM.esc(stockUnit(item))} in cart</span>` : '');
        $('#qmPh').innerHTML = item.thumbnail ? `<img src="${item.thumbnail}" alt="">` : '<i class="bi bi-box"></i>';
        qm.entry = 'pack';
        // Less than one unit left of a weighed item → start with what's left
        if (enforceStock(item) && qty == null) {
            const pk0 = (item.packs || []).find(p => packKey(p.id) === packKey(qm.packId)) || {qty: 1};
            const leftP = Math.floor(stockLeft(item) / Number(pk0.qty || 1) * 1000) / 1000;
            const frac = item.is_weighted || (item.packs || []).some(p => measureOf(p));
            if (frac && leftP > 0 && leftP < qm.initial) qm.initial = leftP;
        }
        qmInput.value = qm.initial;
        renderQtyPacks();
        renderQtyEntry();
        updateQtyDialog();
        bootstrap.Modal.getOrCreateInstance(qtyModalEl).show();
    }
    function renderQtyPacks() {
        const packs = qmPacks();
        $('#qmPackWrap').style.display = packs.length ? '' : 'none';
        $('#qmPacks').innerHTML = packs.map(p => `<button type="button" data-pack="${p.id}" class="${String(p.id) === String(qm.packId) ? 'active' : ''}">${VM.esc(p.name)}${p.qty != 1 ? ' ×' + VM.qty(p.qty) : ''}<small>${M(p.price)}</small></button>`).join('');
    }
    function updateQtyDialog() {
        if (!qm.item) return;
        const it = qm.item, pk = qmPack(), md = qmMode(), sym = unitSym(pk);
        const qty = qmPackQty();
        const ok = qty > 0 && isFinite(qty);
        const total = ok ? round2(Number(pk.price) * qty) : 0;
        $('#qmLabel').textContent = md.title + (md.id === 'amount' ? ' to sell' : '') + ' (' + (md.id === 'amount' ? (CUR.code || md.sym) : md.sym) + ')';
        $('#qmUnit').textContent = md.sym;
        let conv = '';
        if (ok && md.id === 'sub') conv = `= ${fmt3(qty)} ${VM.esc(sym)}`;
        else if (ok && md.id === 'amount') conv = `= ${fmt3(qty)} ${VM.esc(sym)}` + (Math.abs(total - Number(String(qmInput.value).replace(/,/g, ''))) >= 0.01 ? ` <span class="text-muted fw-normal">(charged ${M(total)})</span>` : '');
        else if (ok && measureOf(pk) && SUBUNIT[measureOf(pk).fam] && measureOf(pk).f > 0.001 && qty < 1) conv = `= ${VM.qty(Math.round(qty * measureOf(pk).f / SUBUNIT[measureOf(pk).fam].f))} ${SUBUNIT[measureOf(pk).fam].sym}`;
        $('#qmConv').innerHTML = conv;
        $('#qmEach').innerHTML = ok ? `${fmt3(qty)} ${VM.esc(sym)} × ${M(pk.price)}/${VM.esc(sym)}` : `${M(pk.price)} per ${VM.esc(pk.name || sym)}`;
        const qmTotalText = M(total);
        if ($('#qmTotal').textContent !== qmTotalText) { $('#qmTotal').textContent = qmTotalText; if (qtyModalEl.classList.contains('show')) restart($('#qmTotal'), 'bump'); }
        const existing = state.lines.find(l => !l.item.scale && sameLine(l, it, qm.packId));
        $('#qmInCart').innerHTML = existing ? `<i class="bi bi-cart-check me-1"></i>${fmt3(existing.qty)} ${VM.esc(unitSym(pk))} already in the cart – this will be added to it` : '';
        const inCartBase = state.lines.filter(l => l.item.key === it.key).reduce((a, l) => a + Number(l.qty) * Number(packOf(l).qty || 1), 0);
        const need = inCartBase + (ok ? qty * Number(pk.qty || 1) : 0);
        const over = it.track_stock && need > Number(it.stock) + 0.0001;
        const left = Math.max(0, Math.round((Number(it.stock) - inCartBase) * 1000) / 1000);
        const leftInPack = Math.floor(left / Number(pk.qty || 1) * 1000) / 1000;
        $('#qmWarn').innerHTML = over ? `<i class="bi bi-exclamation-triangle me-1"></i>Only ${VM.qty(left)} ${VM.esc(stockUnit(it))} available${Number(pk.qty || 1) !== 1 ? ' (' + VM.qty(leftInPack) + ' ' + VM.esc(pk.name || '') + ')' : ''}${enforceStock(it) ? ' – reduce the quantity' : ''}` : '';
        $('#qmWarn').style.color = over && enforceStock(it) ? 'var(--pos-danger, #dc2626)' : 'var(--pos-warn)';
        $('#qmAdd').disabled = !ok || (over && enforceStock(it));
    }
    function confirmQtyDialog() {
        if (!qm.item) return;
        const raw = String(qmInput.value).trim();
        const isQty = v => /^\d{1,7}(\.\d{1,3})?$/.test(String(v).trim().replace(/,/g, '')) && Number(String(v).replace(/,/g, '')) > 0;
        // A barcode scanned while the box is open lands in this field. Scanners type much
        // faster than people, so the fast "burst" of keys is the new code; whatever was in
        // the box before it is this item's quantity.
        if (raw.length >= 4 && !isQty(raw)) {
            let code = raw, qty = qm.initial;
            const b = qm.burst;
            if (b) {
                const prefix = b.value.slice(0, b.start), suffix = b.value.slice(b.end);
                if (raw.startsWith(prefix) && raw.endsWith(suffix) && raw.length > prefix.length + suffix.length) {
                    code = raw.slice(prefix.length, raw.length - suffix.length).trim();
                }
                if (isQty(b.value)) { const keep = qmInput.value; qmInput.value = b.value; qty = qmPackQty() || qty; qmInput.value = keep; }
            }
            if (!addItem(qm.item, qty, qm.packId)) { qmInput.value = b && isQty(b.value) ? b.value : qm.initial; updateQtyDialog(); }
            qm.pendingScan = code;
            bootstrap.Modal.getInstance(qtyModalEl).hide();
            return;
        }
        const qty = qmPackQty();
        if (!(qty > 0)) { beep(false); restart($('.qm-qty'), 'err-shake-box'); setTimeout(() => $('.qm-qty').classList.remove('err-shake-box'), 600); qmInput.focus(); qmInput.select(); return; }
        pickFrom = $('#qmAdd').getBoundingClientRect();
        if (!addItem(qm.item, qty, qm.packId)) { restart($('.qm-qty'), 'err-shake-box'); setTimeout(() => $('.qm-qty').classList.remove('err-shake-box'), 600); qmInput.focus(); qmInput.select(); return; }
        bootstrap.Modal.getInstance(qtyModalEl).hide();
    }
    $('#qmPacks').addEventListener('click', e => {
        const b = e.target.closest('[data-pack]'); if (!b) return;
        const prevEntry = qm.entry;
        qm.packId = (qmPacks().find(p => String(p.id) === b.dataset.pack) || {}).id ?? b.dataset.pack; renderQtyPacks(); renderQtyEntry();
        if (qm.entry !== prevEntry || prevEntry === 'sub') qmInput.value = qm.entry === 'amount' ? qmInput.value : 1;
        updateQtyDialog(); qmInput.focus(); qmInput.select();
    });
    $('#qmEntry').addEventListener('click', e => { const b = e.target.closest('[data-entry]'); if (b) switchEntry(b.dataset.entry); });
    $('#qmQuick').addEventListener('click', e => {
        const v = e.target.closest('[data-v]')?.dataset.v; if (!v) return;
        qmInput.value = v; updateQtyDialog(); qmInput.focus(); qmInput.select();
    });
    const qmNum = () => Number(String(qmInput.value).replace(/,/g, '')) || 0;
    $('#qmInc').addEventListener('click', () => { qmInput.value = fmt3(qmNum() + qmStep()); updateQtyDialog(); qmInput.focus(); });
    $('#qmDec').addEventListener('click', () => { qmInput.value = fmt3(Math.max(qmStep(), qmNum() - qmStep())); updateQtyDialog(); qmInput.focus(); });
    qmInput.addEventListener('input', updateQtyDialog);
    qmInput.addEventListener('keydown', e => {
        if (e.key.length === 1 && e.key !== '+' && e.key !== '-' && !e.ctrlKey && !e.metaKey) {
            const now = performance.now();
            // Remember the box as it was when a new run of keystrokes began.
            if (now - qm.lastKeyAt > 50) qm.burst = {value: qmInput.value, start: qmInput.selectionStart ?? qmInput.value.length, end: qmInput.selectionEnd ?? qmInput.value.length};
            qm.lastKeyAt = now;
        }
        if (e.key === 'Enter') { e.preventDefault(); confirmQtyDialog(); }
        else if (e.key === 'Tab' && qmModes().length > 1) {
            e.preventDefault();
            const ids = qmModes().map(x => x.id), i = ids.indexOf(qm.entry);
            switchEntry(ids[(i + (e.shiftKey ? ids.length - 1 : 1)) % ids.length]);
        }
        else if (e.key === '+' || e.key === 'ArrowUp') { e.preventDefault(); $('#qmInc').click(); }
        else if (e.key === '-' || e.key === 'ArrowDown') { e.preventDefault(); $('#qmDec').click(); }
    });
    $('#qmAdd').addEventListener('click', confirmQtyDialog);
    qtyModalEl.addEventListener('shown.bs.modal', () => { qmInput.focus(); qmInput.select(); });
    qtyModalEl.addEventListener('hidden.bs.modal', () => {
        qm.item = null;
        pickFrom = null;
        scan.focus();
        if (qm.pendingScan) { scan.value = qm.pendingScan; qm.pendingScan = null; submitScan(); }
    });

    // Cart text size (per till): normal / large (default) / extra large
    const CART_SIZES = [['normal', 'Normal'], ['large', 'Large'], ['xl', 'Extra large']];
    let cartSize = 'large';
    try { cartSize = localStorage.getItem('pos_cart_size') || 'large'; } catch (e) {}
    function applyCartSize() {
        document.body.classList.toggle('cart-normal', cartSize === 'normal');
        document.body.classList.toggle('cart-xl', cartSize === 'xl');
        $('#mCartSizeLbl').textContent = (CART_SIZES.find(x => x[0] === cartSize) || CART_SIZES[1])[1];
    }
    $('#mCartSize').addEventListener('click', e => {
        e.preventDefault(); e.stopPropagation();   // keep the menu open so it can be clicked again
        const i = CART_SIZES.findIndex(x => x[0] === cartSize);
        cartSize = CART_SIZES[(i + 1) % CART_SIZES.length][0];
        try { localStorage.setItem('pos_cart_size', cartSize); } catch (err) {}
        applyCartSize();
    });
    applyCartSize();

    function paintAskQty() { $('#mAskQtyIcon').className = 'bi ' + (askQty ? 'bi-check2-square' : 'bi-square') + ' me-2'; }
    $('#mAskQty').addEventListener('click', e => {
        e.preventDefault();
        askQty = !askQty;
        try { localStorage.setItem('pos_ask_qty', askQty ? '1' : '0'); } catch (err) {}
        paintAskQty();
        VM.toast(askQty ? 'Quantity box will open before adding items' : 'Items will go straight into the cart', 'success');
    });
    paintAskQty();

    // ------------------------------------------------------------------ scan / search
    const scan = $('#scan');
    const results = $('#results');
    let resultItems = [];
    let activeIdx = 0;

    let hideTimer = null;
    function openResults() {
        const wasOpen = results.style.display === 'block' && !results.classList.contains('anim-close');
        clearTimeout(hideTimer);
        results.classList.remove('anim-close');
        results.style.display = 'block';
        if (!wasOpen) restart(results, 'anim-open');
        return wasOpen;
    }
    function showResults(items) {
        resultItems = items;
        activeIdx = 0;
        const wasOpen = results.style.display === 'block' && !results.classList.contains('anim-close');
        if (!items.length) { results.innerHTML = '<div class="p-3 text-muted"><i class="bi bi-search me-1"></i>No matching products</div>'; openResults(); return; }
        results.innerHTML = items.map((it, i) => `<div class="r ${i === 0 ? 'active' : ''}${wasOpen ? ' fresh' : ''}${enforceStock(it) && it.stock <= 0 ? ' out' : ''}" style="--i:${i}" data-i="${i}">
            ${it.thumbnail ? `<img src="${it.thumbnail}" width="40" height="40" class="rounded" alt="">` : '<div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-box text-muted"></i></div>'}
            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${VM.esc(it.title)}${it.variant ? ' – ' + VM.esc(it.variant) : ''}</div>
            <div class="fs-sm text-muted d-flex align-items-center gap-2 flex-wrap"><span>${VM.esc(it.sku || '')}</span>${stockBadge(it)}</div></div>
            <div class="fw-bold">${M(it.price)}</div></div>`).join('');
        openResults();
    }
    function hideResults() {
        resultItems = [];
        if (results.style.display !== 'block' || results.classList.contains('anim-close')) return;
        if (reduceMotion) { results.style.display = 'none'; return; }
        results.classList.remove('anim-open');
        results.classList.add('anim-close');
        hideTimer = setTimeout(() => { results.style.display = 'none'; results.classList.remove('anim-close'); }, 120);
    }
    results.addEventListener('mousedown', e => {
        const r = e.target.closest('.r'); if (!r) return;
        e.preventDefault();
        const it = resultItems[r.dataset.i], q = qtyMultiplier;
        pickFrom = r.getBoundingClientRect();
        qtyMultiplier = null; scan.value = ''; hideResults();
        pickItem(it, q);
    });

    // Fast search: recent answers are cached for a minute and a newer keystroke cancels the older request,
    // so the till never waits on (or flickers with) out-of-date results.
    const searchCache = new Map();
    let searchCtrl = null;
    const cacheKey = q => q.toLowerCase() + '|' + (state.customer?.id || '');
    async function lookup(q, live = false) {
        const key = cacheKey(q), hit = searchCache.get(key);
        if (hit && Date.now() - hit.t < 60000) return hit.r;
        if (searchCtrl) searchCtrl.abort();
        const ctrl = new AbortController();
        if (live) searchCtrl = ctrl;
        try {
            const r = await VM.api(R.lookup + '?q=' + encodeURIComponent(q) + customerParam(), {signal: ctrl.signal});
            searchCache.set(key, {t: Date.now(), r});
            if (searchCache.size > 200) searchCache.delete(searchCache.keys().next().value);
            return r;
        } finally { if (searchCtrl === ctrl) searchCtrl = null; }
    }
    const isAbort = e => e && (e.name === 'AbortError' || e.code === 20);
    const liveSearch = VM.debounce(async () => {
        const q = scan.value.trim().replace(/^\d+(\.\d+)?\*/, '');
        if (q.length < 2 || /^\d{8,}$/.test(q)) { if (!q) hideResults(); return; }
        searching(true);
        try {
            const r = await lookup(q, true);
            if (scan.value.trim().replace(/^\d+(\.\d+)?\*/, '') === q) showResults(r.items);
        } catch (e) {} finally { if (!searchCtrl) searching(false); }
    }, 150);
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
            const it = resultItems[activeIdx], q = qtyMultiplier;
            pickFrom = $$('.r', results)[activeIdx]?.getBoundingClientRect() || null;
            qtyMultiplier = null; scan.value = ''; hideResults(); pickItem(it, q); return;
        }
        searching(true);
        try {
            const r = await lookup(raw);
            if (r.exact && r.items.length === 1) {
                const it = r.items[0];
                const q = it.scale ? it.qty : qtyMultiplier;
                pickFrom = scan.getBoundingClientRect();
                qtyMultiplier = null; scan.value = ''; hideResults();
                pickItem(it, q);
            } else if (r.items.length) {
                showResults(r.items);
            } else {
                beep(false);
                scan.select();
                VM.toast('No product found for "' + raw + '"', 'error');
            }
        } catch (e) { if (!isAbort(e)) { beep(false); VM.error(e); } }
        finally { searching(false); }
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
        $('#grid').classList.remove('anim');
        $('#grid').innerHTML = Array.from({length: 12}, () => '<div class="tile skel"><div class="ph"></div><div class="t"></div><div class="p"></div></div>').join('');
        try {
            const r = await VM.api(R.browse + '?category=' + cat + customerParam());
            $('#grid').innerHTML = r.items.map((it, i) => `<button class="tile ${enforceStock(it) && it.stock <= 0 ? 'out' : ''}" style="--i:${i}" data-i="${i}" title="${it.track_stock ? VM.esc(VM.qty(it.stock) + ' ' + stockUnit(it) + ' in stock') : 'Stock not tracked'}">
                <div class="ph">${it.thumbnail ? `<img src="${it.thumbnail}" alt="" loading="lazy">` : '<i class="bi bi-box"></i>'}</div>
                <div class="t">${VM.esc(it.title)}${it.variant ? ' – ' + VM.esc(it.variant) : ''}</div>
                <div class="p">${M(it.price)}</div>
                <div class="s">${it.track_stock ? stockBadge(it, 'left') : '&nbsp;'}</div>
            </button>`).join('') || '<div class="text-muted p-3">No products in this category.</div>';
            restart($('#grid'), 'anim');
            $$('#grid .tile').forEach(t => t.addEventListener('click', () => { const q = qtyMultiplier; qtyMultiplier = null; pickFrom = t.getBoundingClientRect(); pickItem(r.items[t.dataset.i], q); }));
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
        if (act === 'inc') { if (stockRefused(l.item, (Number(l.qty) + step) * Number(packOf(l).qty || 1), l)) return; l.qty = round2(Number(l.qty) + step); flash = {uid: l.uid, kind: 'bump'}; }
        else if (act === 'dec') { l.qty = Math.max(step, round2(Number(l.qty) - step)); flash = {uid: l.uid, kind: 'bump'}; }
        else if (act === 'rm') { removeLine(l, lineEl); return; }
        else if (act === 'disc') { await lineDiscountDialog(l); }
        else if (act === 'price') { await priceDialog(l); }
        else if (act === 'qty' || act === 'pack') { return; }
        render();
    });
    $('#cart').addEventListener('change', e => {
        const lineEl = e.target.closest('.line'); if (!lineEl) return;
        const l = state.lines.find(x => x.uid === lineEl.dataset.uid); if (!l) return;
        if (e.target.dataset.act === 'qty') {
            const v = Number(e.target.value);
            if (v > 0 && stockRefused(l.item, v * Number(packOf(l).qty || 1), l)) { e.target.value = l.qty; return; }
            if (v > 0) l.qty = v; else state.lines = state.lines.filter(x => x !== l);
        }
        if (e.target.dataset.act === 'pack') {
            const np = (l.item.packs || []).find(p => String(p.id) === String(e.target.value)) || {qty: 1};
            if (stockRefused(l.item, Number(l.qty) * Number(np.qty || 1), l)) { e.target.value = l.pack_id; return; }
            l.pack_id = e.target.value; l.overridden = false;
        }
        render();
    });

    function removeLine(l, el = null) {
        el = el || document.querySelector(`#cart .line[data-uid="${l.uid}"]`);
        const done = () => { state.lines = state.lines.filter(x => x !== l); render(); };
        if (!el || reduceMotion) return done();
        el.style.height = el.offsetHeight + 'px';
        el.classList.add('removing');
        setTimeout(done, 170);
    }
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
                <div class="fs-sm text-muted">${VM.esc(c.phone || '')}${c.email ? ' · ' + VM.esc(c.email) : ''}${c.points ? ' · ' + c.points + ' pts' : ''}${c.owing ? ' · owes ' + M(c.owing) : ''}</div></button>`).join('') || '<div class="text-muted p-2">No customers found</div>';
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
            const saleCustomer = state.customer;
            payModal.hide();
            resetSale();
            searchCache.clear();
            loadGrid(document.querySelector('#cats button.active')?.dataset.cat || '');
            prepareEmail(saleCustomer);
            $('#dRef').textContent = 'Receipt ' + res.order_id + ' · ' + M(res.total);
            $('#dChange').textContent = M(0);
            setTimeout(() => countUp($('#dChange'), 0, Number(res.change) || 0, 650), 250);
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

    // ------------------------------------------------------------------ email receipt
    const emailOk = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    let emailCustomer = null;
    function prepareEmail(cust) {
        emailCustomer = cust || null;
        $('#dEmail').value = cust?.email || '';
        $('#dEmail').classList.remove('is-invalid', 'is-valid');
        $('#dEmailMsg').innerHTML = '';
        $('#dEmailBtn').disabled = false;
        const canSave = !!cust && !cust.email;
        $('#dSaveWrap').classList.toggle('d-none', !canSave);
        if (canSave) $('#dSaveName').textContent = cust.name || 'this customer';
    }
    async function sendReceiptEmail(orderId, email, save = false) {
        return VM.api(R.emailReceipt.replace('__ID__', encodeURIComponent(orderId)), {method: 'POST', body: {email, save_to_customer: save ? 1 : 0}});
    }
    async function emailFromDone() {
        if (!lastOrder) return;
        const inp = $('#dEmail'), btn = $('#dEmailBtn'), msg = $('#dEmailMsg');
        const email = inp.value.trim();
        if (!emailOk(email)) { inp.classList.add('is-invalid'); msg.innerHTML = '<span class="text-danger">Enter a valid email address.</span>'; inp.focus(); return; }
        inp.classList.remove('is-invalid');
        btn.disabled = true; $('.spinner-border', btn).classList.remove('d-none');
        msg.innerHTML = '<span class="text-muted">Sending…</span>';
        try {
            const save = !$('#dSaveWrap').classList.contains('d-none') && $('#dSave').checked;
            const r = await sendReceiptEmail(lastOrder.order_id, email, save);
            inp.classList.add('is-valid');
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>' + VM.esc(r.message || 'Receipt sent') + '</span>';
            if (save && emailCustomer) emailCustomer.email = email;
            $('#dSaveWrap').classList.add('d-none');
        } catch (e) {
            msg.innerHTML = '<span class="text-danger">' + VM.esc(e.data?.errors?.email?.[0] || e.message) + '</span>';
        } finally { btn.disabled = false; $('.spinner-border', btn).classList.add('d-none'); }
    }
    $('#dEmailBtn').addEventListener('click', emailFromDone);
    $('#dEmail').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); emailFromDone(); }
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); $('#dEmail').blur(); }
    });
    $('#dEmailFocus').addEventListener('click', () => $('#dEmail').focus());
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
                    <button class="btn btn-light" id="rEmail"><i class="bi bi-envelope me-1"></i>Email</button>
                    ${cancelled ? '' : `<button class="btn btn-outline-warning" id="rReturn"><i class="bi bi-arrow-return-left me-1"></i>Refund selected items</button>
                    <button class="btn btn-outline-danger ms-auto" id="rVoid"><i class="bi bi-x-octagon me-1"></i>Void whole sale</button>`}
                </div>`;
            $('#rPrint').addEventListener('click', () => printReceipt(o.id));
            $('#rEmail').addEventListener('click', () => emailFromRecall(o));
            $('#rVoid')?.addEventListener('click', () => voidOrder(o));
            $('#rReturn')?.addEventListener('click', () => returnItems(o));
        } catch (e) { $('#recallOrder').innerHTML = `<div class="alert alert-warning">${VM.esc(e.message)}</div>`; }
    }
    async function emailFromRecall(o) {
        const r = await Swal.fire({
            title: 'Email receipt ' + o.id, input: 'email', inputValue: o.customer_email || '', inputPlaceholder: 'customer@example.com',
            showCancelButton: true, confirmButtonText: '<i class="bi bi-send me-1"></i>Send', showLoaderOnConfirm: true,
            validationMessage: 'Enter a valid email address',
            preConfirm: async email => {
                try { return await sendReceiptEmail(o.id, email, false); }
                catch (err) { Swal.showValidationMessage(err.data?.errors?.email?.[0] || err.message); return false; }
            },
            allowOutsideClick: () => !Swal.isLoading(),
        });
        if (r.isConfirmed && r.value) VM.toast(r.value.message || 'Receipt sent');
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
            <tr><td><kbd>Enter</kbd> in quantity box</td><td>Add to cart (<kbd>Esc</kbd> cancels, <kbd>+</kbd>/<kbd>-</kbd> change it)</td></tr>
            <tr><td><kbd>3*</kbd> then scan</td><td>Add 3 of the scanned item straight away</td></tr>
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
            if (document.activeElement && document.activeElement.closest('#dEmailBox')) return;
            if (e.key.toLowerCase() === 'e') { e.preventDefault(); $('#dEmail').focus(); return; }
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
            if (e.key === '+') { if (stockRefused(l.item, (Number(l.qty) + step) * Number(packOf(l).qty || 1), l)) return; l.qty = round2(Number(l.qty) + step); }
            if (e.key === '-') l.qty = Math.max(step, round2(Number(l.qty) - step));
            flash = {uid: l.uid, kind: 'bump'};
            if (e.key === 'Delete') { removeLine(l); return; }
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
