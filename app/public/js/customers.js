// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const CAN_WRITE  = document.querySelector('meta[name="can-write"]')?.content === 'true';
const CAN_DELETE = document.querySelector('meta[name="can-delete"]')?.content === 'true';
const ROW_H      = 56;
const OVER       = 8;

// ── STATE ─────────────────────────────────────────────────────────────
let allCustomers    = [];
let filtered        = [];
let sortKey         = 'pKdNr';
let sortDir         = 1;
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
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
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
    const wrap = document.getElementById('vscroll-inner');
    wrap.innerHTML = `<div class="loading-row"><div class="spinner"></div> Loading customers…</div>`;

    const { ok, data } = await api('GET', '/customers');
    if (!ok) {
        toast('Failed to load customers', 'error');
        wrap.innerHTML = `<div class="empty-state"><div class="icon">🧙</div><p>Failed to load customers.</p></div>`;
        return;
    }

    const list = Array.isArray(data) ? data : [];
    allCustomers = list.map(normalizeCustomer);
    applyFilters();
}

function normalizeCustomer(entry) {
    const customer = entry?.customer || {};
    const orders = Array.isArray(entry?.orders) ? entry.orders : [];
    const latest = findLatestOrder(orders);

    return {
        pKdNr: customer.pKdNr,
        name: customer.name || '',
        strasse: customer.strasse || '',
        plz: customer.plz,
        ort: customer.ort || '',
        email: customer.email || '',
        orders,
        orderCount: orders.length,
        latestOrderId: latest?.pAufNr ?? null,
        latestOrderDate: latest?.aufDat ?? null,
    };
}

function findLatestOrder(orders) {
    if (!orders.length) return null;

    return orders.reduce((latest, current) => {
        const latestTs = Date.parse(latest?.aufDat || '') || 0;
        const currentTs = Date.parse(current?.aufDat || '') || 0;

        if (currentTs > latestTs) return current;
        if (currentTs === latestTs && (Number(current?.pAufNr) || 0) > (Number(latest?.pAufNr) || 0)) {
            return current;
        }

        return latest;
    }, orders[0]);
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q = document.getElementById('search').value.trim().toLowerCase();

    filtered = allCustomers.filter(c => {
        if (!q) return true;

        const orderIds = c.orders.map(o => String(o.pAufNr || '')).join(' ');
        const haystack = [
            c.pKdNr,
            c.name,
            c.email,
            c.ort,
            c.plz,
            c.strasse,
            orderIds,
        ].join(' ').toLowerCase();

        return haystack.includes(q);
    });

    filtered.sort((a, b) => {
        let av = a[sortKey];
        let bv = b[sortKey];

        if (['pKdNr', 'plz', 'orderCount', 'latestOrderId'].includes(sortKey)) {
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

    document.getElementById('stat-total').textContent = allCustomers.length;
    document.getElementById('stat-with-orders').textContent = allCustomers.filter(c => c.orderCount > 0).length;

    renderVirtual();
}

// ── VIRTUAL SCROLL ────────────────────────────────────────────────────
function renderVirtual() {
    const vscroll = document.getElementById('vscroll');
    const inner = document.getElementById('vscroll-inner');

    if (filtered.length === 0) {
        inner.style.height = '200px';
        inner.innerHTML = `<div class="empty-state"><div class="icon">🧙</div><p>No customers match your search.</p></div>`;
        return;
    }

    inner.style.height = (filtered.length * ROW_H) + 'px';
    inner.innerHTML = '';

    function paint() {
        const scrollTop = vscroll.scrollTop;
        const viewH = vscroll.clientHeight;
        const startIdx = Math.max(0, Math.floor(scrollTop / ROW_H) - OVER);
        const endIdx = Math.min(filtered.length - 1, Math.ceil((scrollTop + viewH) / ROW_H) + OVER);

        [...inner.querySelectorAll('.row')].forEach(el => {
            const i = +el.dataset.idx;
            if (i < startIdx || i > endIdx) el.remove();
        });

        const rendered = new Set([...inner.querySelectorAll('.row')].map(el => +el.dataset.idx));
        for (let i = startIdx; i <= endIdx; i++) {
            if (rendered.has(i)) continue;
            inner.appendChild(buildRow(filtered[i], i));
        }
    }

    paint();
    vscroll.onscroll = paint;
}

function buildRow(customer, idx) {
    const top = idx * ROW_H;
    const latestOrder = customer.latestOrderId
        ? `#${customer.latestOrderId}${customer.latestOrderDate ? ` • ${fmtDate(customer.latestOrderDate)}` : ''}`
        : '—';

    const editBtn = CAN_WRITE
        ? `<button class="btn-icon edit" title="Edit" data-id="${customer.pKdNr}">✎</button>`
        : '';

    const delBtn = CAN_DELETE
        ? `<button class="btn-icon del" title="Archive" data-id="${customer.pKdNr}">⊗</button>`
        : '';

    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.idx = idx;
    row.style.top = top + 'px';
    row.innerHTML = `
        <div class="cell cell-id">#${customer.pKdNr ?? '—'}</div>
        <div class="cell cell-name" title="${esc(customer.name)}">${esc(customer.name || '—')}</div>
        <div class="cell cell-muted" title="${esc(customer.email)}">${esc(customer.email || '—')}</div>
        <div class="cell" title="${esc(customer.ort)}">${esc(customer.ort || '—')}</div>
        <div class="cell cell-num">${customer.plz ?? '—'}</div>
        <div class="cell cell-num">${customer.orderCount}</div>
        <div class="cell" title="${esc(latestOrder)}">${esc(latestOrder)}</div>
        <div class="cell"><div class="actions">${editBtn}${delBtn}</div></div>
    `;

    if (CAN_WRITE) {
        row.querySelector('.btn-icon.edit')?.addEventListener('click', () => openEdit(customer.pKdNr));
    }

    if (CAN_DELETE) {
        row.querySelector('.btn-icon.del')?.addEventListener('click', () => openDelete(customer.pKdNr, customer.name));
    }

    return row;
}

function fmtDate(v) {
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return String(v || '').slice(0, 10) || '—';
    return d.toISOString().slice(0, 10);
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
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

    const { ok, data } = id
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

        toast(data.message || 'Save failed', 'error');
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

    const { ok, status, data } = await api('DELETE', `/customers/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Archive';
    closeModal('modal-del-overlay');

    if (ok) {
        toast(status === 204 ? 'Customer archived.' : (data.message || 'Customer archived.'), 'success');
        await loadCustomers();
    } else {
        toast(data.message || data.error || 'Archive failed.', 'error');
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
if (document.getElementById('vscroll-inner')) {
    loadCustomers();
}
