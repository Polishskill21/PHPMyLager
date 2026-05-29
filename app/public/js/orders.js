// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const CAN_WRITE = document.querySelector('meta[name="can-write"]')?.content === 'true';
const CAN_DELETE = document.querySelector('meta[name="can-delete"]')?.content === 'true';
const ICONS = {
    edit: '/icons/lucide/pencil.png',
    del: '/icons/lucide/trash-2.png',
};

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
        customers = raw
            .map((entry) => entry?.customer || entry)
            .filter((c) => c?.pKdNr != null);
        customerMap = Object.fromEntries(customers.map((c) => [Number(c.pKdNr), c]));
    }

    if (productsRes.ok) {
        const raw = Array.isArray(productsRes.data)
            ? productsRes.data
            : (Array.isArray(productsRes.data?.products) ? productsRes.data.products : []);
        products = raw.filter((p) => p?.pArtikelNr != null);
        productMap = Object.fromEntries(products.map((p) => [Number(p.pArtikelNr), p]));
    }

    fillCustomerSelect();
}

function fillCustomerSelect(selectedId = '') {
    const sel = document.getElementById('f-fKdNr');
    if (!sel) return;

    const opts = ['<option value="">Select customer…</option>'];
    customers.forEach((c) => {
        const label = esc(c.name || 'Unknown');
        const selected = String(c.pKdNr) === String(selectedId) ? ' selected' : '';
        opts.push(`<option value="${c.pKdNr}"${selected}>${label}</option>`);
    });

    if (selectedId && !customerMap[Number(selectedId)]) {
        opts.push(`<option value="${selectedId}" selected>[not listed]</option>`);
    }

    sel.innerHTML = opts.join('');
}

function productOptionsHtml(selectedId = '') {
    const opts = ['<option value="">Select product…</option>'];
    products.forEach((p) => {
        const label = esc(p.bezeichnung || 'Unknown');
        const selected = String(p.pArtikelNr) === String(selectedId) ? ' selected' : '';
        opts.push(`<option value="${p.pArtikelNr}"${selected}>${label}</option>`);
    });

    if (selectedId && !productMap[Number(selectedId)]) {
        opts.push(`<option value="${selectedId}" selected>[not listed]</option>`);
    }

    return opts.join('');
}

// ── DATA ──────────────────────────────────────────────────────────────
async function loadOrders() {
    setTableState('<div class="loading-row"><div class="spinner"></div> Loading orders…</div>');

    const { ok, data } = await api('GET', '/orders');
    if (!ok) {
        toast('Failed to load orders', 'error');
        setTableState('<div class="empty-state">Failed to load orders.</div>');
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
    const customerName = customer?.name || 'Unknown customer';

    const itemNames = items
        .map((item) => item?.bezeichnung || `#${item?.fArtikelNr ?? ''}`)
        .filter(Boolean)
        .join(' ');

    return {
        pAufNr: Number(info.pAufNr),
        aufDat: info.aufDat || '',
        aufTermin: info.aufTermin || '',
        fKdNr: Number(info.fKdNr),
        customer_text: customerName,
        item_count: items.length,
        order_total: Number(entry?.order_total ?? 0),
        preis_total: Number(entry?.preis_total ?? 0),
        item_names: itemNames,
        items: items.map((item) => ({
            pAufPosNr: item?.pAufPosNr,
            fArtikelNr: item?.fArtikelNr,
            bezeichnung: item?.bezeichnung || '',
            aufMenge: Number(item?.aufMenge ?? 0),
            kaufPreis: Number(item?.kaufPreis ?? 0),
            line_total: Number(item?.line_total ?? 0),
        })),
    };
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q = document.getElementById('search')?.value?.trim().toLowerCase() || '';

    filtered = allOrders.filter((order) => {
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

    const statTotal = document.getElementById('stat-total');
    if (statTotal) statTotal.textContent = String(filtered.length);

    const statTotalEur = document.getElementById('stat-total-eur');
    if (statTotalEur) {
        const totalEur = filtered.reduce((sum, order) => sum + Number(order.preis_total || 0), 0);
        statTotalEur.textContent = fmtMoney(totalEur);
    }

    renderTableRows();
}

// ── RENDER ────────────────────────────────────────────────────────────
function setTableState(contentHtml) {
    const body = document.getElementById('orders-body');
    if (!body) return;

    body.innerHTML = `
        <tr>
            <td colspan="7">${contentHtml}</td>
        </tr>
    `;
}

function renderTableRows() {
    const body = document.getElementById('orders-body');
    if (!body) return;

    if (!filtered.length) {
        setTableState('<div class="empty-state">No orders match your search.</div>');
        return;
    }

    body.innerHTML = '';
    const frag = document.createDocumentFragment();
    filtered.forEach((order) => frag.appendChild(buildRow(order)));
    body.appendChild(frag);
}

function buildRow(order) {
    const editBtn = CAN_WRITE
        ? `<button class="btn-icon edit" title="Edit" data-id="${order.pAufNr}"><img class="action-icon" src="${ICONS.edit}" alt="Edit"></button>`
        : '';

    const delBtn = CAN_DELETE
        ? `<button class="btn-icon del" title="Delete" data-id="${order.pAufNr}"><img class="action-icon" src="${ICONS.del}" alt="Delete"></button>`
        : '';

    const actionsHtml = `<div class="orders-actions">${editBtn}${delBtn}</div>`;

    const row = document.createElement('tr');
    row.className = 'row-clickable';
    row.innerHTML = `
        <td class="cell-id">#${order.pAufNr || '—'}</td>
        <td class="cell-customer" title="${esc(order.customer_text)}">${esc(order.customer_text)}</td>
        <td class="cell-date">${fmtDate(order.aufDat)}</td>
        <td class="cell-date">${fmtDate(order.aufTermin)}</td>
        <td class="cell-items td-items">${order.item_count}</td>
        <td class="cell-total td-total">€${fmtMoney(order.preis_total)}</td>
        <td class="td-actions">${actionsHtml}</td>
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
    setDateFieldValue('f-aufDat', toDateInput(now));

    const due = new Date(now);
    due.setDate(due.getDate() + 7);
    setDateFieldValue('f-aufTermin', toDateInput(due));

    document.getElementById('item-rows').innerHTML = '';
    addItemRow();

    document.getElementById('modal-form-title').textContent = 'New Order';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Order';
    document.getElementById('modal-form-overlay').classList.add('open');
}

function openEdit(orderId) {
    const order = allOrders.find((o) => Number(o.pAufNr) === Number(orderId));
    if (!order) return;

    clearFormErrors();
    document.getElementById('f-id').value = order.pAufNr;
    setDateFieldValue('f-aufDat', toDateInput(order.aufDat));
    setDateFieldValue('f-aufTermin', toDateInput(order.aufTermin));
    fillCustomerSelect(order.fKdNr);

    const rows = document.getElementById('item-rows');
    rows.innerHTML = '';

    if (!order.items.length) {
        addItemRow();
    } else {
        order.items.forEach((item) => addItemRow(item));
    }

    document.getElementById('modal-form-title').textContent = 'Edit Order';
    document.getElementById('modal-form-badge').textContent = `#${order.pAufNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Order';
    document.getElementById('modal-form-overlay').classList.add('open');
}

function openInspect(orderId) {
    const order = allOrders.find((o) => Number(o.pAufNr) === Number(orderId));
    if (!order) return;

    document.getElementById('view-order-badge').textContent = `#${order.pAufNr}`;
    document.getElementById('view-customer').textContent = order.customer_text || '—';
    document.getElementById('view-created').textContent = fmtDate(order.aufDat);
    document.getElementById('view-due').textContent = fmtDate(order.aufTermin);

    const totalQty = Number(order.order_total || 0);
    const totalEur = Number(order.preis_total || 0);
    const totalQtyText = String(totalQty);
    const totalEurText = `€${fmtMoney(totalEur)}`;

    const itemsEl = document.getElementById('view-items');
    if (!itemsEl) return;

    const totalsRow = `
        <div class="inspect-total-row" aria-label="Order totals">
            <div></div>
            <div></div>
            <div></div>
            <div class="inspect-total-cell">
                <span>Total Qty</span>
                <strong id="view-total-qty">${esc(totalQtyText)}</strong>
            </div>
            <div></div>
            <div class="inspect-total-cell">
                <span>Total EUR</span>
                <strong id="view-total-eur">${esc(totalEurText)}</strong>
            </div>
        </div>
    `;

    if (!order.items.length) {
        itemsEl.innerHTML = '<div class="inspect-empty">No items for this order.</div>' + totalsRow;
    } else {
        const itemRows = order.items.map((item) => {
            const pos = item.pAufPosNr != null ? item.pAufPosNr : '—';
            const itemId = item.fArtikelNr != null ? item.fArtikelNr : '—';
            const itemName = item.bezeichnung || '[unknown]';

            return `
                <div class="inspect-item-row">
                    <div>${esc(pos)}</div>
                    <div>${esc(itemId)}</div>
                    <div title="${esc(itemName)}">${esc(itemName)}</div>
                    <div class="num">${Number(item.aufMenge || 0)}</div>
                    <div class="num">€${fmtMoney(item.kaufPreis)}</div>
                    <div class="num">€${fmtMoney(item.line_total)}</div>
                </div>
            `;
        }).join('');

        itemsEl.innerHTML = itemRows + totalsRow;
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
    row.className = 'orders-item-row';

    const posNr = item?.pAufPosNr != null ? String(item.pAufPosNr) : '';
    const artikelNr = item?.fArtikelNr != null ? String(item.fArtikelNr) : '';
    const menge = item?.aufMenge != null ? String(item.aufMenge) : '1';

    row.innerHTML = `
        <input type="hidden" class="i-posnr" value="${posNr}">
        <div class="orders-item-field">
            <span class="form-label orders-item-label">Product</span>
            <div class="select-wrap">
                <select class="form-select i-artikel" aria-label="Product">${productOptionsHtml(artikelNr)}</select>
                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
            </div>
        </div>
        <div class="orders-item-field">
            <span class="form-label orders-item-label">Qty</span>
            <div class="quantity-wrap number-input-wrap">
                <input type="number" class="form-input i-menge" min="1" step="1" value="${menge}" aria-label="Quantity">
                <div class="number-stepper-controls">
                    <button type="button" class="number-stepper-button" title="Increase quantity" aria-label="Increase quantity" data-number-step="up">
                        <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="number-stepper-button" title="Decrease quantity" aria-label="Decrease quantity" data-number-step="down">
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
        <button type="button" class="orders-item-remove form-row-remove" title="Remove item" aria-label="Remove item">
            <span class="modal-control-icon icon-trash" aria-hidden="true"></span>
        </button>
    `;

    row.querySelector('.orders-item-remove')?.addEventListener('click', () => {
        row.remove();
        ensureAtLeastOneItemRow();
    });

    container.appendChild(row);
}

function ensureAtLeastOneItemRow() {
    const rows = document.querySelectorAll('#item-rows .orders-item-row');
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

    const { ok, data, message } = id
        ? await api('PUT', `/orders/${id}`, payload)
        : await api('POST', '/orders', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Order' : 'Save Order';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(message || (id ? 'Order updated.' : 'Order created.'), 'success');
    await loadOrders();
}

function buildPayload() {
    const aufDatRaw = document.getElementById('f-aufDat').value;
    const aufTerminRaw = document.getElementById('f-aufTermin').value;
    const customerIdRaw = document.getElementById('f-fKdNr').value;

    const payload = {
        aufDat: fromDateInput(aufDatRaw),
        fKdNr: Number(customerIdRaw),
        aufTermin: fromDateInput(aufTerminRaw),
        items: [],
    };

    const itemRows = [...document.querySelectorAll('#item-rows .orders-item-row')];

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

    const { ok, data, message } = await api('DELETE', `/orders/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Delete';
    closeModal('modal-del-overlay');

    if (ok) {
        toast(message || 'Order deleted.', 'success');
        await loadOrders();
    } else {
        toast(message || data?.error || 'Delete failed.', 'error');
    }

    pendingDeleteId = null;
}

function closeModal(id) {
    window.AppDatePicker?.closeAll();
    document.getElementById(id)?.classList.remove('open');
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach((el) => {
        el.textContent = '';
    });
}

// ── UTILS ─────────────────────────────────────────────────────────────
function fmtDate(value) {
    if (!value) return '—';

    const raw = String(value);
    const datePart = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    return datePart ? datePart[1] : raw.slice(0, 10);
}

function fmtMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function toDateInput(value) {
    if (!value) return '';

    if (value instanceof Date) return formatDateValue(value);

    const raw = String(value);
    const datePart = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    if (datePart) return datePart[1];

    const parsed = new Date(raw.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? '' : formatDateValue(parsed);
}

function fromDateInput(value) {
    return value || '';
}

function setDateFieldValue(id, value) {
    const input = document.getElementById(id);
    if (!input) return;

    input.value = value;
    input._datePickerMonth = null;
}

function formatDateValue(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ── EVENTS ────────────────────────────────────────────────────────────
function syncSortHeaders() {
    document.querySelectorAll('.th[data-sort]').forEach((header) => {
        const active = header.dataset.sort === sortKey;
        const arrow = header.querySelector('.sort-arrow');

        header.classList.toggle('sorted', active);
        header.setAttribute('aria-sort', active ? (sortDir === 1 ? 'ascending' : 'descending') : 'none');

        if (!arrow) return;
        arrow.classList.remove('sort-none', 'sort-asc', 'sort-desc');
        arrow.classList.add(active ? (sortDir === 1 ? 'sort-asc' : 'sort-desc') : 'sort-none');
    });
}

document.querySelectorAll('.th[data-sort]').forEach((th) => {
    th.addEventListener('click', () => {
        const key = th.dataset.sort;

        if (sortKey === key) sortDir *= -1;
        else {
            sortKey = key;
            sortDir = 1;
        }

        syncSortHeaders();
        applyFilters();
    });
});

syncSortHeaders();

document.getElementById('search')?.addEventListener('input', applyFilters);
document.getElementById('btn-add')?.addEventListener('click', openAdd);
document.getElementById('btn-add-item')?.addEventListener('click', () => addItemRow());
document.getElementById('order-form')?.addEventListener('submit', submitOrderForm);
document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
document.getElementById('modal-view-close')?.addEventListener('click', () => closeModal('modal-view-overlay'));
document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));
document.getElementById('modal-del-confirm')?.addEventListener('click', confirmDelete);

document.querySelectorAll('.overlay').forEach((overlay) => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (window.AppDatePicker?.hasOpen()) {
            window.AppDatePicker.closeAll();
            return;
        }

        closeModal('modal-form-overlay');
        closeModal('modal-view-overlay');
        closeModal('modal-del-overlay');
    }
});

// ── INIT ──────────────────────────────────────────────────────────────
if (document.getElementById('orders-body')) {
    (async () => {
        await loadLookups();
        await loadOrders();
    })();
}
