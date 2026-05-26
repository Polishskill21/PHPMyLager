// ── CONFIG ────────────────────────────────────────────────────────────
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
const CAN_WRITE  = document.querySelector('meta[name="can-write"]')?.content === 'true';
const CAN_DELETE = document.querySelector('meta[name="can-delete"]')?.content === 'true';
const ROW_H      = 56;
const OVER       = 8; 

// ── STATE ─────────────────────────────────────────────────────────────
let allProducts  = [];
let warehouseGroups = {};
let filtered     = [];
let sortKey      = 'pArtikelNr';
let sortDir      = 1;
let pendingDeleteId = null;

// ── API HELPERS ───────────────────────────────────────────────────────
async function api(method, path, body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
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
    document.getElementById('toast-area').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ── LOAD DATA ─────────────────────────────────────────────────────────
async function loadWarehouseGroups() {
    const { ok, data } = await api('GET', '/warehouse-groups');
    if (!ok) return;
    const sel = document.getElementById('filter-wg');
    const fSel = document.getElementById('f-fWgNr');
    const list = Array.isArray(data.data) ? data.data : [];
    list.forEach(g => {
        warehouseGroups[g.pWgNr] = g.warengruppe || `Group ${g.pWgNr}`;
        sel.insertAdjacentHTML('beforeend', `<option value="${g.pWgNr}">${g.warengruppe || g.pWgNr}</option>`);
        fSel.insertAdjacentHTML('beforeend', `<option value="${g.pWgNr}">${g.warengruppe || g.pWgNr}</option>`);
    });
}

async function loadProducts() {
    const wrap = document.getElementById('vscroll-inner');
    wrap.innerHTML = `<div class="loading-row"><div class="spinner"></div> Loading products…</div>`;
    const { ok, data } = await api('GET', '/products');
    if (!ok) { toast('Failed to load products', 'error'); return; }
    allProducts = Array.isArray(data.data) ? data.data : [];
    applyFilters();
}

// ── FILTER + SORT ─────────────────────────────────────────────────────
function applyFilters() {
    const q     = document.getElementById('search').value.trim().toLowerCase();
    const stock = document.getElementById('filter-stock').value;
    const wg    = document.getElementById('filter-wg').value;

    filtered = allProducts.filter(p => {
        if (q && !p.bezeichnung?.toLowerCase().includes(q) && !String(p.pArtikelNr).includes(q)) return false;
        if (wg !== 'all' && p.fWgNr != wg) return false;
        if (stock === 'ok'    && !(p.bestand > p.meldeBest)) return false;
        if (stock === 'warn'  && !(p.bestand > 0 && p.bestand <= p.meldeBest)) return false;
        if (stock === 'empty' && p.bestand > 0) return false;
        return true;
    });

    filtered.sort((a, b) => {
        let av = a[sortKey];
        let bv = b[sortKey];

        if (['pArtikelNr', 'ekPreis', 'vkPreis', 'bestand', 'meldeBest'].includes(sortKey)) {
            av = parseFloat(av) || 0;
            bv = parseFloat(bv) || 0;
            return (av - bv) * sortDir;
        }

        if (typeof av === 'string') av = av.toLowerCase(), bv = (bv||'').toLowerCase();
        return av > bv ? sortDir : (av < bv ? -sortDir : 0);
    });

    const low = allProducts.filter(p => p.bestand <= p.meldeBest && p.bestand > 0).length;
    document.getElementById('stat-total').textContent = allProducts.length;
    document.getElementById('stat-low').textContent   = low;

    renderVirtual();
}

// ── VIRTUAL SCROLL ────────────────────────────────────────────────────
function renderVirtual() {
    const vscroll = document.getElementById('vscroll');
    const inner   = document.getElementById('vscroll-inner');

    if (filtered.length === 0) {
        inner.style.height = '200px';
        inner.innerHTML = `<div class="empty-state"><div class="icon">◈</div><p>No products match your filters.</p></div>`;
        return;
    }

    inner.style.height = (filtered.length * ROW_H) + 'px';
    inner.innerHTML = '';

    function paint() {
        const scrollTop = vscroll.scrollTop;
        const viewH     = vscroll.clientHeight;
        const startIdx  = Math.max(0, Math.floor(scrollTop / ROW_H) - OVER);
        const endIdx    = Math.min(filtered.length - 1, Math.ceil((scrollTop + viewH) / ROW_H) + OVER);

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

function buildRow(p, idx) {
    const top = idx * ROW_H;
    const stockClass = p.bestand === 0 ? 'stock-empty' : p.bestand <= p.meldeBest ? 'stock-warn' : 'stock-ok';
    const stockIcon  = p.bestand === 0 ? '●' : p.bestand <= p.meldeBest ? '◐' : '●';
    const wgName = warehouseGroups[p.fWgNr] || p.fWgNr;

    const editBtn = CAN_WRITE  ? `<button class="btn-icon edit" title="Edit" data-id="${p.pArtikelNr}">✎</button>` : '';
    const delBtn  = CAN_DELETE ? `<button class="btn-icon del"  title="Discontinue" data-id="${p.pArtikelNr}">⊗</button>` : '';

    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.idx = idx;
    row.style.top = top + 'px';
    row.innerHTML = `
        <div class="cell cell-id">#${p.pArtikelNr}</div>
        <div class="cell cell-name">${esc(p.bezeichnung || '—')}</div>
        <div class="cell" title="${esc(wgName)}" style="font-size:.78rem; color:var(--muted)">${esc(wgName)}</div>
        <div class="cell cell-num">${fmt(p.ekPreis)}</div>
        <div class="cell cell-num">${fmt(p.vkPreis)}</div>
        <div class="cell cell-num">
            <span class="stock-badge ${stockClass}">${stockIcon} ${p.bestand ?? '—'}</span>
        </div>
        <div class="cell cell-num" style="color:var(--muted)">${p.meldeBest ?? '—'}</div>
        <div class="cell"><div class="actions">${editBtn}${delBtn}</div></div>
    `;

    if (CAN_WRITE) {
        row.querySelector('.btn-icon.edit')?.addEventListener('click', () => openEdit(p.pArtikelNr));
    }
    if (CAN_DELETE) {
        row.querySelector('.btn-icon.del')?.addEventListener('click', () => openDelete(p.pArtikelNr, p.bezeichnung));
    }
    return row;
}

function fmt(v) { return v != null ? Number(v).toFixed(2) : '—'; }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── EVENT LISTENERS ──────────────────────────────────────────────────
document.querySelectorAll('.th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
        const k = th.dataset.sort;
        if (sortKey === k) sortDir *= -1; else { sortKey = k; sortDir = 1; }
        document.querySelectorAll('.th').forEach(t => {
            t.classList.toggle('sorted', t.dataset.sort === sortKey);
            const arr = t.querySelector('.sort-arrow');
            if (arr) arr.textContent = t.dataset.sort === sortKey ? (sortDir === 1 ? '↑' : '↓') : '↕';
        });
        applyFilters();
    });
});

document.getElementById('search')?.addEventListener('input', applyFilters);
document.getElementById('filter-stock')?.addEventListener('change', applyFilters);
document.getElementById('filter-wg')?.addEventListener('change', applyFilters);
document.getElementById('btn-add')?.addEventListener('click', openAdd);

// ── MODALS ────────────────────────────────────────────────────────────
function openAdd() {
    clearFormErrors();
    document.getElementById('f-id').value = '';
    document.getElementById('product-form').reset();
    document.getElementById('modal-form-title').textContent = 'New Product';
    document.getElementById('modal-form-badge').textContent = 'CREATE';
    document.getElementById('modal-form-submit').textContent = 'Save Product';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-bezeichnung').focus();
}

function openEdit(id) {
    const p = allProducts.find(x => x.pArtikelNr === id);
    if (!p) return;
    clearFormErrors();
    document.getElementById('f-id').value          = p.pArtikelNr;
    document.getElementById('f-bezeichnung').value = p.bezeichnung || '';
    document.getElementById('f-ekPreis').value     = p.ekPreis ?? '';
    document.getElementById('f-vkPreis').value     = p.vkPreis ?? '';
    document.getElementById('f-bestand').value     = p.bestand ?? '';
    document.getElementById('f-meldeBest').value   = p.meldeBest ?? '';
    document.getElementById('f-fWgNr').value       = p.fWgNr;
    document.getElementById('modal-form-title').textContent = 'Edit Product';
    document.getElementById('modal-form-badge').textContent = `#${id}`;
    document.getElementById('modal-form-submit').textContent = 'Update Product';
    document.getElementById('modal-form-overlay').classList.add('open');
    document.getElementById('f-bezeichnung').focus();
}

document.getElementById('product-form')?.addEventListener('submit', async e => {
    e.preventDefault();
    clearFormErrors();

    const id = document.getElementById('f-id').value;
    const payload = {
        bezeichnung: document.getElementById('f-bezeichnung').value.trim(),
        fWgNr:       parseInt(document.getElementById('f-fWgNr').value),
        ekPreis:     parseFloat(document.getElementById('f-ekPreis').value),
        vkPreis:     parseFloat(document.getElementById('f-vkPreis').value),
        bestand:     parseInt(document.getElementById('f-bestand').value),
        meldeBest:   parseInt(document.getElementById('f-meldeBest').value),
    };

    const btn = document.getElementById('modal-form-submit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const { ok, data } = id
        ? await api('PUT',  `/products/${id}`, payload)
        : await api('POST', '/products',       payload);

    btn.disabled = false;
    btn.textContent = id ? 'Update Product' : 'Save Product';

    if (!ok) {
        if (data.errors) {
            Object.entries(data.errors).forEach(([k, msgs]) => {
                const el = document.getElementById('err-' + k);
                if (el) { el.textContent = msgs[0]; document.getElementById('f-'+k)?.classList.add('invalid'); }
            });
        }
        toast(data.message || 'Save failed', 'error');
        return;
    }

    closeModal('modal-form-overlay');
    toast(id ? 'Product updated.' : 'Product created.', 'success');
    await loadProducts();
});

function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('del-target-name').textContent = name || 'this product';
    document.getElementById('del-target-id').textContent   = id;
    document.getElementById('modal-del-overlay').classList.add('open');
}

document.getElementById('modal-del-confirm')?.addEventListener('click', async () => {
    if (!pendingDeleteId) return;
    const btn = document.getElementById('modal-del-confirm');
    btn.disabled = true; btn.textContent = 'Processing…';

    const { ok, data } = await api('DELETE', `/products/${pendingDeleteId}`);
    btn.disabled = false; btn.textContent = 'Discontinue';

    closeModal('modal-del-overlay');
    if (ok) {
        toast(data.message || 'Product discontinued.', 'success');
        await loadProducts();
    } else {
        toast(data.message || data.error || 'Delete failed.', 'error');
    }
    pendingDeleteId = null;
});

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.getElementById('modal-form-cancel')?.addEventListener('click', () => closeModal('modal-form-overlay'));
document.getElementById('modal-del-cancel')?.addEventListener('click', () => closeModal('modal-del-overlay'));

document.querySelectorAll('.overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) closeModal(ov.id); });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('modal-form-overlay');
        closeModal('modal-del-overlay');
    }
});

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
    document.querySelectorAll('.form-input.invalid, .form-select.invalid').forEach(el => el.classList.remove('invalid'));
}

// ── INIT ──────────────────────────────────────────────────────────────
if (document.getElementById('vscroll-inner')) {
    (async () => {
        await loadWarehouseGroups();
        await loadProducts();
    })();
}