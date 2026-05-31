const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

let suppliers = [];
let products = [];
let supplierMap = {};
let productMap = {};
let pendingDeleteId = null;
let lookupPromise = null;

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

function ensureLookups() {
    if (!lookupPromise) lookupPromise = loadLookups();
    return lookupPromise;
}

async function loadLookups() {
    const [suppliersRes, productsRes] = await Promise.all([
        api('GET', '/suppliers'),
        api('GET', '/products'),
    ]);

    if (suppliersRes.ok) {
        suppliers = Array.isArray(suppliersRes.data) ? suppliersRes.data : [];
        supplierMap = Object.fromEntries(suppliers.map((supplier) => [Number(supplier.pLiefNr), supplier]));
    } else {
        toast('Failed to load suppliers.', 'error');
    }

    if (productsRes.ok) {
        products = Array.isArray(productsRes.data)
            ? productsRes.data
            : (Array.isArray(productsRes.data?.products) ? productsRes.data.products : []);
        productMap = Object.fromEntries(products.map((product) => [Number(product.pArtikelNr), product]));
    } else {
        toast('Failed to load products.', 'error');
    }

    fillSupplierSelect();
}

function fillSupplierSelect(selectedId = '') {
    const select = document.getElementById('f-fLiefNr');
    if (!select) return;

    const options = ['<option value="">Select supplier…</option>'];
    suppliers.forEach((supplier) => {
        const selected = String(supplier.pLiefNr) === String(selectedId) ? ' selected' : '';
        options.push(`<option value="${supplier.pLiefNr}"${selected}>${esc(supplier.name || 'Unknown')}</option>`);
    });

    if (selectedId && !supplierMap[Number(selectedId)]) {
        options.push(`<option value="${selectedId}" selected>[not listed]</option>`);
    }

    select.innerHTML = options.join('');
}

function productOptionsHtml(selectedId = '') {
    const options = ['<option value="">Select product…</option>'];
    products.forEach((product) => {
        const selected = String(product.pArtikelNr) === String(selectedId) ? ' selected' : '';
        options.push(`<option value="${product.pArtikelNr}"${selected}>${esc(product.bezeichnung || 'Unknown')}</option>`);
    });

    if (selectedId && !productMap[Number(selectedId)]) {
        options.push(`<option value="${selectedId}" selected>[not listed]</option>`);
    }

    return options.join('');
}

async function openAdd() {
    await ensureLookups();
    clearFormErrors();

    document.getElementById('purchase-order-form')?.reset();
    document.getElementById('f-id').value = '';
    fillSupplierSelect();
    document.getElementById('f-bestDat').value = toDateInput(new Date());

    const expected = new Date();
    expected.setDate(expected.getDate() + 7);
    document.getElementById('f-erwLieferDat').value = toDateInput(expected);

    const rows = document.getElementById('purchase-order-item-rows');
    rows.innerHTML = '';
    addItemRow();

    document.getElementById('purchase-order-form-title').textContent = 'New Purchase Order';
    document.getElementById('purchase-order-form-badge').textContent = 'CREATE';
    document.getElementById('purchase-order-form-submit').textContent = 'Save Purchase Order';
    document.getElementById('modal-purchase-order-form-overlay').classList.add('open');
    document.getElementById('f-fLiefNr')?.focus();
}

async function openEdit(id) {
    await ensureLookups();
    clearFormErrors();

    const { ok, data, message } = await api('GET', `/purchase-orders/${id}`);
    if (!ok) {
        toast(message || 'Failed to load purchase order.', 'error');
        return;
    }

    const order = normalizePurchaseOrder(data);
    document.getElementById('f-id').value = order.pBestNr;
    fillSupplierSelect(order.fLiefNr || '');
    document.getElementById('f-bestDat').value = toDateInput(order.bestDat);
    document.getElementById('f-erwLieferDat').value = toDateInput(order.erwLieferDat);

    const rows = document.getElementById('purchase-order-item-rows');
    rows.innerHTML = '';
    if (order.items.length) {
        order.items.forEach((item) => addItemRow(item));
    } else {
        addItemRow();
    }

    document.getElementById('purchase-order-form-title').textContent = 'Edit Purchase Order';
    document.getElementById('purchase-order-form-badge').textContent = `#${order.pBestNr}`;
    document.getElementById('purchase-order-form-submit').textContent = 'Update Purchase Order';
    document.getElementById('modal-purchase-order-form-overlay').classList.add('open');
    document.getElementById('f-fLiefNr')?.focus();
}

function normalizePurchaseOrder(data) {
    const info = data?.order_info || {};
    return {
        pBestNr: info.pBestNr,
        fLiefNr: info.fLiefNr,
        bestDat: info.bestDat || '',
        erwLieferDat: info.erwLieferDat || '',
        status: info.status || 'offen',
        items: Array.isArray(data?.items) ? data.items : [],
    };
}

function addItemRow(item = {}) {
    const container = document.getElementById('purchase-order-item-rows');
    const row = document.createElement('div');
    row.className = 'purchase-order-item-row';

    const posNr = item.pBestPosNr != null ? String(item.pBestPosNr) : '';
    const productId = item.fArtikelNr != null ? String(item.fArtikelNr) : '';
    const qty = item.bestMenge != null ? String(item.bestMenge) : '1';
    const price = item.ekPreis != null ? moneyInput(item.ekPreis) : '';

    row.dataset.originalProductId = productId;
    row.innerHTML = `
        <input type="hidden" class="i-posnr" value="${esc(posNr)}">
        <div class="purchase-order-item-field purchase-order-product-field">
            <span class="form-label purchase-order-item-label">Product</span>
            <span class="select-wrap">
                <select class="form-select i-product" aria-label="Product">${productOptionsHtml(productId)}</select>
                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
            </span>
        </div>
        <div class="purchase-order-item-field">
            <span class="form-label purchase-order-item-label">Qty</span>
            <span class="number-input-wrap">
                <input class="form-input i-qty" type="number" min="1" step="1" value="${esc(qty)}" aria-label="Quantity">
                <span class="number-stepper-controls">
                    <button type="button" class="number-stepper-button" title="Increase quantity" aria-label="Increase quantity" data-number-step="up">
                        <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="number-stepper-button" title="Decrease quantity" aria-label="Decrease quantity" data-number-step="down">
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </button>
                </span>
            </span>
        </div>
        <div class="purchase-order-item-field">
            <span class="form-label purchase-order-item-label">Value</span>
            <span class="number-input-wrap">
                <input class="form-input i-price" type="number" min="0" step="0.01" value="${esc(price)}" aria-label="Buy price">
                <span class="number-stepper-controls">
                    <button type="button" class="number-stepper-button" title="Increase value" aria-label="Increase value" data-number-step="up">
                        <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="number-stepper-button" title="Decrease value" aria-label="Decrease value" data-number-step="down">
                        <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                    </button>
                </span>
            </span>
        </div>
        <button type="button" class="purchase-order-item-remove form-row-remove" title="Remove item" aria-label="Remove item">
            <span class="modal-control-icon icon-trash" aria-hidden="true"></span>
        </button>
    `;

    const productSelect = row.querySelector('.i-product');
    const priceInput = row.querySelector('.i-price');

    productSelect?.addEventListener('change', () => {
        const product = productMap[Number(productSelect.value)];
        if (product && !priceInput.value) priceInput.value = moneyInput(product.ekPreis);
    });

    row.querySelector('.purchase-order-item-remove')?.addEventListener('click', () => {
        row.remove();
        ensureAtLeastOneItemRow();
    });

    container.appendChild(row);
}

function ensureAtLeastOneItemRow() {
    if (!document.querySelectorAll('#purchase-order-item-rows .purchase-order-item-row').length) {
        addItemRow();
    }
}

function buildPayload() {
    const supplierId = document.getElementById('f-fLiefNr').value;
    const bestDat = document.getElementById('f-bestDat').value;
    const expectedDate = document.getElementById('f-erwLieferDat').value;
    const rows = [...document.querySelectorAll('#purchase-order-item-rows .purchase-order-item-row')];

    if (!supplierId) {
        setFieldError('fLiefNr', 'Select a supplier.');
        return null;
    }

    if (!bestDat) {
        setFieldError('bestDat', 'Select an order date.');
        return null;
    }

    if (!rows.length) {
        document.getElementById('err-items').textContent = 'At least one item is required.';
        return null;
    }

    const payload = {
        fLiefNr: Number(supplierId),
        bestDat,
        erwLieferDat: expectedDate || null,
        items: [],
    };

    for (const row of rows) {
        const posNr = row.querySelector('.i-posnr')?.value?.trim() || '';
        const originalProductId = row.dataset.originalProductId || '';
        const productId = row.querySelector('.i-product')?.value?.trim() || '';
        const qty = row.querySelector('.i-qty')?.value?.trim() || '';
        const price = row.querySelector('.i-price')?.value?.trim() || '';

        if (!productId || !qty) {
            document.getElementById('err-items').textContent = 'Each item needs product and quantity.';
            return null;
        }

        const item = {
            fArtikelNr: Number(productId),
            bestMenge: Number(qty),
            ekPreis: price === '' ? null : Number(price),
        };

        if (posNr && String(productId) === String(originalProductId)) {
            item.pBestPosNr = Number(posNr);
        }

        payload.items.push(item);
    }

    return payload;
}

async function submitPurchaseOrderForm(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = buildPayload();
    if (!payload) return;

    const btn = document.getElementById('purchase-order-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = id
        ? await api('PUT', `/purchase-orders/${id}`, payload)
        : await api('POST', '/purchase-orders', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Purchase Order' : 'Save Purchase Order';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed.', 'error');
        return;
    }

    closeModal('modal-purchase-order-form-overlay');
    toast(message || (id ? 'Purchase order updated.' : 'Purchase order created.'), 'success');
    window.location.reload();
}

async function openInspect(id) {
    const { ok, data, message } = await api('GET', `/purchase-orders/${id}`);
    if (!ok) {
        toast(message || 'Failed to load purchase order.', 'error');
        return;
    }

    const info = data?.order_info || {};
    const items = Array.isArray(data?.items) ? data.items : [];

    document.getElementById('po-view-badge').textContent = `#${info.pBestNr ?? id}`;
    document.getElementById('po-view-supplier').textContent = info.lieferant || '—';
    document.getElementById('po-view-ordered').textContent = fmtDate(info.bestDat);
    document.getElementById('po-view-expected').textContent = fmtDate(info.erwLieferDat);

    const statusEl = document.getElementById('po-view-status');
    const status = info.status || 'offen';
    statusEl.textContent = status;
    statusEl.className = `status-badge status-${status}`;
    statusEl.hidden = false;

    const totalQty = Number(data?.total_ordered ?? 0);
    const totalEur = Number(data?.total_value ?? 0);

    const totalsRow = `
        <div class="inspect-total-row" aria-label="Purchase order totals">
            <div></div>
            <div></div>
            <div></div>
            <div class="inspect-total-cell">
                <span>Total Qty</span>
                <strong>${esc(String(totalQty))}</strong>
            </div>
            <div></div>
            <div class="inspect-total-cell">
                <span>Total EUR</span>
                <strong>€${esc(fmtMoney(totalEur))}</strong>
            </div>
        </div>
    `;

    const itemsEl = document.getElementById('po-view-items');
    if (!items.length) {
        itemsEl.innerHTML = '<div class="inspect-empty">No items for this purchase order.</div>' + totalsRow;
    } else {
        const rows = items.map((item) => {
            const pos = item.pBestPosNr != null ? item.pBestPosNr : '—';
            const productId = item.fArtikelNr != null ? item.fArtikelNr : '—';
            const productName = item.bezeichnung || '[unknown]';

            return `
                <div class="inspect-item-row">
                    <div>${esc(pos)}</div>
                    <div>${esc(productId)}</div>
                    <div title="${esc(productName)}">${esc(productName)}</div>
                    <div class="num">${Number(item.bestMenge || 0)}</div>
                    <div class="num">€${fmtMoney(item.ekPreis)}</div>
                    <div class="num">€${fmtMoney(item.line_total)}</div>
                </div>
            `;
        }).join('');

        itemsEl.innerHTML = rows + totalsRow;
    }

    document.getElementById('modal-purchase-order-view-overlay').classList.add('open');
}

function fmtDate(value) {
    if (!value) return '—';
    return String(value).slice(0, 10);
}

function fmtMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

async function openReceive(id) {
    clearFormErrors();

    const { ok, data, message } = await api('GET', `/purchase-orders/${id}`);
    if (!ok) {
        toast(message || 'Failed to load purchase order.', 'error');
        return;
    }

    const info = data?.order_info || {};
    const items = Array.isArray(data?.items) ? data.items : [];

    document.getElementById('f-receive-id').value = info.pBestNr ?? id;
    document.getElementById('po-receive-badge').textContent = `#${info.pBestNr ?? id}`;
    document.getElementById('err-receive-items').textContent = '';

    const list = document.getElementById('receive-item-rows');
    list.innerHTML = items.map((item) => {
        const ordered = Number(item.bestMenge || 0);
        const delivered = Number(item.gelieferteMenge || 0);
        const remaining = Math.max(ordered - delivered, 0);
        const name = item.bezeichnung || '[unknown]';
        const done = remaining <= 0;

        return `
            <div class="receive-item-row" data-posnr="${esc(item.pBestPosNr)}" data-remaining="${remaining}">
                <div class="receive-item-name" title="${esc(name)}">${esc(name)}</div>
                <div class="num">${ordered}</div>
                <div class="num">${delivered}</div>
                <div class="num">${remaining}</div>
                <div class="receive-item-input">
                    <span class="number-input-wrap">
                        <input class="form-input i-receive" type="number" min="0" max="${remaining}" step="1" value="0" ${done ? 'disabled' : ''} aria-label="Receive now for ${esc(name)}">
                        <span class="number-stepper-controls">
                            <button type="button" class="number-stepper-button" title="Increase" aria-label="Increase" data-number-step="up">
                                <span class="modal-control-icon icon-chevron-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="number-stepper-button" title="Decrease" aria-label="Decrease" data-number-step="down">
                                <span class="modal-control-icon icon-chevron-down" aria-hidden="true"></span>
                            </button>
                        </span>
                    </span>
                </div>
            </div>
        `;
    }).join('');

    if (!items.length) {
        list.innerHTML = '<div class="inspect-empty">No items on this purchase order.</div>';
    }

    document.getElementById('modal-purchase-order-receive-overlay').classList.add('open');
}

async function submitReceive(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-receive-id').value;
    const errEl = document.getElementById('err-receive-items');
    const rows = [...document.querySelectorAll('#receive-item-rows .receive-item-row')];

    const items = [];
    let invalid = false;

    rows.forEach((row) => {
        const input = row.querySelector('.i-receive');
        if (!input || input.disabled) return;

        const qty = Number(input.value || 0);
        const remaining = Number(row.dataset.remaining || 0);

        if (!Number.isFinite(qty) || qty < 0 || qty > remaining) {
            invalid = true;
            input.classList.add('invalid');
            return;
        }

        if (qty > 0) items.push({ pBestPosNr: Number(row.dataset.posnr), gelieferteMenge: qty });
    });

    if (invalid) {
        errEl.textContent = 'Each quantity must be between 0 and the remaining amount.';
        return;
    }

    if (!items.length) {
        errEl.textContent = 'Enter at least one quantity to receive.';
        return;
    }

    const btn = document.getElementById('purchase-order-receive-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = await api('PATCH', `/purchase-orders/${id}/receive`, { items });

    btn.disabled = false;
    btn.textContent = 'Register Delivery';

    if (!ok) {
        const firstError = data?.errors && Object.keys(data.errors).length
            ? Object.values(data.errors)[0]
            : null;
        errEl.textContent = Array.isArray(firstError) ? firstError[0] : (message || 'Receive failed.');
        toast(message || 'Receive failed.', 'error');
        return;
    }

    closeModal('modal-purchase-order-receive-overlay');
    toast(message || 'Delivery registered.', 'success');
    window.location.reload();
}

function openDelete(id) {
    pendingDeleteId = id;
    document.getElementById('purchase-order-del-target-id').textContent = `#${id}`;
    document.getElementById('modal-purchase-order-del-overlay').classList.add('open');
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('purchase-order-del-confirm');
    btn.disabled = true;
    btn.textContent = 'Cancelling…';

    const { ok, data, message } = await api('DELETE', `/purchase-orders/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Cancel order';
    closeModal('modal-purchase-order-del-overlay');

    if (ok) {
        toast(message || 'Purchase order cancelled.', 'success');
        window.location.reload();
    } else {
        toast(message || data?.error || 'Cancellation failed.', 'error');
    }

    pendingDeleteId = null;
}

function applyValidationErrors(data) {
    Object.entries(data?.errors || {}).forEach(([key, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : String(messages);

        if (key.startsWith('items')) {
            const err = document.getElementById('err-items');
            if (err && !err.textContent) err.textContent = msg;
            return;
        }

        const err = document.getElementById('err-' + key);
        const field = document.getElementById('f-' + key);
        if (err) err.textContent = msg;
        if (field) field.classList.add('invalid');
    });
}

function setFieldError(key, message) {
    const err = document.getElementById('err-' + key);
    const field = document.getElementById('f-' + key);
    if (err) err.textContent = message;
    if (field) field.classList.add('invalid');
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach((el) => { el.textContent = ''; });
    document.querySelectorAll('.form-input.invalid, .form-select.invalid').forEach((el) => el.classList.remove('invalid'));
}

function closeModal(id) {
    window.AppDatePicker?.closeAll();
    document.getElementById(id)?.classList.remove('open');
}

function toDateInput(value) {
    if (!value) return '';

    if (value instanceof Date) {
        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    return String(value).slice(0, 10);
}

function moneyInput(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toFixed(2);
}

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function filterPurchaseOrderRows() {
    const input = document.getElementById('purchase-orders-search');
    const total = document.getElementById('purchase-orders-stat-total');
    const emptyRow = document.getElementById('purchase-orders-empty-filter-row');
    if (!input) return;

    const query = input.value.trim().toLowerCase();
    let visible = 0;

    document.querySelectorAll('.purchase-orders-table tbody tr[data-sort-row]').forEach((row) => {
        const haystack = [
            row.dataset.sortId,
            row.dataset.sortSupplier,
            row.dataset.sortStatus,
            row.dataset.sortOrdered,
            row.dataset.sortExpected,
        ].join(' ').toLowerCase();
        const match = !query || haystack.includes(query);

        row.hidden = !match;
        if (match) visible += 1;
    });

    if (total) total.textContent = String(visible);
    if (emptyRow) emptyRow.hidden = !query || visible > 0;
}

function initPurchaseOrdersPage() {
    ensureLookups();

    document.getElementById('purchase-orders-search')?.addEventListener('input', filterPurchaseOrderRows);
    document.getElementById('btn-add-purchase-order')?.addEventListener('click', openAdd);
    document.getElementById('purchase-order-form')?.addEventListener('submit', submitPurchaseOrderForm);
    document.getElementById('btn-add-purchase-order-item')?.addEventListener('click', () => addItemRow());
    document.getElementById('purchase-order-form-cancel')?.addEventListener('click', () => closeModal('modal-purchase-order-form-overlay'));
    document.getElementById('purchase-order-del-cancel')?.addEventListener('click', () => closeModal('modal-purchase-order-del-overlay'));
    document.getElementById('purchase-order-del-confirm')?.addEventListener('click', confirmDelete);
    document.getElementById('modal-purchase-order-view-close')?.addEventListener('click', () => closeModal('modal-purchase-order-view-overlay'));
    document.getElementById('purchase-order-receive-form')?.addEventListener('submit', submitReceive);
    document.getElementById('purchase-order-receive-cancel')?.addEventListener('click', () => closeModal('modal-purchase-order-receive-overlay'));

    document.querySelectorAll('.purchase-order-row').forEach((row) => {
        row.addEventListener('click', () => openInspect(row.dataset.id));
    });

    document.querySelectorAll('.purchase-order-edit').forEach((btn) => {
        btn.addEventListener('click', (event) => { event.stopPropagation(); openEdit(btn.dataset.id); });
    });

    document.querySelectorAll('.purchase-order-receive').forEach((btn) => {
        btn.addEventListener('click', (event) => { event.stopPropagation(); openReceive(btn.dataset.id); });
    });

    document.querySelectorAll('.purchase-order-delete').forEach((btn) => {
        btn.addEventListener('click', (event) => { event.stopPropagation(); openDelete(btn.dataset.id); });
    });

    document.querySelectorAll('.overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (window.AppDatePicker?.hasOpen()) {
                window.AppDatePicker.closeAll();
                return;
            }

            closeModal('modal-purchase-order-form-overlay');
            closeModal('modal-purchase-order-del-overlay');
            closeModal('modal-purchase-order-view-overlay');
            closeModal('modal-purchase-order-receive-overlay');
        }
    });

    filterPurchaseOrderRows();
}

if (document.querySelector('.purchase-orders-page')) {
    initPurchaseOrdersPage();
}
