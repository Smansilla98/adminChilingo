/**
 * Shell del panel: menú lateral (cajón en celular, grupos plegables, modo
 * contraído en escritorio), buscador en celular y avisos flotantes.
 */
(function () {
  var html = document.documentElement;
  var shell = document.getElementById('appShell');
  var sidebar = document.getElementById('sidebarNav');
  var backdrop = document.getElementById('navBackdrop');

  function store(key, value) {
    try {
      if (value === undefined) return localStorage.getItem(key);
      localStorage.setItem(key, value);
    } catch (e) { return null; }
    return null;
  }

  // —— Cajón (celular / tablet) ——
  function closeNav() {
    if (!shell) return;
    shell.classList.remove('shell--nav-open');
    document.body.classList.remove('shell-nav-open');
    document.querySelectorAll('[data-open-nav]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }
  function openNav() {
    if (!shell) return;
    shell.classList.add('shell--nav-open');
    document.body.classList.add('shell-nav-open');
    document.querySelectorAll('[data-open-nav]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    var first = sidebar && sidebar.querySelector('.side-link.active, .side-link');
    if (first) first.focus({ preventScroll: true });
  }
  document.querySelectorAll('[data-open-nav]').forEach(function (el) { el.addEventListener('click', openNav); });
  if (backdrop) backdrop.addEventListener('click', closeNav);
  if (sidebar) sidebar.querySelectorAll('a.side-link').forEach(function (a) { a.addEventListener('click', closeNav); });

  // —— Grupos plegables (se recuerdan; el grupo actual siempre abierto) ——
  var abiertos = [];
  try { abiertos = JSON.parse(store('ito-nav-open') || '[]'); } catch (e) { abiertos = []; }
  document.querySelectorAll('.nav-group[data-nav-group]').forEach(function (group) {
    var key = group.getAttribute('data-nav-group');
    var btn = group.querySelector('.nav-group-btn');
    if (abiertos.indexOf(key) !== -1 && !group.classList.contains('open')) {
      group.classList.add('open');
      if (btn) btn.setAttribute('aria-expanded', 'true');
    }
    if (!btn) return;
    btn.addEventListener('click', function () {
      var open = !group.classList.contains('open');
      group.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      abiertos = abiertos.filter(function (k) { return k !== key; });
      if (open) abiertos.push(key);
      store('ito-nav-open', JSON.stringify(abiertos));
    });
  });
  var activeLink = sidebar && sidebar.querySelector('.side-link.active');
  if (activeLink && activeLink.scrollIntoView) activeLink.scrollIntoView({ block: 'nearest' });

  // —— Menú contraído (escritorio) ——
  var collapseBtn = document.getElementById('sidebarCollapse');
  function syncCollapse() {
    var on = html.classList.contains('sb-collapsed');
    if (collapseBtn) {
      collapseBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      collapseBtn.setAttribute('aria-label', on ? 'Expandir menú' : 'Contraer menú');
      collapseBtn.title = on ? 'Expandir menú' : 'Contraer menú';
    }
  }
  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      html.classList.toggle('sb-collapsed');
      store('ito-sb-collapsed', html.classList.contains('sb-collapsed') ? '1' : '0');
      syncCollapse();
    });
    syncCollapse();
  }

  // —— Buscador en celular ——
  var search = document.querySelector('[data-hub-search]');
  var searchToggle = document.querySelector('[data-search-toggle]');
  function openSearch() {
    if (!search) return;
    search.classList.add('is-open');
    var input = search.querySelector('input');
    if (input) setTimeout(function () { input.focus(); }, 0);
  }
  function closeSearch() {
    if (search) search.classList.remove('is-open');
  }
  if (searchToggle) searchToggle.addEventListener('click', openSearch);
  if (search) {
    search.addEventListener('focusout', function () {
      setTimeout(function () {
        if (!search.contains(document.activeElement)) closeSearch();
      }, 120);
    });
  }
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K') && window.matchMedia('(max-width: 991.98px)').matches) {
      openSearch();
    }
    if (e.key === 'Escape') {
      closeNav();
      closeSearch();
    }
  });

  // —— Avisos flotantes ——
  var toasts = document.getElementById('itoToasts');
  function dismiss(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    setTimeout(function () { toast.remove(); }, 200);
  }
  function wire(toast) {
    var close = toast.querySelector('.ito-toast-close');
    if (close) close.addEventListener('click', function () { dismiss(toast); });
    var ms = parseInt(toast.getAttribute('data-autohide') || '0', 10);
    if (ms > 0) {
      var timer = setTimeout(function () { dismiss(toast); }, ms);
      toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
      toast.addEventListener('focusin', function () { clearTimeout(timer); });
    }
  }
  if (toasts) toasts.querySelectorAll('.ito-toast').forEach(wire);

  var iconos = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  /** API para scripts de las vistas: itoToast('Guardado', 'success') */
  window.itoToast = function (mensaje, tono) {
    if (!toasts) return;
    tono = iconos[tono] ? tono : 'info';
    var el = document.createElement('div');
    el.className = 'ito-toast ito-toast--' + tono;
    el.setAttribute('role', tono === 'danger' ? 'alert' : 'status');
    if (tono !== 'danger') el.setAttribute('data-autohide', '6000');
    el.innerHTML = '<i class="bi ' + iconos[tono] + ' ito-toast-icon" aria-hidden="true"></i><div class="ito-toast-body"></div><button type="button" class="ito-toast-close" aria-label="Cerrar aviso">&times;</button>';
    el.querySelector('.ito-toast-body').textContent = mensaje;
    toasts.appendChild(el);
    wire(el);
  };

  // —— Filtros plegables en celular ——
  document.querySelectorAll('[data-ito-filters-toggle]').forEach(function (btn) {
    var panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (!panel) return;
    btn.addEventListener('click', function () {
      var open = !panel.classList.contains('is-open');
      panel.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var first = panel.querySelector('input, select');
        if (first) first.focus();
      }
    });
  });

  // —— Botón en espera al enviar formularios POST (evita doble envío) ——
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || form.target === '_blank' || form.hasAttribute('data-no-loading')) return;
    var btn = e.submitter;
    if (!btn || !btn.classList || !btn.classList.contains('btn')) return;
    setTimeout(function () {
      if (e.defaultPrevented) return;
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
    }, 0);
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('.btn.is-loading').forEach(function (b) {
      b.classList.remove('is-loading');
      b.removeAttribute('aria-busy');
    });
  });
})();
