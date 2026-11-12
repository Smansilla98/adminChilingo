/**
 * Wizard ITO: paneles [data-ito-step], validación por paso, submit al final.
 */
(function () {
  function fieldsIn(panel) {
    return Array.prototype.slice.call(
      panel.querySelectorAll('input, select, textarea')
    ).filter(function (el) {
      return el.type !== 'hidden' && !el.disabled;
    });
  }

  function setPanelActive(root, index) {
    var panels = root.querySelectorAll('[data-ito-step]');
    var tabs = root.querySelectorAll('[data-ito-step-goto]');
    panels.forEach(function (panel) {
      var i = parseInt(panel.getAttribute('data-ito-step'), 10);
      var on = i === index;
      panel.classList.toggle('is-active', on);
      if (on) {
        panel.removeAttribute('hidden');
      } else {
        panel.setAttribute('hidden', '');
      }
      fieldsIn(panel).forEach(function (el) {
        if (!el.dataset.itoReq) {
          el.dataset.itoReq = el.required ? '1' : '0';
        }
        if (on) {
          el.required = el.dataset.itoReq === '1';
        } else {
          el.required = false;
        }
      });
    });
    tabs.forEach(function (tab) {
      var i = parseInt(tab.getAttribute('data-ito-step-goto'), 10);
      var on = i === index;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-current', on ? 'step' : 'false');
      tab.classList.toggle('is-done', i < index);
    });
    root.dataset.itoStepCurrent = String(index);
    var prev = root.querySelector('[data-ito-step-prev]');
    var next = root.querySelector('[data-ito-step-next]');
    var submit = root.querySelector('[data-ito-step-submit]');
    var last = panels.length - 1;
    if (prev) prev.hidden = index === 0;
    if (next) next.hidden = index >= last;
    if (submit) submit.hidden = index < last;
    var active = root.querySelector('[data-ito-step].is-active');
    if (active) {
      var focusable = active.querySelector('input:not([type=hidden]), select, textarea, button');
      if (focusable) {
        try { focusable.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
      }
    }
  }

  function validatePanel(panel) {
    var ok = true;
    fieldsIn(panel).forEach(function (el) {
      if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
        ok = false;
        el.reportValidity();
      }
    });
    return ok;
  }

  function init(root) {
    if (root.dataset.itoStepsReady) return;
    root.dataset.itoStepsReady = '1';
    var panels = root.querySelectorAll('[data-ito-step]');
    if (!panels.length) return;

    var start = 0;
    if (root.querySelector('.is-invalid, .invalid-feedback')) {
      panels.forEach(function (panel, idx) {
        if (panel.querySelector('.is-invalid')) start = idx;
      });
    }
    setPanelActive(root, start);

    root.querySelectorAll('[data-ito-step-goto]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = parseInt(tab.getAttribute('data-ito-step-goto'), 10);
        var current = parseInt(root.dataset.itoStepCurrent || '0', 10);
        if (target <= current) {
          setPanelActive(root, target);
          return;
        }
        for (var i = current; i < target; i++) {
          var panel = root.querySelector('[data-ito-step="' + i + '"]');
          if (panel && !validatePanel(panel)) return;
        }
        setPanelActive(root, target);
      });
    });

    var nextBtn = root.querySelector('[data-ito-step-next]');
    var prevBtn = root.querySelector('[data-ito-step-prev]');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        var current = parseInt(root.dataset.itoStepCurrent || '0', 10);
        var panel = root.querySelector('[data-ito-step="' + current + '"]');
        if (panel && !validatePanel(panel)) return;
        setPanelActive(root, current + 1);
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        var current = parseInt(root.dataset.itoStepCurrent || '0', 10);
        setPanelActive(root, Math.max(0, current - 1));
      });
    }

    var form = root.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        var panelsArr = Array.prototype.slice.call(root.querySelectorAll('[data-ito-step]'));
        for (var i = 0; i < panelsArr.length; i++) {
          fieldsIn(panelsArr[i]).forEach(function (el) {
            if (el.dataset.itoReq === '1') el.required = true;
          });
          if (!validatePanel(panelsArr[i])) {
            e.preventDefault();
            setPanelActive(root, i);
            return;
          }
        }
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-ito-form-steps]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
