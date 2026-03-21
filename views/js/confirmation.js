document.addEventListener('DOMContentLoaded', function () {
  (function initAgAppMaxOrderWatcher() {
    var config = window.agappmaxConfirmation || {};
    if (!config.enabled || !config.sseUrl || typeof EventSource === 'undefined') {
      return;
    }

    var waitingEls = Array.prototype.slice.call(document.querySelectorAll('[data-agappmax-waiting]'));
    var statusEls = Array.prototype.slice.call(document.querySelectorAll('[data-agappmax-status]'));
    var approvedEls = Array.prototype.slice.call(document.querySelectorAll('[data-agappmax-approved]'));

    var setWaiting = function (visible, message, isWarning) {
      waitingEls.forEach(function (el) {
        el.style.display = visible ? '' : 'none';
        if (visible) {
          el.textContent = message || '';
          if (isWarning) {
            el.classList.add('alert-warning');
            el.classList.remove('alert-info');
          } else {
            el.classList.add('alert-info');
            el.classList.remove('alert-warning');
          }
        }
      });
    };

    var setApproved = function (visible, message) {
      approvedEls.forEach(function (el) {
        el.style.display = visible ? '' : 'none';
        if (visible && message) {
          el.textContent = message;
        }
      });
    };

    var setStatusLabel = function (label) {
      if (!label) {
        return;
      }
      statusEls.forEach(function (el) {
        el.textContent = label;
      });
    };

    var retries = 0;
    var maxRetries = 3;
    var source;

    var forceFullReload = function () {
      var url = new URL(window.location.href.split('#')[0]);
      url.searchParams.set('agappmax_refresh', Date.now().toString());
      var delay = Number.isFinite(parseInt(config.reloadDelay, 10)) ? parseInt(config.reloadDelay, 10) : 0;
      setTimeout(function () {
        window.location.replace(url.toString());
      }, Math.max(0, delay));
    };

    var onApproved = function () {
      setWaiting(false);
      setApproved(true, config.approvedMessage || 'Pagamento aprovado! Seu pedido foi confirmado.');
      setStatusLabel(config.approvedStateLabel || 'Pagamento confirmado');
      if (config.autoReload !== false) {
        forceFullReload();
      }
    };

    var connect = function () {
      setWaiting(true, config.waitingMessage, false);
      source = new EventSource(config.sseUrl);

      source.addEventListener('approved', function () {
        source.close();
        onApproved();
      });

      source.addEventListener('waiting', function () {
        setWaiting(true, config.waitingMessage, false);
      });

      source.addEventListener('timeout', function () {
        source.close();
        setWaiting(true, config.timeoutMessage || config.waitingMessage, true);
      });

      source.onerror = function () {
        if (source.readyState === EventSource.CLOSED && retries < maxRetries) {
          retries += 1;
          setTimeout(connect, 2000 * retries);
        } else {
          source.close();
        }
      };
    };

    connect();
  })();
});
