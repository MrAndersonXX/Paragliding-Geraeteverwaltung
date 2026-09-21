document.querySelectorAll('table[data-sortable]').forEach(function (table) {
    const body = table.tBodies[0];
    if (!body) {
        return;
    }
    const buttons = Array.from(table.querySelectorAll('thead .sort-button'));
    buttons.forEach(function (button) {
        button.setAttribute('aria-sort', 'none');
        button.addEventListener('click', function () {
            const column = Number(button.dataset.sort);
            const direction = button.dataset.direction === 'asc' ? -1 : 1;
            const rows = Array.from(body.rows).filter(function (row) {
                return row.dataset.sortableRow !== 'false' && row.cells.length > column;
            });
            buttons.forEach(function (other) {
                other.dataset.direction = '';
                other.setAttribute('aria-sort', 'none');
            });
            button.dataset.direction = direction === 1 ? 'asc' : 'desc';
            button.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
            rows.forEach(function (row, index) { row.dataset.sortOrder = String(index); });
            rows.sort(function (left, right) {
                const leftValue = left.cells[column].textContent.trim();
                const rightValue = right.cells[column].textContent.trim();
                const result = button.dataset.sortType === 'number'
                    ? (Number(leftValue.replace(',', '.')) || 0) - (Number(rightValue.replace(',', '.')) || 0)
                    : leftValue.localeCompare(rightValue, 'de', { numeric: true, sensitivity: 'base' });
                return result === 0 ? Number(left.dataset.sortOrder) - Number(right.dataset.sortOrder) : direction * result;
            });
            rows.forEach(function (row) { body.appendChild(row); });
        });
    });
});
