// Admin: stock for each colour and size combination.
// Uses the colour and size lists kept by product-variants.js (colorEntries, sizes).
// With no colours or sizes, the single "Stock Quantity" field is used instead.
(function () {
    const box = document.getElementById('variantStockBox');
    const rowsHost = document.getElementById('variantStockRows');
    const totalEl = document.getElementById('variantStockTotal');
    const dataEl = document.getElementById('variantStockData');
    const title = document.getElementById('variantStockTitle');
    const singleGroup = document.getElementById('singleStockGroup');
    const singleInput = singleGroup ? singleGroup.querySelector('input[name="stock"]') : null;
    if (!box || !rowsHost || !dataEl || !singleInput) return;

    // Values already typed (or loaded from the server), kept across re-renders.
    const typed = {};
    (window.INITIAL_VARIANT_STOCK || []).forEach(function (r) {
        typed[keyOf(String(r.color ?? ''), String(r.size ?? ''))] = r.stock;
    });

    function keyOf(color, size) {
        return color + '\u001f' + size;
    }

    function combos() {
        const colors = (typeof colorEntries !== 'undefined' ? colorEntries : [])
            .map(function (c) { return c.name; }).filter(Boolean);
        const sizeNames = (typeof sizes !== 'undefined' ? sizes : []).filter(Boolean);
        if (!colors.length && !sizeNames.length) return [];
        const cs = colors.length ? colors : [''];
        const ss = sizeNames.length ? sizeNames : [''];
        const out = [];
        cs.forEach(function (c) { ss.forEach(function (s) { out.push({ color: c, size: s }); }); });
        return out;
    }

    function sync() {
        const rows = [];
        let total = 0;
        rowsHost.querySelectorAll('input[data-key]').forEach(function (inp) {
            rows.push({ color: inp.dataset.color, size: inp.dataset.size, stock: inp.value });
            const n = parseInt(inp.value, 10);
            if (!isNaN(n) && n > 0) total += n;
        });
        dataEl.value = JSON.stringify(rows);
        if (totalEl) totalEl.textContent = String(total);
    }

    function cell(text, cls) {
        const span = document.createElement('span');
        span.className = cls || '';
        span.textContent = text;
        return span;
    }

    function render() {
        // Keep what is typed right now before the rows are rebuilt.
        rowsHost.querySelectorAll('input[data-key]').forEach(function (inp) {
            typed[inp.dataset.key] = inp.value;
        });

        const list = combos();
        const hasOptions = list.length > 0;
        box.hidden = !hasOptions;
        if (title) title.hidden = !hasOptions;
        singleGroup.hidden = hasOptions;
        singleInput.disabled = hasOptions;

        rowsHost.textContent = '';
        if (hasOptions) {
            const head = document.createElement('div');
            head.className = 'vs-row vs-head';
            head.append(cell('Colour'), cell('Size'), cell('Stock'));
            rowsHost.appendChild(head);

            list.forEach(function (combo) {
                const key = keyOf(combo.color, combo.size);
                const row = document.createElement('div');
                row.className = 'vs-row';

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = '1';
                input.placeholder = '0';
                input.required = true;
                input.className = 'form-input vs-input';
                input.dataset.key = key;
                input.dataset.color = combo.color;
                input.dataset.size = combo.size;
                input.setAttribute('aria-label', 'Stock for ' + (combo.color || 'any colour') + ' ' + (combo.size || ''));
                const previous = typed[key];
                input.value = previous === undefined || previous === null ? '' : String(previous);
                input.addEventListener('input', sync);

                row.append(
                    cell(combo.color || '—', 'vs-color'),
                    cell(combo.size || '—', 'vs-size'),
                    input
                );
                rowsHost.appendChild(row);
            });
        }
        sync();
    }

    window.renderVariantStockGrid = render;
    render();
})();