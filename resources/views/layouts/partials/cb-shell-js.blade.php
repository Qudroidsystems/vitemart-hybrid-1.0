{{-- CB UI shell behaviour: theme toggle, topbar dropdowns, active sidebar link,
     ripple, back-to-top, mobile sidebar, page progress bar, 419 → login.
     Included just before </body> in layouts/master.blade.php. --}}
<script>
(function () {
    'use strict';

    /* ── helpers ── */
    function qs(sel, ctx)  { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    /* ── manual dropdown factory ── */
    function makeDropdown(btnId, panelId) {
        var btn = document.getElementById(btnId), panel = document.getElementById(panelId);
        if (!btn || !panel) return;
        function open()  { panel.style.display = 'block'; btn.setAttribute('aria-expanded','true'); }
        function close() { panel.style.display = 'none';  btn.setAttribute('aria-expanded','false'); }
        btn.addEventListener('click', function(e){ e.stopPropagation(); panel.style.display === 'none' ? open() : close(); });
        document.addEventListener('click', function(e){ if (!btn.contains(e.target) && !panel.contains(e.target)) close(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
    }

    /* ── theme ── */
    function initTheme() {
        var html   = document.documentElement;
        var iconEl = document.getElementById('theme-icon');
        var ICON   = { light:'bi bi-sun align-middle fs-3xl', dark:'bi bi-moon align-middle fs-3xl', auto:'bi bi-moon-stars align-middle fs-3xl' };

        function applyMode(mode) {
            var scheme = mode === 'auto' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : mode;
            html.setAttribute('data-bs-theme', scheme);
            html.setAttribute('data-topbar', scheme === 'dark' ? 'dark' : 'light');
            if (iconEl) iconEl.className = ICON[mode] || ICON.light;
            localStorage.setItem('app-theme', mode);
            var r = document.getElementById(scheme === 'dark' ? 'layout-mode-dark' : 'layout-mode-light');
            if (r) r.checked = true;
            qsa('.theme-mode-item').forEach(function(a){
                a.style.fontWeight = a.getAttribute('data-mode') === mode ? '600' : '';
                a.style.color      = a.getAttribute('data-mode') === mode ? 'var(--vz-primary,#405189)' : '';
            });
        }
        applyMode(localStorage.getItem('app-theme') || 'light');
        qsa('.theme-mode-item').forEach(function(a){
            a.addEventListener('click', function(e){ e.preventDefault(); applyMode(a.getAttribute('data-mode')); document.getElementById('theme-dropdown').style.display='none'; });
        });
        qsa('[name="data-bs-theme"]').forEach(function(r){ r.addEventListener('change', function(){ applyMode(r.value); }); });
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(){
            if (localStorage.getItem('app-theme') === 'auto') applyMode('auto');
        });
    }

    /* ── NProgress ── */
    function initNProgress() {
        if (typeof NProgress === 'undefined') return;
        NProgress.configure({ showSpinner: false, speed: 400, minimum: 0.1 });
        var stopTimer = null;
        function start(){ NProgress.start(); clearTimeout(stopTimer); stopTimer = setTimeout(function(){ NProgress.done(); }, 8000); }
        qsa('a[href]').forEach(function(a){
            var h = a.getAttribute('href') || '';
            if (h && h.charAt(0) !== '#' && !h.startsWith('javascript') && !h.startsWith('mailto') && !h.startsWith('tel')
                && !a.hasAttribute('data-bs-toggle') && !a.hasAttribute('data-bs-dismiss') && !a.hasAttribute('download')
                && !a.hasAttribute('data-no-progress') && a.getAttribute('target') !== '_blank'
                && !/\/(export|download|print|invoice|receipt|labels?)(\/|\?|$)|\.(pdf|csv|xlsx?)(\?|$)/i.test(h)) {
                a.addEventListener('click', function(e){ if (!e.defaultPrevented && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) start(); });
            }
        });
        // Plain form posts (filters, saves) – not AJAX forms that call preventDefault
        document.addEventListener('submit', function(e){ setTimeout(function(){ if (!e.defaultPrevented && e.target.getAttribute('target') !== '_blank') start(); }, 0); });
        window.addEventListener('pageshow', function(){ NProgress.done(); });
        window.addEventListener('load',     function(){ NProgress.done(); });
    }

    /* ── active sidebar ── */
    function initActiveSidebar() {
        var cur = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
        var best = null, bestLen = -1;
        // Consider every real sidebar link (top-level direct links AND submenu children).
        qsa('#navbar-nav a.nav-link[href]').forEach(function(link){
            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript') === 0) return;
            if (/\.html?([?#]|$)/i.test(href)) return;   // leftover theme demo links
            var lp;
            try { lp = (new URL(link.href, window.location.origin).pathname || '/').replace(/\/+$/, '') || '/'; }
            catch(e){ return; }
            var match = (lp === cur) || (lp.length > 1 && cur.indexOf(lp + '/') === 0);
            if (match && lp.length > bestLen) { best = link; bestLen = lp.length; }
        });
        if (!best) return;
        best.classList.add('active');
        if (best.closest('.nav-sm')) best.classList.add('nav-active-child');
        else best.classList.add('nav-active-parent');
        var col = best.closest('.collapse');
        if (col) {
            col.classList.add('show');
            var tog = qs('[data-bs-target="#'+col.id+'"],[href="#'+col.id+'"]');
            if (tog) {
                tog.setAttribute('aria-expanded','true');
                tog.classList.remove('collapsed');
                tog.classList.add('nav-active-parent','active');
            }
        }
        setTimeout(function(){ try { best.scrollIntoView({behavior:'smooth',block:'nearest'}); } catch(e){} }, 350);
    }

    /* ── ripple ── */
    function initRipple() {
        qsa('#navbar-nav .nav-link').forEach(function(link){
            link.addEventListener('click', function(e){
                if (link.hasAttribute('data-bs-toggle')) return;
                var r = document.createElement('span');
                var rect = link.getBoundingClientRect();
                var s = Math.max(rect.width, rect.height);
                r.className = 'nav-ripple';
                r.style.cssText = 'width:'+s+'px;height:'+s+'px;left:'+(e.clientX-rect.left-s/2)+'px;top:'+(e.clientY-rect.top-s/2)+'px;';
                link.appendChild(r);
                setTimeout(function(){ r.parentNode && r.parentNode.removeChild(r); }, 650);
            });
        });
    }

    /* ── back to top ── */
    function initBackToTop() {
        var btn = document.getElementById('back-to-top');
        if (!btn) return;
        window.addEventListener('scroll', function(){ btn.classList.toggle('show', window.scrollY > 300); }, {passive:true});
        btn.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });
    }

    /* ── hamburger / mobile sidebar ── */
    function initHamburger() {
        var ham     = document.getElementById('topnav-hamburger-icon');
        var overlay = document.getElementById('vertical-overlay');
        var body    = document.body;

        function closeSidebar() { body.classList.remove('vertical-sidebar-enable'); }
        function openSidebar()  { body.classList.add('vertical-sidebar-enable'); }

        if (ham) {
            var freshHam = ham.cloneNode(true);
            ham.parentNode.replaceChild(freshHam, ham);
            document.getElementById('topnav-hamburger-icon').addEventListener('click', function(e){
                e.preventDefault(); e.stopPropagation();
                body.classList.contains('vertical-sidebar-enable') ? closeSidebar() : openSidebar();
            });
        }
        if (overlay) overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && body.classList.contains('vertical-sidebar-enable')) closeSidebar();
        });
    }

    /* ── image modal ── */
    function initImageModal() {
        var modal = document.getElementById('imageViewModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function(e){
            var img = document.getElementById('enlargedImage');
            var src = e.relatedTarget ? e.relatedTarget.getAttribute('data-image') : null;
            if (img && src) img.src = src;
        });
    }

    /* ── search tooltip ── */
    function initSearchTooltip() {
        var btn = document.getElementById('spotlight-trigger');
        var tip = qs('.search-tooltip');
        if (!btn || !tip) return;
        btn.addEventListener('mouseenter', function(){ tip.style.opacity='1'; });
        btn.addEventListener('mouseleave', function(){ tip.style.opacity='0'; });
    }

    /* ── reset layout ── */
    function initReset() {
        var btn = document.getElementById('reset-layout');
        if (btn) btn.addEventListener('click', function(){ sessionStorage.clear(); localStorage.removeItem('app-theme'); location.reload(); });
    }

    /* ── INIT ── */
    document.addEventListener('DOMContentLoaded', function(){
        initTheme();
        makeDropdown('theme-toggle-btn', 'theme-dropdown');
        makeDropdown('user-menu-btn',    'user-dropdown');
        initActiveSidebar();
        initRipple();
        initBackToTop();
        initHamburger();
        initImageModal();
        initSearchTooltip();
        initReset();
        initNProgress();
    });
})();
</script>

<script>
/* Expired session on an AJAX / fetch call (HTTP 419) → go to the login page
   instead of leaving the page half-working. Full-page posts are redirected
   by the server already. */
(function () {
    var loginUrl = @json(route('login'));
    var sent = false;
    function toLogin() {
        if (sent) return; sent = true;
        window.location.href = loginUrl + (loginUrl.indexOf('?') === -1 ? '?' : '&') + 'expired=1';
    }
    if (window.jQuery) {
        jQuery(document).ajaxError(function (_e, xhr) { if (xhr && xhr.status === 419) toLogin(); });
    }
    if (window.fetch) {
        var _fetch = window.fetch;
        window.fetch = function () {
            return _fetch.apply(this, arguments).then(function (res) {
                if (res && res.status === 419) toLogin();
                return res;
            });
        };
    }
    if (window.axios && window.axios.interceptors) {
        window.axios.interceptors.response.use(null, function (err) {
            if (err && err.response && err.response.status === 419) toLogin();
            return Promise.reject(err);
        });
    }
})();
</script>
