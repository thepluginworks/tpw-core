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
    listenersBound: false,
    mountTimer: null
  };

  // Safe, short delay to allow DOM/UI to settle before mounting Square
  var SAFE_INIT_DELAY = 50; // reduced from 150ms for faster visual response

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
    // Cancel any pending mount before unmounting
    if (state.mountTimer) {
      try { clearTimeout(state.mountTimer); } catch(e) {}
      state.mountTimer = null;
    }
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
      // Build verificationDetails for SCA/3DS per Square docs
      var verificationDetails = null;
      try {
        var amount = null;
        // Amount should be a string in major units with two decimals per Square docs
        if (state.cfg && typeof state.cfg.amount === 'string') {
          amount = state.cfg.amount;
        } else if (state.cfg && state.cfg.square && typeof state.cfg.square.amount === 'string') {
          amount = state.cfg.square.amount;
        }
        var currencyCode = (state.cfg && state.cfg.currency && state.cfg.currency.code) ? state.cfg.currency.code : 'GBP';
        var billing = (state.cfg && state.cfg.billingContact) ? state.cfg.billingContact : null;

        if (amount && currencyCode) {
          verificationDetails = {
            amount: amount,
            currencyCode: currencyCode,
            intent: 'CHARGE',
            customerInitiated: true,
            sellerKeyedIn: false
          };
          if (billing && typeof billing === 'object') {
            verificationDetails.billingContact = {
              familyName: billing.familyName || billing.lastName || undefined,
              givenName: billing.givenName || billing.firstName || undefined,
              email: billing.email || undefined,
              phone: billing.phone || undefined,
              addressLines: Array.isArray(billing.addressLines) ? billing.addressLines : (billing.address ? [billing.address] : undefined),
              city: billing.city || undefined,
              postalCode: billing.postalCode || billing.postcode || undefined,
              countryCode: billing.countryCode || 'GB'
            };
            if (billing.state) { verificationDetails.billingContact.state = billing.state; }
          }
        }
      } catch (e) {
        // If building verification details fails, continue without them
      }

      var result = verificationDetails
        ? await state.squareCard.tokenize(verificationDetails)
        : await state.squareCard.tokenize();
      if (result && result.status === 'OK' && result.token) {
        if (typeof state.nonceCallback === 'function') {
          try { state.nonceCallback(result.token); } catch(e) {}
        }
        return { ok: true, nonce: result.token };
      }
      var msg = (result && result.errors && result.errors[0] && result.errors[0].message) || ('Card tokenization failed: ' + (result && result.status ? result.status : 'UNKNOWN'));
      // Dev-only console logging to aid SCA testing
      try { console.warn('[TPW Square] tokenize status:', result && result.status, 'errors:', result && result.errors); } catch(_) {}
      showError(msg);
      return { ok: false, errors: result && result.errors ? result.errors : [{ message: msg }] };
    } catch (err) {
      try { console.error('[TPW Square] tokenize exception:', err); } catch(_) {}
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
        // Debounce and defer slightly to ensure containers are present and visible
        if (state.mountTimer) { try { clearTimeout(state.mountTimer); } catch(_) {} }
        state.mountTimer = setTimeout(async function(){
          try { await mountSquare(cfg); } catch (err) { showError(err.message || String(err)); }
          state.mountTimer = null;
        }, SAFE_INIT_DELAY);
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
      // Use a short delay to avoid racing with initial render/localization
      if (state.mountTimer) { try { clearTimeout(state.mountTimer); } catch(_) {} }
      state.mountTimer = setTimeout(async function(){
        try { await mountSquare(state.cfg); } catch (err) { showError(err.message || String(err)); }
        state.mountTimer = null;
      }, SAFE_INIT_DELAY);
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
