// ─────────────────────────────────────────────────────────────────────────
// Generic load-more list driver.
//
// Self-initialises on any page that has a table marked with
// [data-list-endpoint]. The first chunk is already server-rendered; this script
// handles:
//   • "Load more"  → append the next page
//   • search box   → debounced server-side search (resets to page 1)
//   • filters      → server-side filter   (resets to page 1)
//   • header click → server-side sort     (resets to page 1)
//
// Configuration is read from data-* attributes so no per-page JS is required:
//   table  [data-list-endpoint]   API path (without the /api prefix), e.g. /products/page
//          [data-list-per-page]   rows per chunk
//          [data-list-page]       page number already rendered (usually 1)
//          [data-list-has-more]   "1" when more pages exist
//          [data-list-total]      total matching rows
//          [data-list-empty]      message shown when a query returns nothing
//   input  [data-list-search]     search box (sent as ?search=)
//   select [data-list-filter="x"] filter control (sent as ?x=value, "all" = none)
//   th     [data-sort="key"]      sortable column (sent as ?sort=key&dir=asc|desc)
//   button [data-list-more]       the "Load more" trigger
//
// After every render a `list:rendered` event is dispatched on document so a
// page's own script can react if it needs to.
// ─────────────────────────────────────────────────────────────────────────
(() => {
    const table = document.querySelector('table[data-list-endpoint]');
    if (!table) return;

    const tbody = table.tBodies[0];
    if (!tbody) return;

    const endpoint = table.dataset.listEndpoint;
    const perPage = parseInt(table.dataset.listPerPage || '50', 10);
    const emptyText = table.dataset.listEmpty || 'No results found.';
    const colspan = table.querySelectorAll('thead th').length || 1;

    const searchInput = document.querySelector('[data-list-search]');
    const filters = [...document.querySelectorAll('[data-list-filter]')];
    const headers = [...table.querySelectorAll('thead th[data-sort]')];
    const moreBtn = document.querySelector('[data-list-more]');
    const shownEl = document.getElementById('list-shown');
    const totalEls = [document.getElementById('list-total'), document.getElementById('list-total-status')].filter(Boolean);

    const state = {
        page: parseInt(table.dataset.listPage || '1', 10),
        hasMore: table.dataset.listHasMore === '1',
        total: parseInt(table.dataset.listTotal || '0', 10),
        sort: '',
        dir: 'asc',
        loading: false,
    };

    function buildQuery(page) {
        const params = new URLSearchParams();
        params.set('page', page);
        if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
        filters.forEach((sel) => {
            if (sel.value && sel.value !== 'all') params.set(sel.dataset.listFilter, sel.value);
        });
        if (state.sort) {
            params.set('sort', state.sort);
            params.set('dir', state.dir);
        }
        return params.toString();
    }

    function renderedRows() {
        return tbody.querySelectorAll('tr[data-row]').length;
    }

    function updateStatus() {
        if (shownEl) shownEl.textContent = renderedRows();
        totalEls.forEach((el) => { el.textContent = state.total; });
        if (moreBtn) moreBtn.hidden = !state.hasMore;
    }

    async function fetchPage(page, append) {
        if (state.loading) return;
        state.loading = true;
        if (moreBtn) moreBtn.disabled = true;

        try {
            const res = await fetch(`/api${endpoint}?${buildQuery(page)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await res.json().catch(() => ({}));
            const data = payload?.data ?? {};
            const html = data.html || '';
            const meta = data.meta || {};

            if (append) {
                tbody.insertAdjacentHTML('beforeend', html);
            } else {
                tbody.innerHTML = html ||
                    `<tr class="table-state-row"><td class="table-state-cell" colspan="${colspan}"><div class="empty-state">${emptyText}</div></td></tr>`;
            }

            state.page = meta.page ?? page;
            state.hasMore = !!meta.hasMore;
            state.total = meta.total ?? state.total;
            updateStatus();
            document.dispatchEvent(new CustomEvent('list:rendered', { detail: { append } }));
        } catch (err) {
            if (typeof toast === 'function') toast('Failed to load more rows.', 'error');
        } finally {
            state.loading = false;
            if (moreBtn) moreBtn.disabled = false;
        }
    }

    const reload = () => fetchPage(1, false);

    function debounce(fn, ms) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), ms);
        };
    }

    if (searchInput) searchInput.addEventListener('input', debounce(reload, 250));
    filters.forEach((sel) => sel.addEventListener('change', reload));

    headers.forEach((header) => {
        header.addEventListener('click', () => {
            const key = header.dataset.sort;
            if (!key) return;

            if (state.sort === key) {
                state.dir = state.dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort = key;
                state.dir = 'asc';
            }

            headers.forEach((h) => {
                h.classList.toggle('sorted', h === header);
                const arrow = h.querySelector('.sort-arrow');
                if (arrow) arrow.textContent = '↕';
            });
            const activeArrow = header.querySelector('.sort-arrow');
            if (activeArrow) activeArrow.textContent = state.dir === 'asc' ? '↑' : '↓';

            reload();
        });
    });

    if (moreBtn) {
        moreBtn.addEventListener('click', () => {
            if (state.hasMore) fetchPage(state.page + 1, true);
        });
    }

    updateStatus();
})();
