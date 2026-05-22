// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const CAN_WRITE  = document.querySelector('meta[name="can-write"]')?.content === 'true';
const ROW_H      = 56;
const OVER       = 8;

// ── STATE ─────────────────────────────────────────────────────────────
let allGroups = [];
let filtered  = [];
let sortKey   = 'pWgNr';
let sortDir   = 1;
let allProducts = null;

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
async function loadGroups() {
    const wrap = document.getElementById('vscroll-inner');
    wrap.innerHTML = `<div class="loading-row"><div class="spinner"></div> Loading product groups…</div>`;

    const { ok, data } = await api('GET', '/warehouse-groups');
    if (!ok) {
        toast('Failed to load product groups', 'error');
        wrap.innerHTML = `<div class="empty-state"><div class="icon">◫</div><p>Failed to load product groups.</p></div>`;
        return;
    }

    const list = data.warehouse_groups || [];
    allGroups = list.map(normalizeGroup);
    applyFilters();
    void ensureProductsLoaded();
}

function normalizeGroup(entry) {
    return {
        pWgNr: entry?.pWgNr,
        warengruppe: entry?.warengruppe || '',
    };
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q = document.getElementById('search').value.trim().toLowerCase();

    filtered = allGroups.filter(g => {
        if (!q) return true;
        const haystack = [g.pWgNr, g.warengruppe].join(' ').toLowerCase();
        return haystack.includes(q);
    });

    filtered.sort((a, b) => {
        let av = a[sortKey];
        let bv = b[sortKey];

        if (sortKey === 'pWgNr') {
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

    renderVirtual();
}

// ── VIRTUAL SCROLL ────────────────────────────────────────────────────
function renderVirtual() {
    const vscroll = document.getElementById('vscroll');
    const inner = document.getElementById('vscroll-inner');

    if (filtered.length === 0) {
        inner.style.height = '200px';
        inner.innerHTML = `<div class="empty-state"><div class="icon">◫</div><p>No product groups match your search.</p></div>`;
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

function buildRow(group, idx) {
    const top = idx * ROW_H;

    const editBtn = CAN_WRITE
        ? `<button class="btn-icon edit" title="Edit" data-id="${group.pWgNr}">✎</button>`
        : '';

    const row = document.createElement('div');
    row.className = 'row';
    row.classList.add('row-clickable');
    row.dataset.idx = idx;
    row.style.top = top + 'px';
    row.innerHTML = `
        <div class="cell cell-id">#${group.pWgNr ?? '—'}</div>
        <div class="cell cell-name" title="${esc(group.warengruppe)}">${esc(group.warengruppe || '—')}</div>
        <div class="cell"><div class="actions">${editBtn}</div></div>
    `;

    row.addEventListener('click', () => openGroupView(group));

    if (CAN_WRITE) {
        row.querySelector('.btn-icon.edit')?.addEventListener('click', (event) => {
            event.stopPropagation();
            openEdit(group.pWgNr);
        });
    }

    return row;
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

async function ensureProductsLoaded() {
    if (Array.isArray(allProducts)) return true;
    const { ok, data } = await api('GET', '/products');
    if (!ok) {
        allProducts = [];
        return false;
    }
    allProducts = data.products || [];
    return true;
}

async function openGroupView(group) {
    const overlay = document.getElementById('modal-view-overlay');
    const list = document.getElementById('group-products-list');
    const count = document.getElementById('group-products-count');
    const title = document.getElementById('modal-view-title');
    const badge = document.getElementById('modal-view-badge');

    title.textContent = group.warengruppe || 'Product Group';
    badge.textContent = `#${group.pWgNr}`;
    count.textContent = '0';
    list.innerHTML = `<div class="list-empty">Loading products…</div>`;
    overlay.classList.add('open');

    const ok = await ensureProductsLoaded();
    if (!ok) {
        list.innerHTML = `<div class="list-empty">Failed to load products.</div>`;
        return;
    }

    const items = allProducts.filter(p => String(p.fWgNr) === String(group.pWgNr));
    count.textContent = String(items.length);

    if (items.length === 0) {
        list.innerHTML = `<div class="list-empty">No products in this group.</div>`;
        return;
    }

    list.innerHTML = items.map(p => {
        const id = p.pArtikelNr ?? '—';
        const name = esc(p.bezeichnung || '—');
        return `
            <div class="list-row">
                <div class="list-cell">#${id}</div>
                <div class="list-cell" title="${name}">${name}</div>
            </div>
        `;
    }).join('');
}

// ── FORM + MODAL ──────────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('f-id').value = '';
    document.getElementById('group-form').reset();
    document.getElementById('modal-form-title').textContent = 'New Product Group';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Product Group';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-warengruppe').focus();
}

function openEdit(id) {
    const group = allGroups.find(g => Number(g.pWgNr) === Number(id));
    if (!group) return;

    clearFormErrors();
    document.getElementById('f-id').value = group.pWgNr;
    document.getElementById('f-warengruppe').value = group.warengruppe || '';

    document.getElementById('modal-form-title').textContent = 'Edit Product Group';
    document.getElementById('modal-form-badge').textContent = `#${group.pWgNr}`;
    document.getElementById('modal-form-submit').textContent = 'Update Product Group';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-warengruppe').focus();
}

document.getElementById('group-form')?.addEventListener('submit', async e => {
    e.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const name = document.getElementById('f-warengruppe').value.trim();
    const payload = { warengruppe: name === '' ? null : name };

    const btn = document.getElementById('modal-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data } = id
        ? await api('PUT', `/warehouse-groups/${id}`, payload)
        : await api('POST', '/warehouse-groups', payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Product Group' : 'Save Product Group';

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
    toast(id ? 'Product group updated.' : 'Product group created.', 'success');
    await loadGroups();
});

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
document.getElementById('modal-view-close')?.addEventListener('click', () => closeModal('modal-view-overlay'));

document.querySelectorAll('.overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('modal-form-overlay');
        closeModal('modal-view-overlay');
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
    loadGroups();
}
