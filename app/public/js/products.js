const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

let pendingDeleteId = null;

async function api(method, path, body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
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

// ── SEARCH + FILTERS (client-side row hide) ───────────────────────────
function filterProductRows() {
    const search = document.getElementById('search');
    const stockSel = document.getElementById('filter-stock');
    const wgSel = document.getElementById('filter-wg');
    const emptyRow = document.getElementById('products-empty-filter-row');
    if (!search) return;

    const q = search.value.trim().toLowerCase();
    const stockVal = stockSel ? stockSel.value : 'all';
    const wgVal = wgSel ? wgSel.value : 'all';

    const rows = document.querySelectorAll('.products-table tbody tr[data-sort-row]');
    let visible = 0;

    rows.forEach((row) => {
        const haystack = [row.dataset.sortId, row.dataset.sortName].join(' ').toLowerCase();
        let match = !q || haystack.includes(q);
        if (match && stockVal !== 'all' && row.dataset.filterStock !== stockVal) match = false;
        if (match && wgVal !== 'all' && row.dataset.filterWg !== wgVal) match = false;

        row.hidden = !match;
        if (match) visible += 1;
    });

    if (emptyRow) emptyRow.hidden = visible > 0 || rows.length === 0;
}

// ── ADD / EDIT FORM ───────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('product-form')?.reset();
    document.getElementById('f-id').value = '';
    // Stock is only set at creation time; it can be changed later via Adjust Stock.
    const stockGroup = document.getElementById('fg-bestand');
    if (stockGroup) stockGroup.style.display = '';
    document.getElementById('modal-form-title').textContent = 'New Product';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Product';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-bezeichnung')?.focus();
}

async function openEdit(id) {
    clearFormErrors();

    const { ok, data, message } = await api('GET', `/products/${id}`);
    if (!ok) {
        toast(message || 'Failed to load product.', 'error');
        return;
    }

    document.getElementById('f-id').value = data.pArtikelNr ?? '';
    document.getElementById('f-bezeichnung').value = data.bezeichnung || '';
    document.getElementById('f-ekPreis').value = data.ekPreis ?? '';
    document.getElementById('f-vkPreis').value = data.vkPreis ?? '';
    document.getElementById('f-meldeBest').value = data.meldeBest ?? '';
    document.getElementById('f-fWgNr').value = data.fWgNr;

    // Stock is not editable here (PUT ignores it) — use Adjust Stock instead.
    const stockGroup = document.getElementById('fg-bestand');
    if (stockGroup) stockGroup.style.display = 'none';

    document.getElementById('modal-form-title').textContent = 'Edit Product';
    document.getElementById('modal-form-badge').textContent = `#${data.pArtikelNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Product';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-bezeichnung')?.focus();
}

async function submitProductForm(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = {
        bezeichnung: document.getElementById('f-bezeichnung').value.trim(),
        fWgNr: parseInt(document.getElementById('f-fWgNr').value),
        ekPreis: parseFloat(document.getElementById('f-ekPreis').value),
        vkPreis: parseFloat(document.getElementById('f-vkPreis').value),
        meldeBest: parseInt(document.getElementById('f-meldeBest').value),
    };

    // Stock is only set on creation; updates go through the Adjust Stock flow.
    if (!id) payload.bestand = parseInt(document.getElementById('f-bestand').value);

    const btn = document.getElementById('modal-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = id
        ? await api('PUT', `/products/${id}`, payload)
        : await api('POST', '/products', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Product' : 'Save Product';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed.', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(message || (id ? 'Product updated.' : 'Product created.'), 'success');
    window.location.reload();
}

// ── DELETE ────────────────────────────────────────────────────────────
function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('del-target-name').textContent = name || 'this product';
    document.getElementById('del-target-id').textContent = id;
    document.getElementById('modal-del-overlay').classList.add('open');
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('modal-del-confirm');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const { ok, data, message } = await api('DELETE', `/products/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Discontinue';
    closeModal('modal-del-overlay');

    if (ok) {
        toast(message || 'Product discontinued.', 'success');
        window.location.reload();
    } else {
        toast(message || data?.error || 'Delete failed.', 'error');
    }

    pendingDeleteId = null;
}

// ── ADJUST STOCK (admin) ──────────────────────────────────────────────
function openAdjust(id, name, stock) {
    clearFormErrors();
    document.getElementById('f-adjust-id').value = id;
    document.getElementById('adjust-badge').textContent = `#${id}`;
    document.getElementById('adjust-product-name').textContent = name || `Product #${id}`;
    document.getElementById('adjust-current-stock').textContent = stock ?? '—';
    document.getElementById('f-adjust-bestand').value = stock ?? '';
    document.getElementById('f-adjust-reason').value = '';
    document.getElementById('modal-adjust-overlay').classList.add('open');
    document.getElementById('f-adjust-bestand')?.focus();
}

async function submitAdjust(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-adjust-id').value;
    const bestand = parseInt(document.getElementById('f-adjust-bestand').value, 10);
    const reason = document.getElementById('f-adjust-reason').value.trim();

    let valid = true;
    if (!Number.isFinite(bestand) || bestand < 0) {
        setAdjustError('bestand', 'Enter a valid stock level (0 or more).');
        valid = false;
    }
    if (reason.length < 5) {
        setAdjustError('reason', 'Reason must be at least 5 characters.');
        valid = false;
    }
    if (!valid) return;

    const btn = document.getElementById('modal-adjust-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = await api('PATCH', `/products/${id}/adjust-stock`, { bestand, reason });

    btn.disabled = false;
    btn.textContent = 'Save Adjustment';

    if (!ok) {
        applyAdjustErrors(data?.errors || {});
        toast(message || 'Adjustment failed.', 'error');
        return;
    }

    closeModal('modal-adjust-overlay');
    toast(message || 'Stock adjusted.', 'success');
    window.location.reload();
}

function setAdjustError(key, msg) {
    const err = document.getElementById('err-adjust-' + key);
    const field = document.getElementById('f-adjust-' + key);
    if (err) err.textContent = msg;
    if (field) field.classList.add('invalid');
}

function applyAdjustErrors(errors) {
    Object.entries(errors).forEach(([key, messages]) => {
        setAdjustError(key, Array.isArray(messages) ? messages[0] : String(messages));
    });
}

// ── STOCK HISTORY (all roles) ─────────────────────────────────────────
async function openHistory(id) {
    const { ok, data, message } = await api('GET', `/products/${id}/stock-history`);
    if (!ok) {
        toast(message || 'Failed to load stock history.', 'error');
        return;
    }

    document.getElementById('history-badge').textContent = `#${id}`;

    const rows = Array.isArray(data) ? [...data] : [];
    rows.sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));

    const el = document.getElementById('history-items');
    if (!rows.length) {
        el.innerHTML = '<div class="inspect-empty">No stock adjustments recorded for this product.</div>';
    } else {
        el.innerHTML = rows.map((row) => {
            const oldB = Number(row.old_bestand ?? 0);
            const newB = Number(row.new_bestand ?? 0);
            const delta = newB - oldB;
            const deltaStr = (delta > 0 ? '+' : '') + delta;
            const user = row.user_name || (row.user_id != null ? `User #${row.user_id}` : '—');
            const date = row.created_at ? String(row.created_at).slice(0, 10) : '—';
            const reason = row.reason || '—';

            return `
                <div class="inspect-item-row">
                    <div>${esc(date)}</div>
                    <div title="${esc(user)}">${esc(user)}</div>
                    <div class="num">${oldB}</div>
                    <div class="num">${newB}</div>
                    <div class="num ${delta >= 0 ? 'delta-up' : 'delta-down'}">${esc(deltaStr)}</div>
                    <div title="${esc(reason)}">${esc(reason)}</div>
                </div>
            `;
        }).join('');
    }

    document.getElementById('modal-history-overlay').classList.add('open');
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
function initProductsPage() {
    document.getElementById('search')?.addEventListener('input', filterProductRows);
    document.getElementById('filter-stock')?.addEventListener('change', filterProductRows);
    document.getElementById('filter-wg')?.addEventListener('change', filterProductRows);
    document.getElementById('btn-add')?.addEventListener('click', openAdd);
    document.getElementById('product-form')?.addEventListener('submit', submitProductForm);
    document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
    document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));
    document.getElementById('modal-del-confirm')?.addEventListener('click', confirmDelete);
    document.getElementById('adjust-form')?.addEventListener('submit', submitAdjust);
    document.getElementById('modal-adjust-cancel')?.addEventListener('click', () => closeModal('modal-adjust-overlay'));
    document.getElementById('modal-history-close')?.addEventListener('click', () => closeModal('modal-history-overlay'));

    document.querySelectorAll('.product-edit').forEach((btn) => {
        btn.addEventListener('click', () => openEdit(btn.dataset.id));
    });

    document.querySelectorAll('.product-adjust').forEach((btn) => {
        btn.addEventListener('click', () => openAdjust(btn.dataset.id, btn.dataset.name, btn.dataset.stock));
    });

    document.querySelectorAll('.product-history').forEach((btn) => {
        btn.addEventListener('click', () => openHistory(btn.dataset.id));
    });

    document.querySelectorAll('.product-delete').forEach((btn) => {
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
            closeModal('modal-adjust-overlay');
            closeModal('modal-history-overlay');
        }
    });

    filterProductRows();
}

if (document.querySelector('.products-page')) {
    initProductsPage();
}
