/*
 * ViteMart till – service worker.
 * Keeps the till's styles, scripts and fonts on the till PC so the screen loads instantly
 * and never depends on the internet. Sales still go to this branch's own server.
 * If the branch server can't be reached, a friendly "reconnecting" page is shown and the
 * cart stays saved on the till until it is back.
 */
const VERSION = 'vm-pos-2026.11.0';
const STATIC = /\/(theme|vendor)\/|\.(css|js|woff2?|ttf|png|jpg|svg|ico)(\?|$)/;

self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => {
    e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('vm-pos-') && k !== VERSION).map(k => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Static files: cache first, refresh in the background.
    if (STATIC.test(url.pathname)) {
        e.respondWith(caches.open(VERSION).then(async cache => {
            const hit = await cache.match(req);
            const net = fetch(req).then(res => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
            return hit || net;
        }));
        return;
    }

    // The till page itself: always from the server (prices, user, CSRF), with an offline fallback.
    if (req.mode === 'navigate' && url.pathname.replace(/\/$/, '') === '/pos') {
        e.respondWith(fetch(req).catch(() => new Response(OFFLINE, {headers: {'Content-Type': 'text/html; charset=utf-8'}})));
    }
});

const OFFLINE = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reconnecting…</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;margin:0}
.b{max-width:440px;text-align:center;padding:24px}h1{font-size:22px;margin:0 0 8px}p{color:#94a3b8;line-height:1.5}button{margin-top:16px;padding:12px 28px;font-size:16px;border:0;border-radius:8px;background:#3b82f6;color:#fff;cursor:pointer}</style></head>
<body><div class="b"><h1>Can't reach the store server</h1><p>Check that the server PC is on and this till is connected to the shop network (cable / Wi-Fi).
The sale in progress is saved on this till and comes back when you reconnect.</p><button onclick="location.reload()">Try again</button>
<p style="font-size:13px">Retrying automatically…</p></div><script>setInterval(()=>fetch('/up',{cache:'no-store'}).then(r=>{if(r.ok)location.reload()}).catch(()=>{}),5000)</script></body></html>`;
