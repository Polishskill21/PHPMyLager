// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const CAN_WRITE = document.querySelector('meta[name="can-write"]')?.content === 'true';
const CAN_DELETE = document.querySelector('meta[name="can-delete"]')?.content === 'true';
const ROW_H = 56;
const OVER = 8;

// ── STATE ─────────────────────────────────────────────────────────────
let allOrders = [];
let customers = [];
let products = [];
let customerMap = {};
let productMap = {};
let filtered = [];
let sortKey = 'pAufNr';
let sortDir = 1;
let pendingDeleteId = null;

// ── API HELPERS ───────────────────────────────────────────────────────
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
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
}

function toast(msg, type = 'info') {
    const area = document.getElementById('toast-area');
    if (!area) return;

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span> ${msg}`;
    area.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ── LOOKUPS ───────────────────────────────────────────────────────────
async function loadLookups() {
    const [customersRes, productsRes] = await Promise.all([
        api('GET', '/customers'),
        api('GET', '/products'),
    ]);

    if (customersRes.ok) {
        const raw = Array.isArray(customersRes.data) ? customersRes.data : [];
        customers = raw.map(entry => entry?.customer || {}).filter(c => c?.pKdNr != null);
        customerMap = Object.fromEntries(customers.map(c => [Number(c.pKdNr), c]));
    }

    if (productsRes.ok) {
        const raw = Array.isArray(productsRes.data?.products) ? productsRes.data.products : [];
        products = raw.filter(p => p?.pArtikelNr != null);
        productMap = Object.fromEntries(products.map(p => [Number(p.pArtikelNr), p]));
    }

    fillCustomerSelect();
}

function fillCustomerSelect(selectedId = '') {
    const sel = document.getElementById('f-fKdNr');
    if (!sel) return;

    const opts = ['<option value="">Select customer…</option>'];
    customers.forEach(c => {
        const label = `#${c.pKdNr} · ${esc(c.name || 'Unknown')}`;
        const selected = String(c.pKdNr) === String(selectedId) ? ' selected' : '';
        opts.push(`<option value="${c.pKdNr}"${selected}>${label}</option>`);
    });

    if (selectedId && !customerMap[Number(selectedId)]) {
        const unknownLabel = `#${selectedId} · [not listed]`;
        opts.push(`<option value="${selectedId}" selected>${unknownLabel}</option>`);
    }

    sel.innerHTML = opts.join('');
}

function productOptionsHtml(selectedId = '') {
    const opts = ['<option value="">Select product…</option>'];
    products.forEach(p => {
        const label = `#${p.pArtikelNr} · ${esc(p.bezeichnung || 'Unknown')}`;
        const selected = String(p.pArtikelNr) === String(selectedId) ? ' selected' : '';
        opts.push(`<option value="${p.pArtikelNr}"${selected}>${label}</option>`);
    });

    if (selectedId && !productMap[Number(selectedId)]) {
        const unknownLabel = `#${selectedId} · [not listed]`;
        opts.push(`<option value="${selectedId}" selected>${unknownLabel}</option>`);
    }

    return opts.join('');
}

// ── DATA ──────────────────────────────────────────────────────────────
async function loadOrders() {
    const wrap = document.getElementById('vscroll-inner');
    wrap.innerHTML = `<div class="loading-row"><div class="spinner"></div> Loading orders…</div>`;

    const { ok, data } = await api('GET', '/orders');
    if (!ok) {
        toast('Failed to load orders', 'error');
        wrap.innerHTML = `<div class="empty-state"><div class="icon">◳</div><p>Failed to load orders.</p></div>`;
        return;
    }

    const list = Array.isArray(data) ? data : [];
    allOrders = list.map(normalizeOrder);
    applyFilters();
}

function normalizeOrder(entry) {
    const info = entry?.order_info || {};
    const items = Array.isArray(entry?.items) ? entry.items : [];

    const customer = customerMap[Number(info.fKdNr)] || null;
    const customerName = customer?.name || `Customer #${info.fKdNr ?? '—'}`;

    const itemNames = items
        .map(item => item?.bezeichnung || `#${item?.fArtikelNr ?? ''}`)
        .filter(Boolean)
        .join(' ');

    return {
        pAufNr: Number(info.pAufNr),
        aufDat: info.aufDat || '',
        aufTermin: info.aufTermin || '',
        fKdNr: Number(info.fKdNr),
        customer_text: `#${info.fKdNr} · ${customerName}`,
        item_count: items.length,
        order_total: Number(entry?.order_total ?? 0),
        preis_total: Number(entry?.preis_total ?? 0),
        item_names: itemNames,
        items: items.map(item => ({
            pAufPosNr: item?.pAufPosNr,
            fArtikelNr: item?.fArtikelNr,
            bezeichnung: item?.bezeichnung || "",
            aufMenge: Number(item?.aufMenge ?? 0),
            kaufPreis: Number(item?.kaufPreis ?? 0),
            line_total: Number(item?.line_total ?? 0),
        })),
    };
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q = document.getElementById('search').value.trim().toLowerCase();

    filtered = allOrders.filter(order => {
        if (!q) return true;

        const haystack = [
            order.pAufNr,
            order.fKdNr,
            order.customer_text,
            order.aufDat,
            order.aufTermin,
            order.item_names,
        ].join(' ').toLowerCase();

        return haystack.includes(q);
    });

    filtered.sort((a, b) => {
        let av = a[sortKey];
        let bv = b[sortKey];

        if (['pAufNr', 'fKdNr', 'item_count', 'order_total', 'preis_total'].includes(sortKey)) {
            av = Number(av) || 0;
            bv = Number(bv) || 0;
            return (av - bv) * sortDir;
        }

        av = String(av || '').toLowerCase();
        bv = String(bv || '').toLowerCase();
        return av > bv ? sortDir : (av < bv ? -sortDir : 0);
    });

    renderVirtual();
}

// ── RENDER ────────────────────────────────────────────────────────────
function renderVirtual() {
    const vscroll = document.getElementById('vscroll');
    const inner = document.getElementById('vscroll-inner');

    if (filtered.length === 0) {
        inner.style.height = '200px';
        inner.innerHTML = `<div class="empty-state"><div class="icon">◳</div><p>No orders match your search.</p></div>`;
        return;
    }

    inner.style.height = filtered.length * ROW_H + 'px';
    inner.innerHTML = '';

    function paint() {
        const scrollTop = vscroll.scrollTop;
        const viewH = vscroll.clientHeight;
        const startIdx = Math.max(0, Math.floor(scrollTop / ROW_H) - OVER);
        const endIdx = Math.min(filtered.length - 1, Math.ceil((scrollTop + viewH) / ROW_H) + OVER);

        [...inner.querySelectorAll('.row')].forEach(el => {
            const i = Number(el.dataset.idx);
            if (i < startIdx || i > endIdx) el.remove();
        });

        const rendered = new Set([...inner.querySelectorAll('.row')].map(el => Number(el.dataset.idx)));
        for (let i = startIdx; i <= endIdx; i++) {
            if (rendered.has(i)) continue;
            inner.appendChild(buildRow(filtered[i], i));
        }
    }

    paint();
    vscroll.onscroll = paint;
}

function buildRow(order, idx) {
    const top = idx * ROW_H;

    const editBtn = CAN_WRITE
        ? `<button class="btn-icon edit" title="Edit" data-id="${order.pAufNr}">✎</button>`
        : '';

    const delBtn = CAN_DELETE
        ? `<button class="btn-icon del" title="Delete" data-id="${order.pAufNr}">⊗</button>`
        : '';

    const row = document.createElement('div');
    row.className = 'row row-clickable';
    row.dataset.idx = String(idx);
    row.style.top = top + 'px';
    row.innerHTML = `
        <div class="cell cell-id">#${order.pAufNr || '—'}</div>
        <div class="cell cell-customer" title="${esc(order.customer_text)}">${esc(order.customer_text)}</div>
        <div class="cell">${fmtDate(order.aufDat)}</div>
        <div class="cell">${fmtDate(order.aufTermin)}</div>
        <div class="cell cell-num">${order.item_count}</div>
        <div class="cell cell-num">${order.order_total}</div>
        <div class="cell cell-num">${fmtMoney(order.preis_total)}</div>
        <div class="cell"><div class="actions">${editBtn}${delBtn}</div></div>
    `;

    row.addEventListener('click', () => openInspect(order.pAufNr));

    if (CAN_WRITE) {
        row.querySelector('.btn-icon.edit')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openEdit(order.pAufNr);
        });
    }

    if (CAN_DELETE) {
        row.querySelector('.btn-icon.del')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openDelete(order.pAufNr);
        });
    }

    return row;
}

// ── FORM / MODALS ─────────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('f-id').value = '';
    document.getElementById('order-form').reset();
    fillCustomerSelect();

    const now = new Date();
    document.getElementById('f-aufDat').value = toLocalInput(now);

    const due = new Date(now);
    due.setDate(due.getDate() + 7);
    document.getElementById('f-aufTermin').value = toLocalInput(due);

    document.getElementById('item-rows').innerHTML = '';
    addItemRow();

    document.getElementById('modal-form-title').textContent = 'New Order';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Order';
    document.getElementById('modal-form-overlay').classList.add('open');
}

function openEdit(orderId) {
    const order = allOrders.find(o => Number(o.pAufNr) === Number(orderId));
    if (!order) return;

    clearFormErrors();
    document.getElementById('f-id').value = order.pAufNr;
    document.getElementById('f-aufDat').value = toLocalInput(order.aufDat);
    document.getElementById('f-aufTermin').value = toLocalInput(order.aufTermin);
    fillCustomerSelect(order.fKdNr);

    const rows = document.getElementById('item-rows');
    rows.innerHTML = '';

    if (!order.items.length) {
        addItemRow();
    } else {
        order.items.forEach(item => addItemRow(item));
    }

    document.getElementById('modal-form-title').textContent = 'Edit Order';
    document.getElementById('modal-form-badge').textContent = `#${order.pAufNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Order';
    document.getElementById('modal-form-overlay').classList.add('open');
}

function openInspect(orderId) {
    const order = allOrders.find(o => Number(o.pAufNr) === Number(orderId));
    if (!order) return;

    document.getElementById('view-order-badge').textContent = `#${order.pAufNr}`;
    document.getElementById('view-customer').textContent = order.customer_text || '—';
    document.getElementById('view-created').textContent = fmtDate(order.aufDat);
    document.getElementById('view-due').textContent = fmtDate(order.aufTermin);
    const customerOrders = allOrders.filter(o => Number(o.fKdNr) === Number(order.fKdNr));
    const totalProducts = customerOrders.reduce((sum, o) => sum + Number(o.order_total || 0), 0);
    const totalEur = customerOrders.reduce((sum, o) => sum + Number(o.preis_total || 0), 0);
    document.getElementById('view-total-products').textContent = String(totalProducts);
    document.getElementById('view-total-eur').textContent = `€${fmtMoney(totalEur)}`;

    const itemsEl = document.getElementById('view-items');
    if (!itemsEl) return;

    if (!order.items.length) {
        itemsEl.innerHTML = `
            <div class="inspect-empty">No items for this order.</div>
        `;
    } else {
        itemsEl.innerHTML = order.items.map(item => {
            const pos = item.pAufPosNr != null ? item.pAufPosNr : '—';
            const itemId = item.fArtikelNr != null ? item.fArtikelNr : '—';
            const itemName = item.bezeichnung || '[unknown]';
            const productLabel = `#${itemId} · ${itemName}`;

            return `
                <div class="inspect-item-row">
                    <div>${esc(pos)}</div>
                    <div title="${esc(productLabel)}">${esc(productLabel)}</div>
                    <div class="num">${Number(item.aufMenge || 0)}</div>
                    <div class="num">${fmtMoney(item.kaufPreis)}</div>
                    <div class="num">${fmtMoney(item.line_total)}</div>
                </div>
            `;
        }).join('');
    }

    document.getElementById('modal-view-overlay').classList.add('open');
}

function openDelete(orderId) {
    pendingDeleteId = Number(orderId);
    document.getElementById('del-target-id').textContent = `#${orderId}`;
    document.getElementById('modal-del-overlay').classList.add('open');
}

function addItemRow(item = {}) {
    const container = document.getElementById('item-rows');
    const row = document.createElement('div');
    row.className = 'item-row';

    const posNr = item?.pAufPosNr != null ? String(item.pAufPosNr) : '';
    const artikelNr = item?.fArtikelNr != null ? String(item.fArtikelNr) : '';
    const menge = item?.aufMenge != null ? String(item.aufMenge) : '1';

    row.innerHTML = `
        <input type="hidden" class="i-posnr" value="${posNr}">
        <select class="form-select i-artikel">${productOptionsHtml(artikelNr)}</select>
        <input type="number" class="form-input i-menge" min="1" step="1" value="${menge}">
        <button type="button" class="item-remove" title="Remove item">−</button>
    `;

    row.querySelector('.item-remove')?.addEventListener('click', () => {
        row.remove();
        ensureAtLeastOneItemRow();
    });

    container.appendChild(row);
}

function ensureAtLeastOneItemRow() {
    const rows = document.querySelectorAll('#item-rows .item-row');
    if (!rows.length) addItemRow();
}

async function submitOrderForm(e) {
    e.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = buildPayload();
    if (!payload) return;

    const btn = document.getElementById('modal-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data } = id
        ? await api('PUT', `/orders/${id}`, payload)
        : await api('POST', '/orders', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Order' : 'Save Order';

    if (!ok) {
        applyValidationErrors(data);
        toast(data.message || 'Save failed', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(id ? 'Order updated.' : 'Order created.', 'success');
    await loadOrders();
}

function buildPayload() {
    const aufDatRaw = document.getElementById('f-aufDat').value;
    const aufTerminRaw = document.getElementById('f-aufTermin').value;
    const customerIdRaw = document.getElementById('f-fKdNr').value;

    const payload = {
        aufDat: fromLocalInput(aufDatRaw),
        fKdNr: Number(customerIdRaw),
        aufTermin: fromLocalInput(aufTerminRaw),
        items: [],
    };

    const itemRows = [...document.querySelectorAll('#item-rows .item-row')];

    if (!itemRows.length) {
        document.getElementById('err-items').textContent = 'At least one item is required.';
        return null;
    }

    for (const row of itemRows) {
        const posNr = row.querySelector('.i-posnr')?.value?.trim();
        const artikelNr = row.querySelector('.i-artikel')?.value?.trim();
        const menge = row.querySelector('.i-menge')?.value?.trim();

        if (!artikelNr || !menge) {
            document.getElementById('err-items').textContent = 'Each item needs product and quantity.';
            return null;
        }

        const item = {
            fArtikelNr: Number(artikelNr),
            aufMenge: Number(menge),
        };

        if (posNr) item.pAufPosNr = Number(posNr);
        payload.items.push(item);
    }

    return payload;
}

function applyValidationErrors(data) {
    const errors = data?.errors || {};

    Object.entries(errors).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : String(messages);

        if (key.startsWith('items')) {
            const el = document.getElementById('err-items');
            if (el && !el.textContent) el.textContent = msg;
            return;
        }

        const err = document.getElementById('err-' + key);
        if (err) err.textContent = msg;
    });
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('modal-del-confirm');
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    const { ok, status, data } = await api('DELETE', `/orders/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Delete';
    closeModal('modal-del-overlay');

    if (ok) {
        toast(status === 204 ? 'Order deleted.' : (data.message || 'Order deleted.'), 'success');
        await loadOrders();
    } else {
        toast(data.message || data.error || 'Delete failed.', 'error');
    }

    pendingDeleteId = null;
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(el => {
        el.textContent = '';
    });
}

// ── UTILS ─────────────────────────────────────────────────────────────
function fmtDate(value) {
    if (!value) return '—';
    return String(value).replace('T', ' ').slice(0, 16);
}

function fmtMoney(value) {
    return Number(value || 0).toFixed(2);
}

function toLocalInput(value) {
    const d = value instanceof Date ? value : new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';

    const pad = n => String(n).padStart(2, '0');
    const y = d.getFullYear();
    const m = pad(d.getMonth() + 1);
    const day = pad(d.getDate());
    const h = pad(d.getHours());
    const min = pad(d.getMinutes());

    return `${y}-${m}-${day}T${h}:${min}`;
}

function fromLocalInput(value) {
    if (!value) return '';
    return value.replace('T', ' ') + ':00';
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ── EVENTS ────────────────────────────────────────────────────────────
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
            if (arrow) arrow.textContent = header.dataset.sort === sortKey ? (sortDir === 1 ? '↑' : '↓') : '↕';
        });

        applyFilters();
    });
});

document.getElementById('search')?.addEventListener('input', applyFilters);
document.getElementById('btn-add')?.addEventListener('click', openAdd);
document.getElementById('btn-add-item')?.addEventListener('click', () => addItemRow());
document.getElementById('order-form')?.addEventListener('submit', submitOrderForm);
document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
document.getElementById('modal-view-close')?.addEventListener('click', () => closeModal('modal-view-overlay'));
document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));
document.getElementById('modal-del-confirm')?.addEventListener('click', confirmDelete);

document.querySelectorAll('.overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('modal-form-overlay');
        closeModal('modal-view-overlay');
        closeModal('modal-del-overlay');
    }
});

// ── INIT ──────────────────────────────────────────────────────────────
if (document.getElementById('vscroll-inner')) {
    (async () => {
        await loadLookups();
        await loadOrders();
    })();
}
