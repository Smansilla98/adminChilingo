/**
 * Barra de progreso al navegar (multi-página), skeleton en filtros y entrada suave.
 * Respeta prefers-reduced-motion.
 */
(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var bar = document.getElementById('itoNavProgress');
  if (!bar) {
    bar = document.createElement('div');
    bar.id = 'itoNavProgress';
    bar.className = 'ito-nav-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);
  }

  function start() {
    if (reduce) return;
    bar.classList.remove('is-done');
    bar.classList.add('is-active');
  }

  function done() {
    bar.classList.add('is-done');
    window.setTimeout(function () {
      bar.classList.remove('is-active', 'is-done');
    }, 280);
  }

  function showTableSkeleton(wrap) {
    if (!wrap || reduce) return;
    wrap.classList.add('is-loading');
    var sk = wrap.querySelector('[data-ito-table-skeleton]');
    if (sk) {
      sk.classList.remove('d-none');
      sk.setAttribute('aria-hidden', 'false');
    }
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    if (a.target && a.target !== '_self') return;
    if (a.hasAttribute('download')) return;
    try {
      var url = new URL(a.href, window.location.href);
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
    } catch (err) {
      return;
    }
    start();
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    var method = (form.method || 'get').toLowerCase();
    if (method !== 'get' && !form.hasAttribute('data-ito-loading')) return;
    start();
    var card = form.closest('.ito-card');
    var wrap = card
      ? card.querySelector('[data-ito-table-wrap]')
      : document.querySelector('[data-ito-table-wrap]');
    showTableSkeleton(wrap);
  }, true);

  window.addEventListener('pageshow', done);
  document.addEventListener('DOMContentLoaded', function () {
    done();
    if (!reduce) {
      document.documentElement.classList.add('ito-ready');
    }
  });
})();
