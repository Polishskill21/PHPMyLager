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

// toast(), flashToast() and esc() are provided globally by public/js/feedback.js
// (loaded in layouts/app.blade.php before this script).

// Search and sorting are handled server-side by list-loadmore.js.

// ── PRODUCT GROUP DETAIL ──────────────────────────────────────────────
function renderGroupProducts(products) {
    const list = document.getElementById('group-products-list');
    if (!list) return;

    if (!products.length) {
        list.innerHTML = '<div class="inspect-empty">No products are assigned to this group.</div>';
        return;
    }

    list.innerHTML = products.map((product) => `
        <div class="inspect-item-row">
            <div>#${esc(product.pArtikelNr)}</div>
            <div title="${esc(product.bezeichnung || '')}">${esc(product.bezeichnung || '—')}</div>
        </div>
    `).join('');
}

async function openGroupProducts(id, name) {
    const list = document.getElementById('group-products-list');
    const badge = document.getElementById('group-products-badge');
    const subtitle = document.getElementById('group-products-subtitle');

    if (badge) badge.textContent = `#${id}`;
    if (subtitle) subtitle.textContent = `${name || `Group ${id}`} products.`;
    if (list) {
        list.innerHTML = '<div class="inspect-empty">Loading products…</div>';
    }

    document.getElementById('modal-group-products-overlay')?.classList.add('open');

    const { ok, data, message } = await api('GET', `/warehouse-groups/${id}/products`);
    if (!ok) {
        renderGroupProducts([]);
        toast(message || 'Failed to load group products.', 'error');
        return;
    }

    renderGroupProducts(Array.isArray(data) ? data : []);
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
    flashToast(message || (id ? 'Product group updated.' : 'Product group created.'), 'success');
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
    document.getElementById('btn-add-group')?.addEventListener('click', openAdd);
    document.getElementById('group-form')?.addEventListener('submit', submitGroupForm);
    document.getElementById('group-form-cancel')?.addEventListener('click', () => closeModal('modal-group-form-overlay'));
    document.getElementById('group-products-close')?.addEventListener('click', () => closeModal('modal-group-products-overlay'));

    document.getElementById('warehouse-table')?.addEventListener('click', (event) => {
        const editBtn = event.target.closest('.group-edit');
        if (editBtn) return openEdit(editBtn.dataset.id, editBtn.dataset.name);

        const row = event.target.closest('tr[data-group-id]');
        if (row) openGroupProducts(row.dataset.groupId, row.dataset.groupName);
    });

    document.querySelectorAll('.overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal('modal-group-form-overlay');
            closeModal('modal-group-products-overlay');
        }
    });
}

if (document.querySelector('.warehouse-page')) {
    initWarehousePage();
}
