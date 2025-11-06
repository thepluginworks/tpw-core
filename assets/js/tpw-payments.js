(function(){
  'use strict';

  function $(sel){ return document.querySelector(sel); }
  function showError(msg){
    var el = $('#tpw-square-errors');
    if (!el) return;
    el.textContent = (msg || '').toString();
    el.hidden = !msg;
    if (msg) {
      el.setAttribute('role','alert');
      el.setAttribute('aria-live','polite');
    }
  }
  function clearError(){ showError(''); }

  var state = {
    cfg: null,
    payments: null,
    squareCard: null,
    activeMethod: null,
    mounted: false,
    nonceCallback: null,
    listenersBound: false
  };

  async function ensureSquarePayments(cfg){
    if (!window.Square || !window.Square.payments) {
      throw new Error('Square Web Payments SDK not loaded. Enqueue https://web.squarecdn.com/v1/square.js before boot().');
    }
    if (!cfg || !cfg.square || !cfg.square.appId || !cfg.square.locationId) {
      throw new Error('Missing Square config (square.appId, square.locationId).');
    }
    if (state.payments) return state.payments;
    state.payments = await window.Square.payments(cfg.square.appId, cfg.square.locationId);
    return state.payments;
  }

  async function mountSquare(cfg){
    if (state.mounted && state.squareCard) return state.squareCard;
    clearError();
    var container = $('#tpw-square-container');
    if (!container) {
      throw new Error('#tpw-square-container not found');
    }
    container.hidden = false;
    var payments = await ensureSquarePayments(cfg);
    var card = await payments.card();
    await card.attach('#tpw-square-container');
    state.squareCard = card;
    state.mounted = true;
    document.dispatchEvent(new CustomEvent('tpw_square_ready'));
    return card;
  }

  function unmountSquare(){
    var container = $('#tpw-square-container');
    if (container) {
      try {
        // Detach any mounted UI by clearing container
        container.innerHTML = '';
        container.hidden = true;
      } catch(e) {}
    }
    state.squareCard = null;
    state.mounted = false;
  }

  async function tokenizeSquare(){
    if (!state.squareCard) {
      return { ok: false, errors: [{ message: 'Square is not mounted' }] };
    }
    clearError();
    try {
      var result = await state.squareCard.tokenize();
      if (result && result.status === 'OK' && result.token) {
        if (typeof state.nonceCallback === 'function') {
          try { state.nonceCallback(result.token); } catch(e) {}
        }
        return { ok: true, nonce: result.token };
      }
      var msg = (result && result.errors && result.errors[0] && result.errors[0].message) || 'Card tokenization failed';
      showError(msg);
      return { ok: false, errors: result && result.errors ? result.errors : [{ message: msg }] };
    } catch (err) {
      showError(err && err.message ? err.message : 'Payment error');
      return { ok: false, errors: [{ message: (err && err.message) || 'Payment error' }] };
    }
  }

  function bindMethodListener(cfg){
    if (state.listenersBound) return;
    document.addEventListener('tpw_payment_method_changed', async function(e){
      var method = e && e.detail && e.detail.method ? e.detail.method : '';
      state.activeMethod = method;
      if (method === 'square') {
        try { await mountSquare(cfg); } catch (err) { showError(err.message || String(err)); }
      } else {
        unmountSquare();
      }
    });
    state.listenersBound = true;
  }

  function detectInitialMethod(){
    var checked = document.querySelector('input[name="tpw_payment_method"]:checked');
    return checked ? checked.value : '';
  }

  async function boot(cfg){
    state.cfg = cfg || {};
    bindMethodListener(state.cfg);
    // Mount immediately if Square is already selected
    state.activeMethod = detectInitialMethod();
    if (state.activeMethod === 'square') {
      try { await mountSquare(state.cfg); } catch (err) { showError(err.message || String(err)); }
    } else {
      unmountSquare();
    }

    return {
      method: state.activeMethod || 'none',
      tokenize: tokenizeSquare,
      onNonce: function(cb){ state.nonceCallback = (typeof cb === 'function') ? cb : null; },
      unmount: unmountSquare,
      getSquareCard: function(){ return state.squareCard; }
    };
  }

  // Expose global
  if (!window.TPW_Core_Payments) {
    window.TPW_Core_Payments = {};
  }
  window.TPW_Core_Payments.boot = boot;
})();
