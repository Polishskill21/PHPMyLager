const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

let pendingDeleteId = null;

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

    const hasEnvelope = payload && typeof payload === 'object' && Object.prototype.hasOwnProperty.call(payload, 'data');
    return {
        ok: true,
        status: res.status,
        data: hasEnvelope ? payload.data : payload,
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
function filterCustomerRows() {
    const input = document.getElementById('search');
    const total = document.getElementById('customers-stat-total');
    const emptyRow = document.getElementById('customers-empty-filter-row');
    if (!input) return;

    const query = input.value.trim().toLowerCase();
    let visible = 0;

    document.querySelectorAll('.customers-table tbody tr[data-sort-row]').forEach((row) => {
        const haystack = [
            row.dataset.sortId,
            row.dataset.sortName,
            row.dataset.sortEmail,
            row.dataset.sortStreet,
            row.dataset.sortCity,
            row.dataset.sortPlz,
        ].join(' ').toLowerCase();
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
    document.getElementById('customer-form')?.reset();
    document.getElementById('f-id').value = '';
    document.getElementById('modal-form-title').textContent = 'New Customer';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Customer';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-name')?.focus();
}

async function openEdit(id) {
    clearFormErrors();

    const { ok, data, message } = await api('GET', `/customers/${id}`);
    if (!ok) {
        toast(message || 'Failed to load customer.', 'error');
        return;
    }

    document.getElementById('f-id').value = data.pKdNr ?? '';
    document.getElementById('f-name').value = data.name || '';
    document.getElementById('f-strasse').value = data.strasse || '';
    document.getElementById('f-plz').value = data.plz || '';
    document.getElementById('f-ort').value = data.ort || '';
    document.getElementById('f-email').value = data.email || '';

    document.getElementById('modal-form-title').textContent = 'Edit Customer';
    document.getElementById('modal-form-badge').textContent = `#${data.pKdNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Customer';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-name')?.focus();
}

async function submitCustomerForm(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = {
        name: document.getElementById('f-name').value.trim(),
        strasse: document.getElementById('f-strasse').value.trim(),
        plz: document.getElementById('f-plz').value.trim(),
        ort: document.getElementById('f-ort').value.trim(),
        email: document.getElementById('f-email').value.trim(),
    };

    const btn = document.getElementById('modal-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = id
        ? await api('PUT', `/customers/${id}`, payload)
        : await api('POST', '/customers', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Customer' : 'Save Customer';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed.', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(message || (id ? 'Customer updated.' : 'Customer created.'), 'success');
    window.location.reload();
}

// ── DELETE ────────────────────────────────────────────────────────────
function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('del-target-name').textContent = name || 'this customer';
    document.getElementById('del-target-id').textContent = id;
    document.getElementById('modal-del-overlay').classList.add('open');
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('modal-del-confirm');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const { ok, data, message } = await api('DELETE', `/customers/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Archive';
    closeModal('modal-del-overlay');

    if (ok) {
        toast(message || 'Customer archived.', 'success');
        window.location.reload();
    } else {
        toast(message || data?.error || 'Archive failed.', 'error');
    }

    pendingDeleteId = null;
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
function initCustomersPage() {
    document.getElementById('search')?.addEventListener('input', filterCustomerRows);
    document.getElementById('btn-add')?.addEventListener('click', openAdd);
    document.getElementById('customer-form')?.addEventListener('submit', submitCustomerForm);
    document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
    document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));
    document.getElementById('modal-del-confirm')?.addEventListener('click', confirmDelete);

    document.querySelectorAll('.customer-edit').forEach((btn) => {
        btn.addEventListener('click', () => openEdit(btn.dataset.id));
    });

    document.querySelectorAll('.customer-delete').forEach((btn) => {
        btn.addEventListener('click', () => openDelete(btn.dataset.id, btn.dataset.name));
    });

    document.querySelectorAll('.overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal('modal-form-overlay');
            closeModal('modal-del-overlay');
        }
    });

    filterCustomerRows();
}

if (document.querySelector('.customers-page')) {
    initCustomersPage();
}
