/**
 * Hub search / command palette (Ctrl+K)
 * Groups results, arrow-key navigation, Enter to open.
 */
(function () {
  const wrap = document.querySelector('[data-hub-search]');
  if (!wrap) return;

  const input = wrap.querySelector('[data-hub-search-input]');
  const box = wrap.querySelector('[data-hub-search-results]');
  const dataEl = wrap.querySelector('[data-hub-search-data]');
  if (!input || !box || !dataEl) return;

  let modules = [];
  try {
    modules = JSON.parse(dataEl.textContent || '[]');
  } catch (e) {
    modules = [];
  }

  let timer = null;
  let entityHits = [];
  let activeIndex = -1;
  let flatItems = [];
  const searchUrl = wrap.getAttribute('data-search-url') || '';

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function groupOrder(name) {
    const order = ['Acciones', 'Módulos', 'Alumnos', 'Bloques', 'Comprobantes', 'Programa'];
    const i = order.indexOf(name);
    return i === -1 ? 50 : i;
  }

  function collectHits(q) {
    const query = (q || '').trim().toLowerCase();
    const modHits = (!query
      ? modules.slice(0, 8)
      : modules.filter(function (it) {
          return (it.label || '').toLowerCase().indexOf(query) !== -1
            || (it.group || '').toLowerCase().indexOf(query) !== -1;
        }).slice(0, 8)
    ).map(function (it) {
      return Object.assign({}, it, { group: it.group || 'Módulos' });
    });

    return modHits.concat(entityHits).slice(0, 20);
  }

  function groupHits(hits) {
    const map = {};
    hits.forEach(function (it) {
      const g = it.group || 'Otros';
      if (!map[g]) map[g] = [];
      map[g].push(it);
    });
    return Object.keys(map)
      .sort(function (a, b) {
        return groupOrder(a) - groupOrder(b);
      })
      .map(function (g) {
        return { group: g, items: map[g] };
      });
  }

  function setActive(index) {
    const items = box.querySelectorAll('[data-hub-option]');
    if (!items.length) {
      activeIndex = -1;
      input.removeAttribute('aria-activedescendant');
      return;
    }
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;
    activeIndex = index;
    items.forEach(function (el, i) {
      const on = i === activeIndex;
      el.classList.toggle('is-active', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
      if (on) {
        input.setAttribute('aria-activedescendant', el.id);
        el.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  function render(q) {
    const hits = collectHits(q);
    flatItems = hits;
    activeIndex = -1;
    input.removeAttribute('aria-activedescendant');

    if (!hits.length) {
      box.innerHTML =
        '<div class="topbar-search-empty" role="status">Sin resultados. Probá otro nombre, DNI o módulo.</div>';
      box.hidden = false;
      return;
    }

    const groups = groupHits(hits);
    let optionId = 0;
    box.innerHTML = groups
      .map(function (g) {
        const head =
          '<div class="topbar-search-group" role="presentation">' + esc(g.group) + '</div>';
        const rows = g.items
          .map(function (it) {
            const id = 'hub-opt-' + optionId++;
            return (
              '<a class="topbar-search-item" role="option" id="' +
              id +
              '" data-hub-option href="' +
              esc(it.href) +
              '" aria-selected="false">' +
              '<i class="bi ' +
              esc(it.icon || 'bi-box') +
              '" aria-hidden="true"></i>' +
              '<span><strong>' +
              esc(it.label) +
              '</strong>' +
              (it.meta
                ? '<small class="d-block text-muted">' + esc(it.meta) + '</small>'
                : '') +
              '</span></a>'
            );
          })
          .join('');
        return head + rows;
      })
      .join('');

    box.hidden = false;
    if ((q || '').trim()) setActive(0);
  }

  function fetchEntities(q) {
    if (!searchUrl || !q || q.length < 2) {
      entityHits = [];
      render(q);
      return;
    }
    fetch(searchUrl + '?q=' + encodeURIComponent(q), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        entityHits = Array.isArray(data.results) ? data.results : [];
        render(q);
      })
      .catch(function () {
        entityHits = [];
        render(q);
      });
  }

  function openActiveOrFirst() {
    const active = box.querySelector('[data-hub-option].is-active');
    const target = active || box.querySelector('[data-hub-option]');
    if (target) {
      window.location.href = target.getAttribute('href');
    }
  }

  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-expanded', 'false');
  input.setAttribute('aria-controls', box.id || 'hubSearchResults');
  if (!box.id) box.id = 'hubSearchResults';

  input.addEventListener('focus', function () {
    render(input.value);
    input.setAttribute('aria-expanded', 'true');
  });

  input.addEventListener('input', function () {
    const q = input.value;
    render(q);
    clearTimeout(timer);
    timer = setTimeout(function () {
      fetchEntities(q.trim());
    }, 220);
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.blur();
      return;
    }
    if (box.hidden) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive(activeIndex < 0 ? 0 : activeIndex + 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive(activeIndex < 0 ? 0 : activeIndex - 1);
    } else if (e.key === 'Enter') {
      if (flatItems.length) {
        e.preventDefault();
        openActiveOrFirst();
      }
    } else if (e.key === 'Home') {
      e.preventDefault();
      setActive(0);
    } else if (e.key === 'End') {
      e.preventDefault();
      setActive(box.querySelectorAll('[data-hub-option]').length - 1);
    }
  });

  box.addEventListener('mousemove', function (e) {
    const opt = e.target.closest('[data-hub-option]');
    if (!opt) return;
    const items = Array.prototype.slice.call(box.querySelectorAll('[data-hub-option]'));
    const idx = items.indexOf(opt);
    if (idx >= 0) setActive(idx);
  });

  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) {
      box.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      input.focus();
      render(input.value);
      input.setAttribute('aria-expanded', 'true');
    }
  });
})();
