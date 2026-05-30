const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function api(method, path, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            Accept: 'application/json',
        },
    };

    if (body) opts.body = JSON.stringify(body);

    const res = await fetch('/api' + path, opts);
    const payload = res.status === 204 ? null : await res.json().catch(() => ({}));

    if (!res.ok) {
        const errorPayload = payload && typeof payload === 'object' ? payload : {};
        return {
            ok: false,
            status: res.status,
            data: errorPayload,
            message: errorPayload.message || 'Request failed.',
            errors: errorPayload.errors || {},
        };
    }

    return {
        ok: true,
        status: res.status,
        data: payload?.data ?? payload,
        message: payload?.message || '',
        errors: {},
    };
}

function toast(msg, type = 'info') {
    const area = document.getElementById('toast-area');
    if (!area) return;

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span> ${esc(msg)}`;
    area.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── SEARCH FILTER ─────────────────────────────────────────────────────
function filterGroupRows() {
    const input = document.getElementById('warehouse-search');
    const total = document.getElementById('warehouse-stat-total');
    const emptyRow = document.getElementById('warehouse-empty-filter-row');
    if (!input) return;

    const query = input.value.trim().toLowerCase();
    let visible = 0;

    document.querySelectorAll('.warehouse-table tbody tr[data-sort-row]').forEach((row) => {
        const haystack = [row.dataset.sortId, row.dataset.sortName].join(' ').toLowerCase();
        const match = !query || haystack.includes(query);

        row.hidden = !match;
        if (match) visible += 1;
    });

    if (total) total.textContent = String(visible);
    if (emptyRow) emptyRow.hidden = !query || visible > 0;
}

// ── ADD / EDIT FORM ───────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('group-form')?.reset();
    document.getElementById('f-id').value = '';
    document.getElementById('group-form-title').textContent = 'New Product Group';
    document.getElementById('group-form-badge').textContent = 'CREATE';
    document.getElementById('group-form-submit').textContent = 'Save Product Group';
    document.getElementById('modal-group-form-overlay').classList.add('open');
    document.getElementById('f-warengruppe')?.focus();
}

function openEdit(id, name) {
    clearFormErrors();
    document.getElementById('f-id').value = id ?? '';
    document.getElementById('f-warengruppe').value = name || '';
    document.getElementById('group-form-title').textContent = 'Edit Product Group';
    document.getElementById('group-form-badge').textContent = `#${id}`;
    document.getElementById('group-form-submit').textContent = 'Update Product Group';
    document.getElementById('modal-group-form-overlay').classList.add('open');
    document.getElementById('f-warengruppe')?.focus();
}

async function submitGroupForm(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const name = document.getElementById('f-warengruppe').value.trim();
    const payload = { warengruppe: name === '' ? null : name };

    const btn = document.getElementById('group-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = id
        ? await api('PUT', `/warehouse-groups/${id}`, payload)
        : await api('POST', '/warehouse-groups', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Product Group' : 'Save Product Group';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed.', 'error');
        return;
    }

    closeModal('modal-group-form-overlay');
    toast(message || (id ? 'Product group updated.' : 'Product group created.'), 'success');
    window.location.reload();
}

// ── HELPERS ───────────────────────────────────────────────────────────
function applyValidationErrors(data) {
    Object.entries(data?.errors || {}).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : String(messages);
        const err = document.getElementById('err-' + key);
        const field = document.getElementById('f-' + key);

        if (err) err.textContent = msg;
        if (field) field.classList.add('invalid');
    });
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach((el) => { el.textContent = ''; });
    document.querySelectorAll('.form-input.invalid, .form-select.invalid').forEach((el) => el.classList.remove('invalid'));
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

// ── INIT ──────────────────────────────────────────────────────────────
function initWarehousePage() {
    document.getElementById('warehouse-search')?.addEventListener('input', filterGroupRows);
    document.getElementById('btn-add-group')?.addEventListener('click', openAdd);
    document.getElementById('group-form')?.addEventListener('submit', submitGroupForm);
    document.getElementById('group-form-cancel')?.addEventListener('click', () => closeModal('modal-group-form-overlay'));

    document.querySelectorAll('.group-edit').forEach((btn) => {
        btn.addEventListener('click', () => openEdit(btn.dataset.id, btn.dataset.name));
    });

    document.querySelectorAll('.overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal('modal-group-form-overlay');
        }
    });

    filterGroupRows();
}

if (document.querySelector('.warehouse-page')) {
    initWarehousePage();
}
