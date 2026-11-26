/*
 * Garage ERP — unified popups (no dependencies; loaded on every layout before app.js)
 *
 *   ERP.confirm({ title, message, type: 'danger'|'warning'|'primary', ok, cancel }) -> Promise<boolean>
 *   ERP.toast(message, type = 'success'|'danger'|'warning'|'info', { title, timeout, action: { label, url, blank } })
 *
 * - Every element/form with data-confirm="message" asks through the styled dialog (optional
 *   data-confirm-type / data-confirm-title / data-confirm-ok). Forms sent with method DELETE
 *   always ask, even without data-confirm (opt out with data-no-confirm).
 * - Server flash messages (<script type="application/json" id="erp-flash">) are shown as toasts.
 */
(function () {
    'use strict';

    const ar = (document.documentElement.lang || '').startsWith('ar');
    const T = ar ? {
        ok: 'تأكيد', cancel: 'إلغاء', delete: 'نعم، احذف', deleteTitle: 'تأكيد الحذف', yes: 'نعم، متابعة',
        titles: { danger: 'تأكيد الحذف أو الإلغاء', warning: 'هل أنت متأكد؟', primary: 'تأكيد العملية' },
        deleteMsg: 'سيتم حذف هذا السجل. لا يمكن التراجع عن هذه العملية. هل تريد المتابعة؟',
        toast: { success: 'تمت العملية بنجاح', danger: 'حدث خطأ', error: 'حدث خطأ', warning: 'تنبيه', info: 'معلومة' },
        close: 'إغلاق',
    } : {
        ok: 'Confirm', cancel: 'Cancel', delete: 'Yes, delete', deleteTitle: 'Confirm delete', yes: 'Yes, continue',
        titles: { danger: 'Confirm delete or cancel', warning: 'Are you sure?', primary: 'Confirm action' },
        deleteMsg: 'This record will be deleted. This cannot be undone. Do you want to continue?',
        toast: { success: 'Done', danger: 'Something went wrong', error: 'Something went wrong', warning: 'Warning', info: 'Information' },
        close: 'Close',
    };
    const ICONS = {
        danger: 'bi-trash3', warning: 'bi-exclamation-triangle', primary: 'bi-check2-circle',
        success: 'bi-check-lg', error: 'bi-x-lg', info: 'bi-info-lg',
    };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const ERP = (window.ERP = window.ERP || {});

    /* ============================================================ confirmation dialog */
    ERP.confirm = function (opts = {}) {
        const type = ['danger', 'warning', 'primary'].includes(opts.type) ? opts.type : 'warning';
        return new Promise((resolve) => {
            const previous = document.activeElement;
            const back = document.createElement('div');
            back.className = 'ui-dialog-backdrop';
            back.innerHTML = `
                <div class="ui-dialog ui-dialog--${type}" role="alertdialog" aria-modal="true" aria-labelledby="ui-dlg-title" aria-describedby="ui-dlg-text">
                    <div class="ui-dialog-body">
                        <span class="ui-dialog-icon"><i class="bi ${ICONS[type]}"></i></span>
                        <h2 class="ui-dialog-title" id="ui-dlg-title">${esc(opts.title || T.titles[type])}</h2>
                        <p class="ui-dialog-text" id="ui-dlg-text">${esc(opts.message || '')}</p>
                    </div>
                    <div class="ui-dialog-actions">
                        <button type="button" class="btn btn-${type === 'primary' ? 'primary' : type}" data-ok>${esc(opts.ok || (type === 'danger' ? T.yes : T.ok))}</button>
                        <button type="button" class="btn btn-light border" data-cancel>${esc(opts.cancel || T.cancel)}</button>
                    </div>
                </div>`;
            document.body.appendChild(back);
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => back.classList.add('show'));
            const okBtn = back.querySelector('[data-ok]');
            const cancelBtn = back.querySelector('[data-cancel]');
            // destructive actions focus "cancel" so Enter on a stray keypress does not delete
            (type === 'danger' ? cancelBtn : okBtn).focus();

            const close = (result) => {
                document.removeEventListener('keydown', onKey, true);
                back.classList.remove('show');
                document.body.style.overflow = '';
                setTimeout(() => back.remove(), 180);
                previous?.focus?.();
                resolve(result);
            };
            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                if (e.key === 'Tab') { // keep focus inside the dialog
                    e.preventDefault();
                    (document.activeElement === okBtn ? cancelBtn : okBtn).focus();
                }
            };
            document.addEventListener('keydown', onKey, true);
            okBtn.addEventListener('click', () => close(true));
            cancelBtn.addEventListener('click', () => close(false));
            back.addEventListener('mousedown', (e) => { if (e.target === back) close(false); });
        });
    };

    /* ============================================================ toast notifications */
    ERP.toast = function (message, type = 'success', opts = {}) {
        if (!message) return;
        type = type === 'error' ? 'danger' : type;
        let box = document.querySelector('.ui-toasts');
        if (!box) {
            box = document.createElement('div');
            box.className = 'ui-toasts';
            box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }
        const timeout = opts.timeout ?? (type === 'danger' ? 9000 : type === 'warning' ? 7000 : 5000);
        const el = document.createElement('div');
        el.className = `ui-toast ui-toast--${type}`;
        el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        const body = Array.isArray(message) ? `<ul>${message.map((m) => `<li>${esc(m)}</li>`).join('')}</ul>` : esc(message);
        const action = opts.action ? `<a class="ui-toast-action" href="${esc(opts.action.url)}" ${opts.action.blank ? 'target="_blank" rel="noopener"' : ''}>
            ${opts.action.icon ? `<i class="bi ${esc(opts.action.icon)}"></i>` : ''}${esc(opts.action.label)}</a>` : '';
        el.innerHTML = `
            <span class="ui-toast-icon"><i class="bi ${ICONS[type === 'danger' ? 'error' : type] || ICONS.info}"></i></span>
            <div class="ui-toast-content"><div class="ui-toast-title">${esc(opts.title || T.toast[type] || '')}</div><div class="ui-toast-text">${body}</div>${action}</div>
            <button type="button" class="ui-toast-close" aria-label="${T.close}">&times;</button>
            ${timeout ? `<span class="ui-toast-progress" style="animation-duration:${timeout}ms"></span>` : ''}`;
        box.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        const remove = () => { el.classList.add('hide'); setTimeout(() => el.remove(), 220); };
        el.querySelector('.ui-toast-close').addEventListener('click', remove);
        if (timeout) {
            el.querySelector('.ui-toast-progress').addEventListener('animationend', remove);
        }
        return el;
    };
    window.erpToast = (msg, type = 'success') => ERP.toast(msg, type);
    document.addEventListener('erp-toast', (e) => {
        const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
        ERP.toast(d?.message || '', d?.type || 'success', { title: d?.title });
    });

    /* ============================================================ confirmations for data-confirm and DELETE forms */
    const isDeleteForm = (f) => !!f && (f.querySelector('input[name="_method"]')?.value || '').toUpperCase() === 'DELETE';
    const guessType = (el, form) => {
        const explicit = el?.dataset.confirmType || form?.dataset.confirmType;
        if (explicit) return explicit;
        if (isDeleteForm(form)) return 'danger';
        const text = `${el?.dataset.confirm || ''} ${form?.dataset.confirm || ''} ${el?.textContent || ''}`.toLowerCase();
        if (/حذف|إلغاء|الغاء|رفض|أرشف|تعطيل|إيقاف|شطب|عكس|delete|remove|cancel|reject|archive|disable|suspend|void|reverse|discard|revoke/.test(text)) return 'danger';
        if (/ترحيل|اعتماد|تسليم|دفع|سداد|إرسال|تحويل|post|approve|deliver|pay|send|submit|transfer|renew|activate|confirm/.test(text)) return 'primary';
        return 'warning';
    };
    const optionsFor = (el, form) => {
        const type = guessType(el, form);
        const msg = el?.dataset.confirm || form?.dataset.confirm || (isDeleteForm(form) ? T.deleteMsg : (window.ERP?.t?.confirm || T.titles.warning));
        const del = isDeleteForm(form);
        return {
            type, message: msg,
            title: el?.dataset.confirmTitle || form?.dataset.confirmTitle || (del ? T.deleteTitle : undefined),
            ok: el?.dataset.confirmOk || form?.dataset.confirmOk || (del ? T.delete : undefined),
        };
    };

    // clicks on links / buttons carrying data-confirm (window capture: runs before other handlers)
    window.addEventListener('click', async (e) => {
        const el = e.target.closest('[data-confirm]:not(form)');
        if (!el || el.disabled) return;
        if (el.dataset.uiConfirmed) { delete el.dataset.uiConfirmed; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        if (await ERP.confirm(optionsFor(el, el.form || el.closest('form')))) {
            el.dataset.uiConfirmed = '1';
            const form = el.form || el.closest('form');
            if (form) form.dataset.uiConfirmed = '1';
            el.click(); // replay the original action (link navigation, submit with this button, Livewire…)
        }
    }, true);

    // form submissions: data-confirm on the form, or any DELETE form
    window.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-confirm')) return;
        if (!form.dataset.confirm && !isDeleteForm(form)) return;
        if (form.dataset.uiConfirmed) { delete form.dataset.uiConfirmed; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter;
        if (await ERP.confirm(optionsFor(submitter, form))) {
            form.dataset.uiConfirmed = '1';
            submitter && submitter.form === form ? form.requestSubmit(submitter) : form.requestSubmit();
        }
    }, true);

    /* ============================================================ server flash messages -> toasts */
    const showFlash = () => {
        const node = document.getElementById('erp-flash');
        if (!node) return;
        try {
            JSON.parse(node.textContent || '[]').forEach((f) => ERP.toast(f.message, f.type, { title: f.title, action: f.action, timeout: f.timeout }));
        } catch (err) { /* ignore malformed flash */ }
        node.remove();
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', showFlash) : showFlash();
})();
