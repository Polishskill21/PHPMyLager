(() => {
    const tables = document.querySelectorAll('.data-table[data-static-sort]');

    tables.forEach((table) => {
        const headers = [...table.querySelectorAll('thead th[data-sort]')];
        const body = table.tBodies[0];
        if (!headers.length || !body) return;

        const rows = [...body.querySelectorAll('tr[data-sort-row]')];
        if (rows.length < 2) return;

        const state = { key: null, direction: 1 };

        headers.forEach((header) => {
            header.addEventListener('click', () => {
                const key = header.dataset.sort;
                if (!key) return;

                state.direction = state.key === key ? state.direction * -1 : 1;
                state.key = key;

                headers.forEach((item) => {
                    item.classList.toggle('sorted', item === header);
                    const arrow = item.querySelector('.sort-arrow');
                    if (arrow) arrow.textContent = '↕';
                });

                const activeArrow = header.querySelector('.sort-arrow');
                if (activeArrow) activeArrow.textContent = state.direction === 1 ? '↑' : '↓';

                const type = header.dataset.sortType || 'text';
                const sortedRows = [...body.querySelectorAll('tr[data-sort-row]')]
                    .sort((a, b) => compareValues(getSortValue(a, key), getSortValue(b, key), type) * state.direction);

                sortedRows.forEach((row) => body.appendChild(row));
            });
        });
    });

    function getSortValue(row, key) {
        return row.dataset[toDatasetKey(key)] || '';
    }

    function toDatasetKey(key) {
        return `sort${key.replace(/(^|-)([a-z])/g, (_, __, char) => char.toUpperCase())}`;
    }

    function compareValues(a, b, type) {
        if (type === 'number') {
            return (Number.parseFloat(a) || 0) - (Number.parseFloat(b) || 0);
        }

        return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
    }
})();
