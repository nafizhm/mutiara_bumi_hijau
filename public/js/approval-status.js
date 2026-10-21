(() => {
    const endpoint = document.currentScript.dataset.statusUrl;
    const controls = 'a[href], button, input[type="submit"]';
    const blocked = new Map();
    const badges = new Map();
    let pending = [], loading = false, scheduled = false;
    const path = value => {
        try { return new URL(value, location.href).pathname.replace(/\/$/, ''); }
        catch { return ''; }
    };
    function isPending(control) {
        const form = control.form || control.closest('form');
        const method = (form?.querySelector('[name="_method"]')?.value || form?.method || '').toUpperCase();
        const submit = form && (control.type === 'submit' || (control.tagName === 'BUTTON' && !control.type));
        const mutation = /edit|delete|hapus|cancel|restore|setting|upload/i.test(control.className + ' ' + control.id)
            || /^(edit|hapus|cancel|restore|simpan|update|ubah|upload|arsipkan)$/i.test(control.textContent.trim())
            || (submit && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method));
        if (!mutation) return false;
        const urls = [control.getAttribute('href'), control.dataset.url, form?.getAttribute('action')]
            .filter(value => value && value !== '#' && !value.startsWith('javascript:')).map(path);
        // Modal edit forms usually set their destination only when submitting.
        const record = control.dataset.id || form?.querySelector('[name="primary_id"], [name="id"], [name="id_user"]')?.value;
        return pending.some(item => item.urls.some(url => urls.includes(path(url)))
            || (record && item.record === String(record) && item.pages.some(page => path(page) === path(location.href))));
    }
    function scan() {
        scheduled = false;
        for (const [control, state] of blocked) {
            if (!control.isConnected) { blocked.delete(control); continue; }
            if (!isPending(control)) {
                control.hidden = state.hidden;
                control.style.display = state.display;
                blocked.delete(control);
            }
        }
        document.querySelectorAll(controls).forEach(control => {
            if (blocked.has(control) || !isPending(control)) return;
            blocked.set(control, {hidden: control.hidden, display: control.style.display});
            control.hidden = true;
            control.style.display = 'none';
        });
        const groups = new Map();
        for (const control of blocked.keys()) {
            const group = control.closest('td, th, .modal-footer')
                || control.closest('form')?.parentElement || control.parentElement;
            if (!groups.has(group)) groups.set(group, control);
        }
        for (const [group, badge] of badges) {
            if (!groups.has(group) || !group.isConnected) {
                badge.remove();
                badges.delete(group);
            }
        }
        for (const [group, control] of groups) {
            if (badges.has(group) && badges.get(group).isConnected) continue;
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark approval-pending-badge';
            badge.textContent = 'Menunggu persetujuan';
            // Keep one badge outside the delete form, beside the action buttons.
            const form = control.closest('form');
            if (form && group.contains(form)) form.after(badge);
            else control.after(badge);
            badges.set(group, badge);
        }
    }
    function schedule() {
        if (!scheduled) { scheduled = true; requestAnimationFrame(scan); }
    }
    async function refresh() {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const response = await fetch(endpoint, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (response.ok) { pending = (await response.json()).data || []; schedule(); }
        } finally { loading = false; }
    }
    document.addEventListener('click', event => {
        const control = event.target.closest(controls);
        if (control && isPending(control)) { event.preventDefault(); event.stopImmediatePropagation(); schedule(); }
    }, true);
    document.addEventListener('submit', event => {
        if ([...event.target.querySelectorAll('button, input[type="submit"]')].some(isPending)) {
            event.preventDefault(); event.stopImmediatePropagation(); schedule();
        }
    }, true);
    new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true, attributes: true,
        attributeFilter: ['action', 'data-url', 'data-id', 'href', 'value']});
    if (window.jQuery) {
        $(document).on('draw.dt', schedule);
        $(document).ajaxComplete((event, xhr, settings) => {
            if (path(settings.url) !== path(endpoint)) refresh().catch(() => {});
        });
    }
    const fetchOriginal = window.fetch;
    window.fetch = async function (input, options) {
        const response = await fetchOriginal.call(this, input, options);
        const url = typeof input === 'string' ? input : input.url;
        const method = (options?.method || input.method || 'GET').toUpperCase();
        if (path(url) !== path(endpoint) && !['GET', 'HEAD'].includes(method)) refresh().catch(() => {});
        return response;
    };
    document.addEventListener('visibilitychange', () => refresh().catch(() => {}));
    window.setInterval(() => refresh().catch(() => {}), 20000);
    refresh().catch(() => {});
})();
