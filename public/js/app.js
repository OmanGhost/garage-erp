/* Garage ERP front-end helpers (vanilla JS + Alpine provided by Livewire) */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
    const fmt = (n, d) => (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: d ?? ERP.decimals, maximumFractionDigits: d ?? ERP.decimals });
    window.erpFmt = fmt;
    const round = (n, d = 3) => Math.round((Number(n) + Number.EPSILON) * Math.pow(10, d)) / Math.pow(10, d);
    window.erpRound = round;

    /* ---------------- top navigation: sections that do not fit move into "More" ---------------- */
    const topnav = $('[data-topnav]');
    const fitTopnav = () => {
        if (!topnav || !topnav.offsetParent) return; // hidden on mobile (slide-in menu is used)
        const more = $('[data-topnav-more]', topnav);
        const moreMenu = $('[data-topnav-more-menu]', topnav);
        const items = $$('[data-topnav-item]', topnav);
        items.forEach((li) => li.classList.remove('d-none'));
        more.classList.add('d-none');
        moreMenu.innerHTML = '';
        if (topnav.scrollWidth <= topnav.clientWidth + 1) return;
        more.classList.remove('d-none');
        // hide sections from the end until the bar (including "More") fits
        const hidden = [];
        for (let i = items.length - 1; i >= 0 && topnav.scrollWidth > topnav.clientWidth + 1; i--) {
            items[i].classList.add('d-none');
            hidden.unshift(items[i]);
        }
        hidden.forEach((li) => {
            const section = document.createElement('div');
            section.className = 'topnav-more-section';
            const sub = li.querySelector('.topnav-menu');
            if (sub) {
                section.innerHTML = sub.innerHTML; // title + links of the hidden section
            } else {
                const a = li.querySelector('a').cloneNode(true);
                a.className = 'dropdown-item' + (a.classList.contains('active') ? ' active' : '');
                section.appendChild(a);
            }
            moreMenu.appendChild(section);
        });
    };
    if (topnav) {
        fitTopnav();
        let fitTimer;
        window.addEventListener('resize', () => { clearTimeout(fitTimer); fitTimer = setTimeout(fitTopnav, 120); });
        document.fonts?.ready?.then(fitTopnav);
    }
    const menuFilter = $('[data-erp-menu-filter]');
    menuFilter?.addEventListener('input', () => {
        const q = menuFilter.value.trim().toLowerCase();
        $$('[data-menu-group]').forEach(g => {
            let any = false;
            $$('[data-menu-item]', g).forEach(a => { const ok = !q || a.textContent.toLowerCase().includes(q); a.style.display = ok ? '' : 'none'; any = any || ok; });
            g.style.display = any ? '' : 'none';
            if (q) g.querySelector('.collapse')?.classList.add('show');
        });
    });

    /* ---------------- theme ---------------- */
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-erp-theme]')) return;
        const html = document.documentElement;
        const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        fetch(ERP.themeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ theme: next }) });
    });

    /* ---------------- double submit (confirmations are handled by ui.js) ---------------- */
    document.addEventListener('submit', (e) => {
        const f = e.target;
        // prevent double submit
        if (f.dataset.submitted === '1' && !f.hasAttribute('data-allow-resubmit')) { e.preventDefault(); return; }
        f.dataset.submitted = '1';
        $$('button[type=submit], button:not([type])', f).forEach(b => { b.dataset.html = b.innerHTML; setTimeout(() => b.disabled = true, 0); });
        setTimeout(() => { f.dataset.submitted = '0'; $$('button', f).forEach(b => b.disabled = false); }, 8000);
    }, true);

    /* toasts: ERP.toast / window.erpToast are provided by ui.js */

    /* ---------------- lookup (remote searchable select) ---------------- */
    function initLookup(root) {
        if (root.dataset.ready) return; root.dataset.ready = '1';
        const input = $('input[type=text]', root), hidden = $('input[type=hidden]', root), menu = $('.lookup-menu', root);
        const type = root.dataset.lookup; let timer, items = [], idx = -1;
        const params = () => { const p = new URLSearchParams({ q: input.value }); (root.dataset.params || '').split('&').filter(Boolean).forEach(kv => { const [k, sel] = kv.split('='); const el = sel.startsWith('#') ? $(sel) : null; p.set(k, el ? el.value : sel); }); return p; };
        // the menu floats above everything (position: fixed), so tables with overflow (invoice lines…) never clip it;
        // it opens below the input, or above when there is no room, and is at least 340px wide for long part names
        const place = () => {
            const r = input.getBoundingClientRect(), vw = window.innerWidth, vh = window.innerHeight;
            const width = Math.min(Math.max(r.width, 340), vw - 16);
            const rtl = getComputedStyle(root).direction === 'rtl';
            const left = Math.max(8, Math.min(rtl ? r.right - width : r.left, vw - width - 8));
            const below = vh - r.bottom - 8, above = r.top - 8, wanted = Math.min(300, menu.scrollHeight || 300);
            Object.assign(menu.style, { position: 'fixed', width: width + 'px', left: left + 'px', right: 'auto' });
            if (below < wanted && above > below) {
                Object.assign(menu.style, { top: 'auto', bottom: (vh - r.top + 2) + 'px', maxHeight: Math.min(300, above) + 'px' });
            } else {
                Object.assign(menu.style, { bottom: 'auto', top: (r.bottom + 2) + 'px', maxHeight: Math.max(120, Math.min(300, below)) + 'px' });
            }
        };
        const onMove = () => { if (menu.classList.contains('show')) place(); };
        const hide = () => {
            menu.classList.remove('show');
            window.removeEventListener('scroll', onMove, true); window.removeEventListener('resize', onMove);
        };
        const canQuick = (ERP.quickTypes || []).includes(type) && root.dataset.quick !== '0' && !root.closest('#quickCreateModal');
        const render = () => {
            menu.innerHTML = '';
            if (!items.length) { menu.innerHTML = `<div class="lookup-item text-body-secondary">${ERP.t.noResults}</div>`; }
            items.forEach((it, i) => { const d = document.createElement('div'); d.className = 'lookup-item' + (i === idx ? ' active' : ''); d.textContent = it.text; d.addEventListener('mousedown', (ev) => { ev.preventDefault(); choose(it); }); menu.appendChild(d); });
            if (canQuick) {
                const q = input.value.trim(), d = document.createElement('div');
                d.className = 'lookup-item lookup-add';
                d.innerHTML = '<i class="bi bi-plus-circle"></i> ';
                d.appendChild(document.createTextNode(q ? ERP.t.addNewNamed.replace(':q', q) : ERP.t.addNew));
                d.addEventListener('mousedown', (ev) => { ev.preventDefault(); hide(); erpQuickCreate(type, q, params(), choose); });
                menu.appendChild(d);
            }
            if (document.activeElement !== input) return; // focus moved on while the search was running
            menu.classList.add('show');
            place();
            menu.querySelector('.lookup-item.active')?.scrollIntoView({ block: 'nearest' });
            window.addEventListener('scroll', onMove, true); window.addEventListener('resize', onMove);
        };
        const choose = (it) => {
            hidden.value = it.id; input.value = it.text; hide();
            hidden.dispatchEvent(new Event('input', { bubbles: true })); hidden.dispatchEvent(new Event('change', { bubbles: true }));
            root.dispatchEvent(new CustomEvent('lookup-selected', { bubbles: true, detail: it }));
        };
        const search = () => {
            clearTimeout(timer);
            timer = setTimeout(() => fetch(`${ERP.lookupUrl}/${type}?${params()}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(j => { items = j.results || []; idx = items.length ? 0 : -1; render(); }), 220);
        };
        input.addEventListener('input', () => { if (!input.value) { hidden.value = ''; hidden.dispatchEvent(new Event('input', { bubbles: true })); } search(); });
        input.addEventListener('focus', search);
        input.addEventListener('blur', () => setTimeout(hide, 150));
        input.addEventListener('keydown', (e) => {
            if (!menu.classList.contains('show')) return;
            if (e.key === 'ArrowDown') { idx = Math.min(idx + 1, items.length - 1); render(); e.preventDefault(); }
            if (e.key === 'ArrowUp') { idx = Math.max(idx - 1, 0); render(); e.preventDefault(); }
            if (e.key === 'Enter' && idx >= 0 && items[idx]) { choose(items[idx]); e.preventDefault(); }
            if (e.key === 'Escape') hide();
        });
    }
    window.erpInitLookups = (r = document) => $$('[data-lookup]', r).forEach(initLookup);

    /* ---------------- quick add from a search field (customer, vehicle, part, service, supplier) ---------------- */
    window.erpQuickCreate = function (type, q, searchParams, onCreated) {
        let el = document.getElementById('quickCreateModal');
        if (!el) {
            el = document.createElement('div');
            el.id = 'quickCreateModal'; el.className = 'modal fade'; el.tabIndex = -1;
            el.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="${ERP.t.cancel}"></button></div>
                <div class="modal-body"></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${ERP.t.cancel}</button>
                    <button type="button" class="btn btn-primary" data-quick-save><i class="bi bi-check2"></i> ${ERP.t.save}</button></div></div></div>`;
            document.body.appendChild(el);
        }
        const modal = bootstrap.Modal.getOrCreateInstance(el), body = $('.modal-body', el), saveBtn = $('[data-quick-save]', el);
        $('.modal-title', el).textContent = (ERP.t.quickTitles || {})[type] || ERP.t.addNew;
        body.innerHTML = '<div class="text-center py-5"><span class="spinner-border"></span></div>';
        const p = new URLSearchParams({ q: q || '' });
        if (searchParams && searchParams.get('customer_id')) p.set('customer_id', searchParams.get('customer_id'));
        fetch(`${ERP.quickUrl}/${type}?${p}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(html => { body.innerHTML = html; erpInitLookups(body); setTimeout(() => $('input:not([type=hidden]),select', body)?.focus(), 300); })
            .catch(() => { body.innerHTML = `<div class="alert alert-danger mb-0">${ERP.t.error}</div>`; });
        const clearErrors = (form) => {
            $$('.is-invalid', form).forEach(x => x.classList.toggle('is-invalid', false));
            $$('.quick-err', form).forEach(x => x.parentNode && x.parentNode.removeChild(x));
        };
        const submit = () => {
            const form = $('form[data-quick-form]', body); if (!form) return;
            clearErrors(form);
            saveBtn.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(async r => {
                    const j = await r.json().catch(() => ({}));
                    if (r.ok && j.item) { modal.hide(); onCreated(j.item); if (ERP.toast) ERP.toast(j.message || '', 'success'); return; }
                    const errs = j.errors || { _: [j.message || ERP.t.error] };
                    let first = null;
                    Object.entries(errs).forEach(([name, msgs]) => {
                        const field = form.querySelector(`[name="${name}"]`);
                        const box = document.createElement('div'); box.className = 'invalid-feedback d-block quick-err'; box.textContent = msgs[0];
                        if (field) {
                            const holder = field.closest('.lookup') || field;
                            const visible = field.type === 'hidden' ? holder.querySelector('input[type=text]') : field;
                            if (visible) visible.classList.add('is-invalid');
                            holder.after(box); first = first || visible;
                        } else { box.className = 'alert alert-danger quick-err'; form.prepend(box); }
                    });
                    if (first && first.focus) first.focus();
                })
                .catch(() => { if (ERP.toast) ERP.toast(ERP.t.error, 'danger'); })
                .finally(() => { saveBtn.disabled = false; });
        };
        saveBtn.onclick = submit;
        body.onkeydown = (e) => { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && !e.target.closest('.lookup')) { e.preventDefault(); submit(); } };
        modal.show();
    };
    document.addEventListener('DOMContentLoaded', () => erpInitLookups());
    document.addEventListener('livewire:navigated', () => erpInitLookups());
    new MutationObserver(() => erpInitLookups()).observe(document.documentElement, { childList: true, subtree: true });

    /* ---------------- line items (Alpine component) ---------------- */
    window.lineItems = function (cfg) {
        return {
            rows: (cfg.rows && cfg.rows.length ? cfg.rows : []).map(r => Object.assign({ uid: Math.random().toString(36).slice(2) }, r)),
            mode: cfg.mode || 'sales', priceField: cfg.priceField || 'sale_price', defaultTax: Number(cfg.tax ?? 5), lookup: cfg.lookup || 'parts',
            allowServices: cfg.allowServices || false, extraDiscount: 0,
            init() { if (!this.rows.length) this.add(); },
            add(type) { this.rows.push({ uid: Math.random().toString(36).slice(2), item_type: type || (this.lookup === 'services' ? 'service' : 'part'), part_id: '', service_id: '', description: '', qty: 1, unit_price: 0, discount: 0, tax_rate: this.defaultTax, label: '' }); this.$nextTick(() => erpInitLookups(this.$el)); },
            remove(i) { this.rows.splice(i, 1); if (!this.rows.length) this.add(); },
            picked(i, e) {
                const it = e.detail, r = this.rows[i], x = it.extra || {};
                if (r.item_type === 'service') { r.service_id = it.id; r.unit_price = Number(x.price || 0); }
                else { r.part_id = it.id; r.unit_price = Number(x[this.priceField] || x.sale_price || 0); }
                r.tax_rate = Number(x.tax_rate ?? this.defaultTax); r.description = it.text.split(' [')[0].replace(/^[^—]*—\s*/, '') || it.text; r.label = it.text;
            },
            net(r) { return round(Math.max(0, Number(r.qty || 0) * Number(r.unit_price || 0) - Number(r.discount || 0)), 3); },
            tax(r) { return round(this.net(r) * Number(r.tax_rate || 0) / 100, 3); },
            get subtotal() { return round(this.rows.reduce((s, r) => s + Number(r.qty || 0) * Number(r.unit_price || 0), 0), 3); },
            get discount() { return round(this.rows.reduce((s, r) => s + Math.min(Number(r.discount || 0), Number(r.qty || 0) * Number(r.unit_price || 0)), 0), 3); },
            get taxTotal() { return round(this.rows.reduce((s, r) => s + this.tax(r), 0), 3); },
            get total() { return round(this.subtotal - this.discount + this.taxTotal, 3); },
            fmt(n) { return fmt(n); },
        };
    };

    /* ---------------- barcode scanner (keyboard wedge) ---------------- */
    let scanBuf = '', scanAt = 0;
    document.addEventListener('keydown', (e) => {
        const now = Date.now();
        if (now - scanAt > 60) scanBuf = '';
        scanAt = now;
        if (e.key === 'Enter' && scanBuf.length >= 6) {
            const active = document.activeElement;
            if (!active || !['INPUT', 'TEXTAREA'].includes(active.tagName) || active.dataset.scanTarget !== undefined) {
                window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { code: scanBuf } }));
            }
            scanBuf = ''; return;
        }
        if (e.key.length === 1) scanBuf += e.key;
    });

    /* ---------------- signature pad ---------------- */
    window.erpSignature = function (canvasId, inputId) {
        const c = document.getElementById(canvasId), input = document.getElementById(inputId);
        if (!c || !window.SignaturePad) return null;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio; c.getContext('2d').scale(ratio, ratio);
        const pad = new SignaturePad(c, { backgroundColor: 'rgb(255,255,255)' });
        pad.addEventListener('endStroke', () => input.value = pad.isEmpty() ? '' : pad.toDataURL('image/png'));
        return pad;
    };

    /* ---------------- real-time (Laravel Reverb via Echo) ---------------- */
    if (ERP.reverb && window.Echo && window.Pusher) {
        try {
            window.EchoClient = new window.Echo.default({
                broadcaster: 'reverb', key: ERP.reverb.key, wsHost: ERP.reverb.host, wsPort: ERP.reverb.port, wssPort: ERP.reverb.port,
                forceTLS: ERP.reverb.scheme === 'https', enabledTransports: ['ws', 'wss'], authEndpoint: '/broadcasting/auth',
                auth: { headers: { 'X-CSRF-TOKEN': csrf() } },
            });
            if (ERP.companyId) {
                EchoClient.private('company.' + ERP.companyId).listen('.job-card.status', (e) => {
                    document.dispatchEvent(new CustomEvent('erp-jobcard-updated', { detail: e }));
                    if (window.Livewire) Livewire.dispatch('jobcard-updated', e);
                });
            }
            if (ERP.userId) {
                EchoClient.private('App.Models.User.' + ERP.userId).notification((n) => { erpToast(n.title || 'Notification', 'info'); if (window.Livewire) Livewire.dispatch('notification-received'); });
            }
        } catch (e) { console.warn('Realtime disabled', e); }
    }

    /* ---------------- job card guide: open the related tab and scroll to it; tooltips ---------------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-guide-tab]');
        if (!btn || !window.bootstrap) return;
        const trigger = document.querySelector(`[data-bs-target="${btn.dataset.guideTab}"]`);
        if (trigger) { bootstrap.Tab.getOrCreateInstance(trigger).show(); trigger.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    document.addEventListener('DOMContentLoaded', () => {
        if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
    });

    /* ---------------- PWA ---------------- */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
})();

/* section bar: keep the active page visible when the bar scrolls horizontally (mobile) */
document.addEventListener('DOMContentLoaded', () => {
    const a = document.querySelector('[data-subnav] .app-subnav-link.active');
    if (a) a.scrollIntoView({ block: 'nearest', inline: 'center' });
});

/* phones: search toggle in the top bar, and tables outside a scroll container get one */
document.addEventListener('DOMContentLoaded', () => {
    const bar = document.querySelector('.app-topbar');
    document.querySelector('[data-search-toggle]')?.addEventListener('click', (e) => {
        const open = bar.classList.toggle('search-open');
        e.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) setTimeout(() => bar.querySelector('.topbar-search input')?.focus(), 50);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') bar?.classList.remove('search-open'); });
    document.querySelectorAll('main table.table').forEach((t) => {
        if (t.closest('.table-responsive, .modal, .dropdown-menu, .line-items-wrap')) return;
        const w = document.createElement('div');
        w.className = 'table-responsive';
        t.parentNode.insertBefore(w, t);
        w.appendChild(t);
    });
});
