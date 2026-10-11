{{-- Small helpers shared by the upgraded pages (no build step needed). --}}
<script>
window.VM = window.VM || {};
(function (VM) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    VM.currency = @json(currency_symbol());

    VM.money = function (n, dp = 2) {
        n = Number(n || 0);
        return (n < 0 ? '-' : '') + VM.currency + Math.abs(n).toLocaleString(undefined, {minimumFractionDigits: dp, maximumFractionDigits: dp});
    };
    VM.qty = function (n) {
        n = Number(n || 0);
        return Number.isInteger(n) ? n.toLocaleString() : n.toLocaleString(undefined, {maximumFractionDigits: 3});
    };
    VM.esc = function (s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    };

    /** fetch wrapper: JSON in/out, CSRF, throws {status, data} on error */
    VM.api = async function (url, {method = 'GET', body = null, form = null, headers = {}} = {}) {
        const opts = {method, headers: Object.assign({'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf}, headers), credentials: 'same-origin'};
        if (form) {
            opts.body = form;
        } else if (body !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        let data = null;
        try { data = await res.json(); } catch (e) { data = {}; }
        if (res.status === 401 && data && data.logged_out) {
            window.location.href = '/login';
        }
        if (!res.ok) {
            const err = new Error(data.message || ('Request failed (' + res.status + ')'));
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    };

    VM.toast = function (message, icon = 'success') {
        if (window.Swal) {
            Swal.fire({toast: true, position: 'top-end', icon, title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true});
        } else {
            alert(message);
        }
    };
    VM.error = function (e) {
        let msg = e && e.message ? e.message : String(e);
        if (e && e.data && e.data.errors) {
            msg = Object.values(e.data.errors).flat().slice(0, 6).join('<br>');
        }
        if (window.Swal) {
            Swal.fire({icon: 'error', title: 'Something went wrong', html: msg});
        } else {
            alert(msg);
        }
    };
    VM.confirm = async function (title, text = '', confirmText = 'Yes, continue', icon = 'warning') {
        if (!window.Swal) return confirm(title + '\n' + text);
        const r = await Swal.fire({title, html: text, icon, showCancelButton: true, confirmButtonText: confirmText, confirmButtonColor: '#0ab39c'});
        return r.isConfirmed;
    };
    VM.prompt = async function (title, label, required = true, inputType = 'text') {
        if (!window.Swal) return prompt(title);
        const r = await Swal.fire({title, input: inputType, inputLabel: label, showCancelButton: true,
            inputValidator: v => (required && !v ? 'This is required' : undefined)});
        return r.isConfirmed ? r.value : null;
    };
    VM.debounce = function (fn, ms = 250) {
        let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
    };
})(window.VM);
</script>
