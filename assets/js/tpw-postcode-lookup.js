(function(window, document){
  'use strict';

  function showWarning(input, message){
    if (!input) return;
    var holder = input.closest('.form-group') || input.parentNode;
    if (!holder) return;
    var err = holder.querySelector('.form-error');
    if (!err){
      err = document.createElement('div');
      err.className = 'form-error';
      holder.appendChild(err);
    }
    err.textContent = message || '';
  }

  function clearWarning(input){
    var holder = input && (input.closest && input.closest('.form-group')) || (input && input.parentNode) || null;
    if (!holder) return;
    var err = holder.querySelector('.form-error');
    if (err) err.textContent = '';
  }

  function ajaxLookup(postcode, country, nonce, mode, streetPrefix){
    var data = new FormData();
    data.append('action', 'tpw_lookup_postcode');
    data.append('postcode', postcode);
    data.append('country', country || 'GB');
    data.append('nonce', nonce || (window.tpwPostcode && window.tpwPostcode.nonce) || '');
    if (mode) data.append('mode', mode);
    if (streetPrefix) data.append('street_prefix', streetPrefix);

    return fetch((window.ajaxurl || (window.tpwPostcode && window.tpwPostcode.ajaxUrl)), {
      method: 'POST',
      credentials: 'same-origin',
      body: data
    }).then(function(res){ return res.json(); });
  }

  var TPWPostcodeLookup = {
    init: function(cfg){
      cfg = cfg || {};
      var pc = document.querySelector(cfg.postcodeField);
      if (!pc) return;
      var address1 = cfg.address1Field ? document.querySelector(cfg.address1Field) : null;
      var city = cfg.cityField ? document.querySelector(cfg.cityField) : null;
      var county = cfg.countyField ? document.querySelector(cfg.countyField) : null;
      var country = cfg.countryField ? document.querySelector(cfg.countryField) : null;
      var triggerBtn = cfg.buttonSelector ? document.querySelector(cfg.buttonSelector) : null;
      var provider = (cfg.provider || (window.tpwPostcode && window.tpwPostcode.provider) || '').toLowerCase();

      // Dropdown container
      var holder = pc.closest('.form-group') || pc.parentNode;
      var dropdownWrap = document.createElement('div');
      dropdownWrap.style.marginTop = '6px';
      var label = document.createElement('label');
      label.textContent = 'Select Address';
      label.style.display = 'none';
      label.style.fontWeight = '600';
      label.style.marginBottom = '4px';
      label.setAttribute('aria-live', 'polite');
      var select = document.createElement('select');
      select.style.display = 'none';
      select.className = 'tpw-address-select';
      select.innerHTML = '<option value="">-- Select Address --</option>';
      dropdownWrap.appendChild(label);
      dropdownWrap.appendChild(select);
      if (holder && holder.appendChild) holder.appendChild(dropdownWrap);

      // Simple in-memory + sessionStorage cache
      var cache = {};
      try { cache = JSON.parse(sessionStorage.getItem('tpw_pc_cache') || '{}'); } catch(e) { cache = {}; }
      function cacheSet(key, data){
        cache[key] = data;
        try { sessionStorage.setItem('tpw_pc_cache', JSON.stringify(cache)); } catch(e){}
      }
      function cacheGet(key){ return cache[key]; }

      function hideDropdown(){
        label.style.display = 'none';
        select.style.display = 'none';
        select.innerHTML = '<option value="">-- Select Address --</option>';
      }

      function showNotSupported(){
        hideDropdown();
        showWarning(pc, 'This provider does not support full address lists.');
      }

      function run(){
        var val = pc.value.trim();
        if (!val) return;
        clearWarning(pc);
        // If we support full list, use full mode; otherwise basic
  var wantsFull = cfg.enableFull !== false; // default true
  var doFull = wantsFull && (provider === 'google' || provider === 'getaddress');

        if (doFull) {
          // Use cache first
          var key = 'v2:' + (cfg.country || 'GB') + ':' + val;
          var cached = cacheGet(key);
          if (cached) {
            populateDropdown(cached);
            return;
          }
        }

        if (wantsFull && !(provider === 'google' || provider === 'getaddress')) {
          // Immediately inform about capability; proceed with basic lookup
          showNotSupported();
        }

        ajaxLookup(val, cfg.country || 'GB', cfg.nonce, doFull ? 'full' : 'basic', cfg.streetPrefix || '').then(function(resp){
          if (resp && resp.success && resp.data){
            if (doFull && resp.data.addresses && resp.data.addresses.length){
              cacheSet('v2:' + (cfg.country || 'GB') + ':' + val, resp.data.addresses);
              populateDropdown(resp.data.addresses);
              return;
            }
            hideDropdown();
            // Basic mode: populate fields with town/county
            if (city){
              var townVal = (resp.data.town || resp.data.district || '').trim();
              if (townVal) city.value = townVal;
            }
            if (county){
              var countyVal = (resp.data.county || '').trim();
              if (!countyVal) countyVal = (resp.data.region || '').trim();
              if (countyVal) county.value = countyVal;
            }
            if (address1 && resp.data.address1) { address1.value = resp.data.address1; }
            if (country && resp.data.country) { country.value = resp.data.country; }
          } else {
            hideDropdown();
            // Provider capability hint
            if (wantsFull && !(provider === 'google' || provider === 'getaddress')) {
              showNotSupported();
            } else {
              showWarning(pc, (resp && resp.message) || 'Postcode not found');
            }
          }
        }).catch(function(){
          hideDropdown();
          showWarning(pc, 'Lookup failed');
        });
      }

      function populateDropdown(addresses){
        clearWarning(pc);
        select.innerHTML = '<option value="">-- Select Address --</option>';
        addresses.forEach(function(a, idx){
          var opt = document.createElement('option');
          opt.value = String(idx);
          opt.textContent = a.label || a.address1 || '';
          opt.dataset.address = JSON.stringify(a);
          select.appendChild(opt);
        });
        label.style.display = '';
        select.style.display = '';
      }

      select.addEventListener('change', function(){
        var idx = select.value;
        if (!idx) return;
        var opt = select.options[select.selectedIndex];
        var data;
        try { data = JSON.parse(opt.dataset.address || '{}'); } catch(e) { data = null; }
        if (!data) return;
        if (address1 && data.address1) address1.value = data.address1;
        if (city && data.town) city.value = data.town;
        if (county && data.county) county.value = data.county;
        if (pc && data.postcode) pc.value = data.postcode;
        if (country && data.country) country.value = data.country;
      });

      // Reset dropdown when postcode changes
      pc.addEventListener('input', function(){ hideDropdown(); clearWarning(pc); });

      pc.addEventListener('blur', function(){
        if (!cfg.trigger || cfg.trigger === 'blur') run();
      });
      if (triggerBtn){
        triggerBtn.addEventListener('click', function(e){ e.preventDefault(); run(); });
      }
    }
  };

  window.TPWPostcodeLookup = TPWPostcodeLookup;

})(window, document);
