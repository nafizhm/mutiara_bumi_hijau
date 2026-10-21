@auth
<script src="{{ asset('js/approval-status.js') }}?v={{ filemtime(public_path('js/approval-status.js')) }}" data-status-url="{{ route('approvals.statuses') }}" defer></script>
@endauth
@if(auth()->check() && !auth()->user()->isSuperAdmin())
@php
    $configuredApprovalRules = \App\Services\ApprovalSettings::rules();
    $approvalSettingsInstalled = \Illuminate\Support\Facades\Schema::hasTable('approval_settings');
    $approvalRoutes = [];
    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $approvalRoute) {
        foreach (array_intersect($approvalRoute->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $approvalMethod) {
            $approvalRequest = \Illuminate\Http\Request::create('/', $approvalMethod);
            $approvalRequest->setRouteResolver(fn () => $approvalRoute);
            $approvalMenu = \App\Services\MenuAccess::menu($approvalRequest);
            $approvalAbility = \App\Services\MenuAccess::ability($approvalRequest);
            if ($approvalMenu && in_array($approvalAbility, ['edit', 'hapus'])
                && (!$approvalSettingsInstalled || ($configuredApprovalRules[$approvalMenu][$approvalAbility] ?? false))) {
                $approvalRoutes[] = ['path' => '/'.$approvalRoute->uri(), 'method' => $approvalMethod];
            }
        }
    }
@endphp
<script>
(() => {
    const approvalRoutes = @json($approvalRoutes);
    if (location.pathname === new URL(@json(route('approval-settings.index')), location.href).pathname) return;
    function needsReason(url, method) {
        const path = new URL(url || location.href, location.href).pathname;
        return approvalRoutes.some(rule => {
            if (rule.method !== method.toUpperCase()) return false;
            const pattern = new URL(rule.path.replace(/\{[^}]+\}/g, '__PARAM__'), location.origin).pathname;
            const escaped = pattern.split('/').map(part => part === '__PARAM__' ? '[^/]+' : part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('/');
            return new RegExp('^' + escaped + '/?$').test(path);
        });
    }
    function reason() {
        let value = window.prompt('Alasan edit/hapus (minimal 5 karakter). Perubahan akan menunggu persetujuan Super Admin atau Direktur.');
        if (value === null) return null;
        value = value.trim();
        if (value.length < 5 || value.length > 2000) { window.alert('Alasan wajib 5–2000 karakter.'); return null; }
        return value;
    }
    function prepare(form) {
        const method = form.querySelector('[name="_method"]')?.value || form.method;
        if (!needsReason(form.action, method)) return true;
        const value = reason();
        if (value === null) return false;
        let field = form.querySelector('[name="approval_reason"]');
        if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'approval_reason'; form.appendChild(field); }
        field.value = value;
        return true;
    }
    document.addEventListener('submit', event => {
        if (!prepare(event.target)) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    const submit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() { if (prepare(this)) submit.call(this); };
    if (window.jQuery) $.ajaxPrefilter((options, original, xhr) => {
        const data = options.data;
        const override = data instanceof FormData ? data.get('_method') : typeof data === 'string' ? new URLSearchParams(data).get('_method') : data?._method;
        if (!needsReason(options.url, override || options.type || 'GET')) return;
        const existing = data instanceof FormData ? data.get('approval_reason') : typeof data === 'string' ? new URLSearchParams(data).get('approval_reason') : data?.approval_reason;
        const value = existing || reason();
        if (value === null) { xhr.abort(); return; }
        xhr.setRequestHeader('X-Approval-Reason', value);
    });
    const fetchOriginal = window.fetch;
    window.fetch = function(input, options = {}) {
        const body = options.body;
        const override = body instanceof FormData || body instanceof URLSearchParams ? body.get('_method') : null;
        if (needsReason(typeof input === 'string' ? input : input.url, override || options.method || input.method || 'GET')) {
            const value = (body instanceof FormData ? body.get('approval_reason') : null) || reason();
            if (value === null) return Promise.reject(new DOMException('Pengajuan dibatalkan', 'AbortError'));
            options.headers = new Headers(options.headers || (input instanceof Request ? input.headers : {}));
            options.headers.set('X-Approval-Reason', value);
        }
        return fetchOriginal.call(this, input, options);
    };
})();
</script>
@endif
