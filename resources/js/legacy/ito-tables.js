/**
 * Modo ficha en listados (tablet/celular): data-label desde thead + clase cards.
 * Opt-out: table[data-ito-no-cards]
 */
(function () {
  function headerText(th) {
    return (th.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function isActionCell(td) {
    if (td.querySelector('a, button, form, .ito-actions, .btn')) return true;
    var t = (td.textContent || '').trim();
    return t === '' && td.children.length > 0;
  }

  function labelTable(table) {
    if (table.closest('.asistencias-matrix, .asistencias-matrix-form')) return;
    if (table.hasAttribute('data-ito-no-cards')) return;
    if (table.dataset.itoCardsReady === '1' && !table.querySelector('tbody td:not([data-label])')) return;

    table.classList.add('ito-table', 'ito-table--cards');
    var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), headerText);
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var cells = tr.querySelectorAll('td');
      if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
        cells[0].classList.add('ito-empty');
        return;
      }
      cells.forEach(function (td, i) {
        if (td.hasAttribute('data-label')) return;
        var label = headers[i] || '';
        if (!label && isActionCell(td)) {
          label = 'Acciones';
          td.classList.add('is-actions');
        } else if (label === '' || /^acciones?$/i.test(label)) {
          td.classList.add('is-actions');
        }
        td.setAttribute('data-label', label);
      });
    });
    table.dataset.itoCardsReady = '1';
  }

  function boot() {
    document.querySelectorAll('table.ito-table, .ito-page table.table, .ito-card table.table').forEach(labelTable);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('ito:tables-refresh', boot);
})();
