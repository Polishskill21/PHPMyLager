// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const CAN_WRITE = document.querySelector('meta[name="can-write"]')?.content === 'true';
const CAN_DELETE = document.querySelector('meta[name="can-delete"]')?.content === 'true';
const ICONS = {
    edit: '/icons/lucide/pencil.png',
    del: '/icons/lucide/trash-2.png',
};

// ── STATE ─────────────────────────────────────────────────────────────
let allCustomers = [];
let filtered = [];
let sortKey = 'pKdNr';
let sortDir = 1;
let pendingDeleteId = null;

// ── API HELPERS ───────────────────────────────────────────────────────
async function api(method, path, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
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

// ── TOAST ─────────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span> ${msg}`;
    document.getElementById('toast-area')?.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ── LOAD DATA ─────────────────────────────────────────────────────────
async function loadCustomers() {
    setTableState('customers-body', 7, '<div class="loading-row"><div class="spinner"></div> Loading customers…</div>');

    const { ok, data } = await api('GET', '/customers');
    if (!ok) {
        toast('Failed to load customers', 'error');
        setTableState('customers-body', 7, '<div class="empty-state"><p>Failed to load customers.</p></div>');
        return;
    }

    const list = Array.isArray(data) ? data : [];
    allCustomers = list.map(normalizeCustomer);
    applyFilters();
}

function normalizeCustomer(entry) {
    const customer = entry?.customer || entry || {};

    return {
        pKdNr: customer.pKdNr,
        name: customer.name || '',
        strasse: customer.strasse || '',
        plz: customer.plz,
        ort: customer.ort || '',
        email: customer.email || '',
    };
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q = document.getElementById('search').value.trim().toLowerCase();

    filtered = allCustomers.filter(c => {
        if (!q) return true;

        const haystack = [
            c.pKdNr,
            c.name,
            c.email,
            c.ort,
            c.plz,
            c.strasse,
        ].join(' ').toLowerCase();

        return haystack.includes(q);
    });

    filtered.sort((a, b) => {
        let av = a[sortKey];
        let bv = b[sortKey];

        if (['pKdNr', 'plz'].includes(sortKey)) {
            av = parseFloat(av) || 0;
            bv = parseFloat(bv) || 0;
            return (av - bv) * sortDir;
        }

        if (typeof av === 'string') {
            av = av.toLowerCase();
            bv = (bv || '').toLowerCase();
        }

        return av > bv ? sortDir : (av < bv ? -sortDir : 0);
    });

    renderRows();
}

// ── TABLE RENDERING ──────────────────────────────────────────────────
function renderRows() {
    const body = document.getElementById('customers-body');
    if (!body) return;

    if (filtered.length === 0) {
        setTableState('customers-body', 7, '<div class="empty-state"><p>No customers match your search.</p></div>');
        return;
    }

    body.innerHTML = '';
    const fragment = document.createDocumentFragment();
    filtered.forEach(customer => fragment.appendChild(buildRow(customer)));
    body.appendChild(fragment);
}

function buildRow(customer) {
    const editBtn = CAN_WRITE
        ? `<button class="btn-icon edit" title="Edit" data-id="${customer.pKdNr}"><img class="action-icon" src="${ICONS.edit}" alt="Edit"></button>`
        : '';

    const delBtn = CAN_DELETE
        ? `<button class="btn-icon del" title="Archive" data-id="${customer.pKdNr}"><img class="action-icon" src="${ICONS.del}" alt="Archive"></button>`
        : '';

    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="cell-id">#${customer.pKdNr ?? '—'}</td>
        <td class="cell-name" title="${esc(customer.name)}">${esc(customer.name || '—')}</td>
        <td class="cell-muted" title="${esc(customer.email)}">${esc(customer.email || '—')}</td>
        <td title="${esc(customer.strasse)}">${esc(customer.strasse || '—')}</td>
        <td title="${esc(customer.ort)}">${esc(customer.ort || '—')}</td>
        <td class="cell-number">${customer.plz ?? '—'}</td>
        <td class="cell-actions"><div class="table-actions">${editBtn}${delBtn}</div></td>
    `;

    row.querySelector('.btn-icon.edit')?.addEventListener('click', () => openEdit(customer.pKdNr));
    row.querySelector('.btn-icon.del')?.addEventListener('click', () => openDelete(customer.pKdNr, customer.name));
    return row;
}

function setTableState(bodyId, colspan, html) {
    const body = document.getElementById(bodyId);
    if (!body) return;
    body.innerHTML = `<tr class="table-state-row"><td class="table-state-cell" colspan="${colspan}">${html}</td></tr>`;
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── FORM + MODAL ──────────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('f-id').value = '';
    document.getElementById('customer-form').reset();
    document.getElementById('modal-form-title').textContent = 'New Customer';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Customer';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-name').focus();
}

function openEdit(id) {
    const customer = allCustomers.find(c => Number(c.pKdNr) === Number(id));
    if (!customer) return;

    clearFormErrors();
    document.getElementById('f-id').value = customer.pKdNr;
    document.getElementById('f-name').value = customer.name || '';
    document.getElementById('f-strasse').value = customer.strasse || '';
    document.getElementById('f-plz').value = customer.plz || '';
    document.getElementById('f-ort').value = customer.ort || '';
    document.getElementById('f-email').value = customer.email || '';

    document.getElementById('modal-form-title').textContent = 'Edit Customer';
    document.getElementById('modal-form-badge').textContent = `#${customer.pKdNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Customer';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-name').focus();
}

document.getElementById('customer-form')?.addEventListener('submit', async e => {
    e.preventDefault();
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
        if (data.errors) {
            Object.entries(data.errors).forEach(([key, messages]) => {
                const err = document.getElementById('err-' + key);
                const field = document.getElementById('f-' + key);

                if (err) err.textContent = messages[0];
                if (field) field.classList.add('invalid');
            });
        }

        toast(message || 'Save failed', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(id ? 'Customer updated.' : 'Customer created.', 'success');
    await loadCustomers();
});

function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('del-target-name').textContent = name || 'this customer';
    document.getElementById('del-target-id').textContent = id;
    document.getElementById('modal-del-overlay').classList.add('open');
}

document.getElementById('modal-del-confirm')?.addEventListener('click', async () => {
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
        await loadCustomers();
    } else {
        toast(message || data?.error || 'Archive failed.', 'error');
    }

    pendingDeleteId = null;
});

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));

document.querySelectorAll('.overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('modal-form-overlay');
        closeModal('modal-del-overlay');
    }
});

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(el => {
        el.textContent = '';
    });

    document.querySelectorAll('.form-input.invalid, .form-select.invalid').forEach(el => {
        el.classList.remove('invalid');
    });
}

// ── EVENT LISTENERS ──────────────────────────────────────────────────
document.querySelectorAll('.th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
        const key = th.dataset.sort;

        if (sortKey === key) sortDir *= -1;
        else {
            sortKey = key;
            sortDir = 1;
        }

        document.querySelectorAll('.th').forEach(header => {
            header.classList.toggle('sorted', header.dataset.sort === sortKey);
            const arrow = header.querySelector('.sort-arrow');
            if (arrow) {
                arrow.textContent = header.dataset.sort === sortKey ? (sortDir === 1 ? '↑' : '↓') : '↕';
            }
        });

        applyFilters();
    });
});

document.getElementById('search')?.addEventListener('input', applyFilters);
document.getElementById('btn-add')?.addEventListener('click', openAdd);

// ── INIT ──────────────────────────────────────────────────────────────
if (document.getElementById('customers-body')) {
    loadCustomers();
}
