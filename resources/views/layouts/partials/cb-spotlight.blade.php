{{-- Spotlight search (CB UI shell): Ctrl/⌘+K. Pages come from the sidebar;
     products, customers and receipts come from route('spotlight.search'). --}}
@auth
<!-- ========== SPOTLIGHT MODAL ========== -->
<div id="spotlight-overlay" style="display:none;position:fixed;inset:0;z-index:1060;align-items:flex-start;justify-content:center;padding-top:12vh;">
    <div id="spotlight-box" style="width:100%;max-width:720px;margin:0 24px;background:rgba(28,28,30,0.98);border:1px solid rgba(255,255,255,0.12);border-radius:32px;box-shadow:0 32px 80px rgba(0,0,0,0.6),0 0 0 0.5px rgba(255,255,255,0.08);overflow:hidden;backdrop-filter:blur(20px);">
        <div style="display:flex;align-items:center;gap:16px;padding:20px 24px;border-bottom:0.5px solid rgba(255,255,255,0.1);">
            <i class="mdi mdi-magnify" style="font-size:28px;color:#4f8ef7;flex-shrink:0;"></i>
            <input id="spotlight-input" type="text" placeholder="Search pages, products, customers, receipts…" autocomplete="off"
                   style="flex:1;background:transparent;border:none;outline:none;font-size:1.5rem;font-weight:500;color:#fff;caret-color:#4f8ef7;padding:8px 0;">
            <div style="display:flex;gap:8px;">
                <button id="spotlight-clear-history" style="display:none;background:rgba(255,255,255,0.08);border:none;border-radius:10px;padding:6px 12px;color:rgba(255,255,255,0.6);font-size:12px;font-weight:500;cursor:pointer;">Clear History</button>
                <kbd id="spotlight-esc" style="font-size:13px;padding:5px 12px;border-radius:10px;background:rgba(255,255,255,0.08);border:0.5px solid rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);cursor:pointer;">ESC</kbd>
            </div>
        </div>
        <div id="spotlight-results" style="max-height:540px;overflow-y:auto;padding:12px 0;">
            <!-- Suggestion chips shown while typing -->
            <div id="spotlight-suggestions" style="display:none;padding:10px 24px 0;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35);margin-bottom:8px;">Suggestions</div>
                <div id="spotlight-suggestion-chips" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
                <div style="height:1px;background:rgba(255,255,255,.06);margin:14px -4px 0;"></div>
            </div>
            <!-- History section -->
            <div id="spotlight-history-section" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 24px 8px;">
                    <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.4);">Recent Searches</span>
                    <button id="spotlight-clear-history-btn" style="background:transparent;border:none;color:rgba(255,255,255,0.4);font-size:12px;cursor:pointer;">Clear All</button>
                </div>
                <div id="spotlight-history-list"></div>
                <div style="height:1px;background:rgba(255,255,255,0.06);margin:12px 20px;"></div>
            </div>
            <!-- Empty / default state -->
            <div id="spotlight-empty" style="padding:48px 24px;text-align:center;color:rgba(255,255,255,0.35);">
                <i class="mdi mdi-lightning-bolt" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.4;"></i>
                <span style="font-size:15px;">Start typing to search…</span>
                <div style="margin-top:16px;font-size:12px;opacity:0.4;">Try: a product name or barcode, a customer phone, a receipt number, or a page like “stock”</div>
            </div>
            <ul id="spotlight-list" style="list-style:none;margin:0;padding:0;display:none;"></ul>
            <div id="spotlight-loading" style="display:none;padding:48px;text-align:center;">
                <div style="display:inline-block;width:32px;height:32px;border:2px solid rgba(255,255,255,0.15);border-top-color:#4f8ef7;border-radius:50%;animation:loadingSpin 0.7s linear infinite;"></div>
                <div style="margin-top:16px;font-size:13px;color:rgba(255,255,255,0.45);">Searching<span class="typing-dot">.</span><span class="typing-dot">.</span><span class="typing-dot">.</span></div>
            </div>
        </div>
        <div style="padding:14px 24px;border-top:0.5px solid rgba(255,255,255,0.07);display:flex;gap:24px;font-size:12px;color:rgba(255,255,255,0.35);flex-wrap:wrap;">
            <span><kbd style="background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;">⌘K</kbd> / <kbd style="background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;">Ctrl+K</kbd> open</span>
            <span><kbd style="background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;">↑↓</kbd> navigate</span>
            <span><kbd style="background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;">↵</kbd> open</span>
            <span><kbd style="background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 8px;">ESC</kbd> close</span>
        </div>
    </div>
</div>


<script>
(function(){
    'use strict';

    /* ─────────────────────────────────────────────────────────────────
       COMPLETE PAGE REGISTRY — every navigable route in the system
       ───────────────────────────────────────────────────────────────── */
    /* Pages come from the sidebar itself, so the list always matches the menu
       (and only shows pages this user is allowed to open). */
    var STATIC_PAGES = [];
    (function buildFromSidebar(){
        var seen = {};
        var section = 'Pages';
        Array.prototype.forEach.call(document.querySelectorAll('#navbar-nav > li, #navbar-nav .simplebar-content > li'), function(li){
            if (li.classList.contains('menu-title')) { section = (li.textContent || '').trim() || section; return; }
            var top = li.querySelector(':scope > a.nav-link');
            if (!top) return;
            var topText = (top.textContent || '').replace(/\s+/g, ' ').trim();
            var icon = ((top.querySelector('i') || {}).className || '').trim() || 'mdi-chevron-right';
            var links = li.querySelectorAll('a.nav-link[href]');
            Array.prototype.forEach.call(links, function(a){
                var href = a.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#' || href.indexOf('javascript') === 0) return;
                if (/\.html?([?#]|$)/i.test(href)) return;   // leftover theme demo links
                var url = a.href;
                if (seen[url]) return; seen[url] = true;
                var title = (a.textContent || '').replace(/\s+/g, ' ').trim();
                if (!title) return;
                var isChild = a !== top;
                STATIC_PAGES.push({
                    title: title, url: url, icon: icon,
                    category: isChild ? topText.replace(/\s*Management$/i, '') : section,
                    keywords: [topText, section]
                });
            });
        });
    })();

    /* ── category colours ── */
    var PALETTE = ['#4f8ef7','#0d9488','#e76f51','#7c3aed','#f59e0b','#10b981','#e63946','#0ea5e9','#6a0572','#2a9d8f'];
    var CAT_COLORS = {'Products':'#0d9488','Customers':'#7c3aed','Receipts':'#f59e0b','Pages':'#4f8ef7'};
    STATIC_PAGES.forEach(function(p){ if (!CAT_COLORS[p.category]) CAT_COLORS[p.category] = PALETTE[Object.keys(CAT_COLORS).length % PALETTE.length]; });

    /* ── popular / suggestion chips ── */
    var POPULAR = [
        {label:'Products',   q:'product'},
        {label:'Stock',      q:'stock'},
        {label:'Orders',     q:'order'},
        {label:'Customers',  q:'customer'},
        {label:'Sales',      q:'sales'},
        {label:'Price',      q:'price'},
        {label:'Reports',    q:'report'},
        {label:'Users',      q:'user'},
    ];

    /* mdi-* names need the base "mdi" class; other icon sets (ph-*, ri-*, bi-*) are used as-is */
    function iconClass(ic){ ic = String(ic||'').replace(/[^\w\s-]/g,''); return /(^|\s)mdi-/.test(ic) && !/(^|\s)mdi(\s|$)/.test(ic) ? ic + ' mdi' : ic; }

    /* ── history helpers ── */
    var HISTORY_KEY = 'spotlight_search_history';
    function getHistory(){ try{ return JSON.parse(localStorage.getItem(HISTORY_KEY)||'[]'); }catch(e){ return []; } }
    function saveHistory(h){ localStorage.setItem(HISTORY_KEY, JSON.stringify(h.slice(0,10))); }
    function addHistory(query, result){
        if (!query || query.trim().length < 2) return;
        var h = getHistory();
        var item = {query:query.trim(), url:result.url, title:result.title, icon:result.icon, category:result.category, ts:Date.now()};
        var idx = h.findIndex(function(x){ return x.url===result.url; });
        if (idx !== -1) h.splice(idx,1);
        h.unshift(item);
        saveHistory(h);
    }

    /* ── fuzzy / scored search ── */
    function scoreMatch(page, q) {
        var lq   = q.toLowerCase().trim();
        var lt   = page.title.toLowerCase();
        var lc   = page.category.toLowerCase();
        var lk   = (page.keywords||[]).join(' ').toLowerCase();
        var score = 0;
        if (lt === lq)                   score += 100;
        if (lt.startsWith(lq))           score += 60;
        if (lt.includes(lq))             score += 40;
        if (lc.includes(lq))             score += 20;
        if (lk.includes(lq))             score += 30;
        /* word-level partial: each word of query must match something */
        var words = lq.split(/\s+/).filter(Boolean);
        if (words.length > 1) {
            var allMatch = words.every(function(w){ return lt.includes(w)||lc.includes(w)||lk.includes(w); });
            if (allMatch) score += 35;
        }
        /* fuzzy: check if query chars appear in order in title */
        if (score === 0 && lq.length >= 2) {
            var ti = 0;
            for (var ci = 0; ci < lq.length; ci++) {
                var found = lt.indexOf(lq[ci], ti);
                if (found === -1) { ti = -1; break; }
                ti = found + 1;
            }
            if (ti !== -1) score += 10;
        }
        return score;
    }

    function searchStatic(q) {
        var scored = [];
        STATIC_PAGES.forEach(function(p){
            var s = scoreMatch(p, q);
            if (s > 0) scored.push({page:p, score:s});
        });
        scored.sort(function(a,b){ return b.score - a.score; });
        return scored.map(function(x){ return x.page; }).slice(0,18);
    }

    /* ── suggestion chips based on partial input ── */
    function getSuggestions(q) {
        if (!q || q.length < 1) return POPULAR;
        var lq = q.toLowerCase();
        var matchedCats = {};
        STATIC_PAGES.forEach(function(p){
            var lt = p.title.toLowerCase(), lk = (p.keywords||[]).join(' ').toLowerCase();
            if (lt.includes(lq) || lk.includes(lq) || p.category.toLowerCase().includes(lq)) {
                matchedCats[p.category] = (matchedCats[p.category]||0) + 1;
            }
        });
        var cats = Object.keys(matchedCats).sort(function(a,b){ return matchedCats[b]-matchedCats[a]; }).slice(0,6);
        if (cats.length === 0) return POPULAR.slice(0,5);
        return cats.map(function(c){ return {label:c, q:c}; });
    }

    /* ── DOM refs ── */
    var overlay   = document.getElementById('spotlight-overlay');
    var box       = document.getElementById('spotlight-box');
    var input     = document.getElementById('spotlight-input');
    var emptyEl   = document.getElementById('spotlight-empty');
    var loadEl    = document.getElementById('spotlight-loading');
    var list      = document.getElementById('spotlight-list');
    var trigger   = document.getElementById('spotlight-trigger');
    var escBtn    = document.getElementById('spotlight-esc');
    var histSec   = document.getElementById('spotlight-history-section');
    var histList  = document.getElementById('spotlight-history-list');
    var clearBtn  = document.getElementById('spotlight-clear-history-btn');
    var clearMain = document.getElementById('spotlight-clear-history');
    var suggestEl = document.getElementById('spotlight-suggestions');
    var chipWrap  = document.getElementById('spotlight-suggestion-chips');

    var ajaxTimer = null, activeIndex = -1, currentResults = [];

    /* ── open / close ── */
    function open(){
        if(!overlay) return;
        overlay.style.display='flex';
        overlay.style.animation='spotlightOverlayFadeIn 0.25s ease forwards';
        if(box) box.style.animation='spotlightModalBounceIn 0.35s cubic-bezier(0.34,1.3,0.64,1) forwards';
        setTimeout(function(){ if(input) input.focus(); },100);
        renderHistory();
        renderChips(getSuggestions(''));
        if(clearMain) clearMain.style.display = getHistory().length>0?'block':'none';
    }
    function close(){
        if(box) box.style.animation='spotlightModalFadeOut 0.2s ease forwards';
        if(overlay) overlay.style.animation='spotlightOverlayFadeOut 0.2s ease forwards';
        setTimeout(function(){
            if(overlay) overlay.style.display='none';
            if(input) input.value='';
            showEmpty(true);
        },200);
    }

    function showEmpty(withChips){
        if(emptyEl){ emptyEl.style.display='block'; }
        if(loadEl) loadEl.style.display='none';
        if(list){ list.style.display='none'; list.innerHTML=''; }
        if(clearMain) clearMain.style.display = getHistory().length>0?'block':'none';
        renderHistory();
        if(withChips) renderChips(getSuggestions(''));
        currentResults=[]; activeIndex=-1;
    }

    function showLoading(){
        if(emptyEl) emptyEl.style.display='none';
        if(loadEl) loadEl.style.display='block';
        if(list) list.style.display='none';
        if(histSec) histSec.style.display='none';
        if(suggestEl) suggestEl.style.display='none';
    }

    /* ── suggestion chips ── */
    function renderChips(suggestions) {
        if (!chipWrap || !suggestEl) return;
        chipWrap.innerHTML = '';
        if (!suggestions || !suggestions.length) { suggestEl.style.display='none'; return; }
        suggestEl.style.display = 'block';
        suggestions.forEach(function(s, i){
            var btn = document.createElement('button');
            btn.className = 'spotlight-suggest-chip';
            btn.style.cssText = 'background:rgba(79,142,247,.12);border:1px solid rgba(79,142,247,.3);border-radius:20px;padding:5px 14px;color:rgba(255,255,255,.8);font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;white-space:nowrap;animation-delay:'+(i*0.04)+'s;opacity:0;';
            btn.textContent = s.label;
            btn.addEventListener('mouseenter', function(){ btn.style.background='rgba(79,142,247,.25)'; btn.style.borderColor='rgba(79,142,247,.6)'; });
            btn.addEventListener('mouseleave', function(){ btn.style.background='rgba(79,142,247,.12)'; btn.style.borderColor='rgba(79,142,247,.3)'; });
            btn.addEventListener('click', function(){
                if (input) { input.value = s.q; input.focus(); }
                performSearch(s.q);
            });
            chipWrap.appendChild(btn);
        });
    }

    /* ── history ── */
    function renderHistory(){
        var h = getHistory();
        if (h.length > 0 && (!input || !input.value.trim())) {
            if(histSec) histSec.style.display='block';
            if(histList) histList.innerHTML='';
            if(clearMain) clearMain.style.display='block';
            h.forEach(function(item, idx){
                var div = document.createElement('div');
                div.className = 'spotlight-history-item';
                div.style.cssText = 'display:flex;align-items:center;gap:14px;padding:10px 24px;cursor:pointer;transition:background .15s;border-radius:10px;margin:0 16px;';
                var c = CAT_COLORS[item.category]||'#4f8ef7';
                div.innerHTML = '<span style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:'+c+'22;"><i class="'+iconClass(item.icon||'mdi-history')+'" style="font-size:16px;color:'+c+';"></i></span>'
                    + '<span style="flex:1;min-width:0;"><span style="display:block;font-size:14px;font-weight:500;color:rgba(255,255,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+escHtml(item.title)+'</span>'
                    + '<span style="display:block;font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;">'+escHtml(item.category)+'</span></span>'
                    + '<button class="hist-remove" style="background:transparent;border:none;color:rgba(255,255,255,.35);cursor:pointer;font-size:13px;padding:6px 10px;border-radius:6px;">✕</button>';
                div.querySelector('.hist-remove').addEventListener('click', function(e){
                    e.stopPropagation();
                    var his = getHistory(); his.splice(idx,1); saveHistory(his); renderHistory();
                    if (!input || !input.value.trim()) showEmpty(true);
                });
                div.addEventListener('mouseenter', function(){ div.style.background='rgba(255,255,255,.04)'; });
                div.addEventListener('mouseleave', function(){ div.style.background=''; });
                div.addEventListener('click', function(){ if(input){ input.value=item.query; performSearch(item.query); } });
                if(histList) histList.appendChild(div);
            });
        } else {
            if(histSec) histSec.style.display='none';
            if(clearMain) clearMain.style.display='none';
        }
    }

    /* ── HTML escaping ── */
    function escHtml(str){
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(m){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    /* ── highlight matched chars in title ── */
    function highlightText(title, q){
        if (!q) return escHtml(title);
        var lq = q.toLowerCase(), lt = title.toLowerCase();
        var idx = lt.indexOf(lq);
        if (idx === -1) return escHtml(title);
        return escHtml(title.substring(0,idx))
            + '<mark style="background:rgba(79,142,247,.35);color:#a5c8ff;border-radius:3px;padding:0 2px;">' + escHtml(title.substring(idx,idx+lq.length)) + '</mark>'
            + escHtml(title.substring(idx+lq.length));
    }

    /* ── perform search ── */
    function performSearch(query){
        if (!query || !query.trim()) { showEmpty(true); return; }
        var sr = searchStatic(query);
        if (sr.length > 0) {
            renderResults(sr, query);
            renderChips(getSuggestions(query));
        } else {
            showLoading();
        }
        clearTimeout(ajaxTimer);
        ajaxTimer = setTimeout(function(){
            if (query.length < 2) { if (!sr.length) renderResults(sr, query); return; }
            fetch(@json(route('spotlight.search')) + '?q=' + encodeURIComponent(query), {
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'
            }).then(function(r){ return r.ok ? r.json() : {results:[]}; })
              .then(function(d){
                  if (!input || input.value.trim() !== query) return;
                  var remote = d.results || [];
                  var seen = {};
                  var merged = sr.concat(remote).filter(function(r){ if(seen[r.url]) return false; seen[r.url]=true; return true; });
                  renderResults(merged, query);
              }).catch(function(){ if (input && input.value.trim() === query) renderResults(sr, query); });
        }, 280);
    }

    /* ── render results ── */
    function renderResults(results, query){
        if(loadEl) loadEl.style.display='none';
        if(emptyEl) emptyEl.style.display='none';
        if(list){ list.innerHTML=''; list.style.display='block'; }
        if(histSec) histSec.style.display='none';
        activeIndex = -1; currentResults = results;

        if (!results.length) {
            if(emptyEl){
                emptyEl.innerHTML = '<i class="mdi mdi-magnify-close" style="font-size:42px;display:block;margin-bottom:16px;opacity:.4;"></i>'
                    + '<span style="font-size:15px;">No results for "'+escHtml(query)+'"</span>'
                    + '<div style="margin-top:12px;font-size:12px;opacity:.4;">Try a different term or browse from the sidebar</div>';
                emptyEl.style.display='block';
            }
            if(list) list.style.display='none';
            renderChips(POPULAR);
            return;
        }

        /* Group by category */
        var groups = {}, order = [];
        results.forEach(function(r){
            if (!groups[r.category]) { groups[r.category]=[]; order.push(r.category); }
            groups[r.category].push(r);
        });

        var globalIdx = 0;
        order.forEach(function(cat){
            /* category header */
            var hdr = document.createElement('li');
            hdr.style.cssText = 'padding:12px 24px 6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35);';
            hdr.textContent = cat;
            list.appendChild(hdr);

            groups[cat].forEach(function(r){
                var li = document.createElement('li');
                var isTop = (globalIdx === 0);
                li.className = 'spotlight-result-item' + (isTop ? ' top-match' : '');
                li.setAttribute('data-idx', globalIdx);
                li.style.cssText = 'display:flex;align-items:center;gap:14px;padding:12px 24px;cursor:pointer;transition:all .2s;border-radius:10px;margin:4px 12px;';
                var c = CAT_COLORS[r.category] || '#4f8ef7';
                li.innerHTML = '<span style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:'+c+'22;">'
                    + '<i class="'+iconClass(r.icon||'mdi-chevron-right')+'" style="font-size:18px;color:'+c+';"></i></span>'
                    + '<span style="flex:1;min-width:0;">'
                    + '<span class="result-title" style="display:block;font-size:15px;font-weight:500;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
                    + highlightText(r.title, query)
                    + '</span>'
                    + '<span style="display:block;font-size:12px;color:rgba(255,255,255,.4);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+escHtml(r.subtitle || r.category)+'</span></span>'
                    + '<i class="mdi mdi-arrow-right" style="font-size:16px;color:rgba(255,255,255,.25);flex-shrink:0;transition:transform .2s;"></i>';

                var idx = globalIdx;
                li.addEventListener('mouseenter', function(){ li.style.background='rgba(79,142,247,.12)'; activeIndex=idx; });
                li.addEventListener('mouseleave', function(){ li.style.background=(activeIndex===idx)?'rgba(79,142,247,.18)':''; });
                li.addEventListener('click', function(){ addHistory(input?input.value:'', r); saveHistory(getHistory()); window.location.href=r.url; });
                list.appendChild(li);
                globalIdx++;
            });
        });
    }

    /* ── keyboard navigation ── */
    function highlightItem(items){
        items.forEach(function(li, i){
            var active = (i === activeIndex);
            li.style.background = active ? 'rgba(79,142,247,.18)' : '';
            var t   = li.querySelector('.result-title');
            if (t)   t.style.color = active ? '#4f8ef7' : '#fff';
            var arr = li.querySelector('.mdi-arrow-right');
            if (arr) arr.style.transform = active ? 'translateX(6px)' : 'translateX(0)';
            if (active) li.scrollIntoView({block:'nearest'});
        });
    }

    /* ── event listeners ── */
    if (trigger)   trigger.addEventListener('click', open);
    var triggerSm = document.getElementById('spotlight-trigger-sm');
    if (triggerSm) triggerSm.addEventListener('click', open);
    if (escBtn)    escBtn.addEventListener('click', close);
    if (clearBtn)  clearBtn.addEventListener('click', function(){ localStorage.removeItem(HISTORY_KEY); renderHistory(); showEmpty(true); });
    if (clearMain) clearMain.addEventListener('click', function(){ localStorage.removeItem(HISTORY_KEY); renderHistory(); showEmpty(true); });
    if (overlay)   overlay.addEventListener('click', function(e){ if (e.target===overlay) close(); });

    document.addEventListener('keydown', function(e){
        if ((e.metaKey||e.ctrlKey) && e.key==='k'){ e.preventDefault(); overlay&&overlay.style.display==='flex' ? close() : open(); }
        if (e.key==='Escape' && overlay && overlay.style.display==='flex') close();
    });

    if (input) {
        input.addEventListener('keydown', function(e){
            var items = list ? Array.prototype.slice.call(list.querySelectorAll('li[data-idx]')) : [];
            if (e.key==='ArrowDown') { e.preventDefault(); activeIndex=Math.min(activeIndex+1,items.length-1); highlightItem(items); }
            else if (e.key==='ArrowUp') { e.preventDefault(); activeIndex=Math.max(activeIndex-1,0); highlightItem(items); }
            else if (e.key==='Enter' && activeIndex>=0 && currentResults[activeIndex]) {
                addHistory(input.value, currentResults[activeIndex]); saveHistory(getHistory());
                window.location.href = currentResults[activeIndex].url;
            }
        });
        input.addEventListener('input', function(){
            var q = this.value.trim();
            if (!q) {
                showEmpty(true);
                renderHistory();
                if(clearMain) clearMain.style.display = getHistory().length>0 ? 'block' : 'none';
                return;
            }
            if(clearMain) clearMain.style.display = 'none';
            renderChips(getSuggestions(q));
            performSearch(q);
        });
    }

    renderHistory();
})();
</script>
@endauth
