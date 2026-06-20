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

function openAdd() {
    clearFormErrors();
    document.getElementById('supplier-form')?.reset();
    document.getElementById('f-id').value = '';
    document.getElementById('supplier-form-title').textContent = 'New Supplier';
    document.getElementById('supplier-form-badge').textContent = 'CREATE';
    document.getElementById('supplier-form-submit').textContent = 'Save Supplier';
    document.getElementById('modal-supplier-form-overlay').classList.add('open');
    document.getElementById('f-name')?.focus();
}

async function openEdit(id) {
    clearFormErrors();

    const { ok, data, message } = await api('GET', `/suppliers/${id}`);
    if (!ok) {
        toast(message || 'Failed to load supplier.', 'error');
        return;
    }

    document.getElementById('f-id').value = data.pLiefNr ?? '';
    document.getElementById('f-name').value = data.name || '';
    document.getElementById('f-email').value = data.email || '';
    document.getElementById('f-strasse').value = data.strasse || '';
    document.getElementById('f-plz').value = data.plz || '';
    document.getElementById('f-ort').value = data.ort || '';

    document.getElementById('supplier-form-title').textContent = 'Edit Supplier';
    document.getElementById('supplier-form-badge').textContent = `#${data.pLiefNr}`;
    document.getElementById('supplier-form-submit').textContent = 'Update Supplier';
    document.getElementById('modal-supplier-form-overlay').classList.add('open');
    document.getElementById('f-name')?.focus();
}

function buildPayload() {
    return {
        name: document.getElementById('f-name').value.trim(),
        email: optionalValue('f-email'),
        strasse: optionalValue('f-strasse'),
        plz: optionalValue('f-plz'),
        ort: optionalValue('f-ort'),
    };
}

function optionalValue(id) {
    const value = document.getElementById(id)?.value?.trim() || '';
    return value || null;
}

async function submitSupplierForm(event) {
    event.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = buildPayload();
    const btn = document.getElementById('supplier-form-submit');

    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data, message } = id
        ? await api('PUT', `/suppliers/${id}`, payload)
        : await api('POST', '/suppliers', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Supplier' : 'Save Supplier';

    if (!ok) {
        applyValidationErrors(data);
        toast(message || 'Save failed.', 'error');
        return;
    }

    closeModal('modal-supplier-form-overlay');
    flashToast(message || (id ? 'Supplier updated.' : 'Supplier created.'), 'success');
    window.location.reload();
}

function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('supplier-del-target-id').textContent = id;
    document.getElementById('supplier-del-target-name').textContent = name || 'this supplier';
    document.getElementById('modal-supplier-del-overlay').classList.add('open');
}

async function confirmDelete() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('supplier-del-confirm');
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    const { ok, data, message } = await api('DELETE', `/suppliers/${pendingDeleteId}`);

    btn.disabled = false;
    btn.textContent = 'Delete';
    closeModal('modal-supplier-del-overlay');

    if (ok) {
        flashToast(message || 'Supplier deleted.', 'success');
        window.location.reload();
    } else {
        toast(message || data?.error || 'Delete failed.', 'error');
    }

    pendingDeleteId = null;
}

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

// Search and sorting are handled server-side by list-loadmore.js.

function initSuppliersPage() {
    document.getElementById('btn-add-supplier')?.addEventListener('click', openAdd);
    document.getElementById('supplier-form')?.addEventListener('submit', submitSupplierForm);
    document.getElementById('supplier-form-cancel')?.addEventListener('click', () => closeModal('modal-supplier-form-overlay'));
    document.getElementById('supplier-del-cancel')?.addEventListener('click', () => closeModal('modal-supplier-del-overlay'));
    document.getElementById('supplier-del-confirm')?.addEventListener('click', confirmDelete);

    document.getElementById('suppliers-table')?.addEventListener('click', (event) => {
        const editBtn = event.target.closest('.supplier-edit');
        if (editBtn) return openEdit(editBtn.dataset.id);

        const deleteBtn = event.target.closest('.supplier-delete');
        if (deleteBtn) return openDelete(deleteBtn.dataset.id, deleteBtn.dataset.name);
    });

    document.querySelectorAll('.overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal('modal-supplier-form-overlay');
            closeModal('modal-supplier-del-overlay');
        }
    });
}

if (document.querySelector('.suppliers-page')) {
    initSuppliersPage();
}
