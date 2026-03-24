(function(window, document){
  'use strict';

  // Shared postcode lookup controller for TPW forms
  // Usage: TPWCorePostcode.bind({
  //   form: '#formId',
  //   postcode: 'input[name=postcode]',
  //   lookupBtn: '[data-role=postcode-lookup]', // optional; if omitted, will run on blur
  //   selectWrap: '[data-role=postcode-address-select]', // container with a <select>
  //   messageBox: '[data-role=postcode-message]',
  //   fields: {
  //     line1: 'input[name=address1]', line2: 'input[name=address2]',
  //     city: 'input[name=town]', county: 'input[name=county]', country: 'input[name=country]',
  //     lat: 'input[name=lat]', lng: 'input[name=lng]'
  //   },
  //   countryDefault: 'GB',
  //   streetPrefixFrom: 'input[name=address1]' // optional: include current Address 1 as street_prefix
  // })

  function $(root, sel){ return root ? root.querySelector(sel) : null; }
  function runtimeConfig(){ return window.tpwCorePostcode || {}; }
  function normalizeLookupCountry(value, fallback){
    var raw = (value || fallback || 'GB');
    var compact = String(raw).trim().toUpperCase().replace(/\s+/g, ' ');
    if (!compact) return (fallback || 'GB');
    if (compact === 'GB' || compact === 'UK' || compact === 'GBR' || compact === 'UNITED KINGDOM' || compact === 'GREAT BRITAIN' || compact === 'ENGLAND' || compact === 'SCOTLAND' || compact === 'WALES' || compact === 'NORTHERN IRELAND') {
      return 'GB';
    }
    return compact;
  }
  function ajaxUrl(){ return (window.tpwCorePostcode && window.tpwCorePostcode.ajaxUrl) || window.ajaxurl || (window.tpwPostcode && window.tpwPostcode.ajaxUrl) || '';
  }
  function nonce(){ return (window.tpwCorePostcode && window.tpwCorePostcode.nonce) || (window.tpwPostcode && window.tpwPostcode.nonce) || '';
  }

  function postLookup(data){
    return fetch(ajaxUrl(), { method:'POST', credentials:'same-origin', body: data })
      .then(function(r){ return r.json(); });
  }

  function extractList(result){
    if (!result) return [];
    if (Array.isArray(result.addresses)) return result.addresses;
    if (result.data && Array.isArray(result.data.addresses)) return result.data.addresses;
    if (Array.isArray(result.results)) return result.results;
    if (result.data && Array.isArray(result.data.results)) return result.data.results;
    if (Array.isArray(result.suggestions)) return result.suggestions;
    if (result.data && Array.isArray(result.data.suggestions)) return result.data.suggestions;
    return [];
  }

  function normalize(addr, fallback){
    fallback = fallback || {};
    if (!addr || typeof addr === 'string') {
      // Minimal parse for plain Strings (label)
      return {
        line1: fallback.line1 || '',
        line2: '',
        city: fallback.city || '',
        county: '',
        postcode: fallback.postcode || '',
        country: fallback.country || 'GB',
        lat: '', lng: ''
      };
    }
    var line1 = addr.line1 || addr.address1 || addr.address_line_1 || '';
    if (!line1) {
      var number = addr.building_number || addr.house_number || addr.premise || addr.sub_building_name || '';
      var street = addr.route || addr.street || addr.thoroughfare || addr.road || '';
      line1 = [number, street].filter(Boolean).join(' ');
    }
    return {
      line1: line1,
      line2: addr.line2 || addr.address_line_2 || addr.sub_locality || addr.dependent_locality || '',
      city: addr.town || addr.city || addr.post_town || addr.locality || addr.postal_town || fallback.city || '',
      county: addr.county || addr.administrative_area || addr.region || addr.county_name || '',
      postcode: addr.postcode || addr.postal_code || addr.post_code || fallback.postcode || '',
      country: addr.country || addr.countryCode || addr.country_code || fallback.country || 'GB',
      lat: addr.latitude || addr.lat || (addr.geometry && (addr.geometry.lat || (addr.geometry.location && addr.geometry.location.lat))) || '',
      lng: addr.longitude || addr.lng || (addr.geometry && (addr.geometry.lng || (addr.geometry.location && addr.geometry.location.lng))) || ''
    };
  }

  function bind(config){
    config = config || {};
    if (!runtimeConfig().enabled) return;
    var scope = document;
    var form = typeof config.form === 'string' ? $(scope, config.form) : (config.form || scope);
    if (!form) return;
    var pc = $(form, config.postcode);
    var lookupBtn = config.lookupBtn ? $(form, config.lookupBtn) : null;
    var selWrap = config.selectWrap ? $(form, config.selectWrap) : null;
    var sel = selWrap ? selWrap.querySelector('select') : null;
    var msg = config.messageBox ? $(form, config.messageBox) : null;
    var fields = config.fields || {};
    var f = {
      line1: $(form, fields.line1), line2: $(form, fields.line2),
      city: $(form, fields.city), county: $(form, fields.county), country: $(form, fields.country),
      lat: $(form, fields.lat), lng: $(form, fields.lng)
    };
    var countryDefault = config.countryDefault || 'GB';
    var supportsFull = !!runtimeConfig().supportsFull;

    function showMsg(text){ if(!msg) return; msg.textContent = text||''; msg.style.display = text ? 'block' : 'none'; }
    function hideSelect(){ if (selWrap) selWrap.style.display = 'none'; if (sel) sel.innerHTML = ''; }
    function populateSelect(list){ if (!sel || !selWrap) return; sel.innerHTML = '<option value="">-- Select Address --</option>';
      list.forEach(function(a, i){ var o = document.createElement('option'); o.value=String(i); o.textContent = a.label || a.summary || a.address1 || a.line1 || ''; o.dataset.address = JSON.stringify(a); sel.appendChild(o); });
      selWrap.style.display = 'block'; }
    function apply(data){ if (f.line1) f.line1.value = data.line1 || ''; if (f.line2) f.line2.value = data.line2 || '';
      if (f.city) f.city.value = data.city || ''; if (f.county) f.county.value = data.county || ''; if (f.country) f.country.value = data.country || countryDefault; if (pc) pc.value = data.postcode || (pc.value||''); if (f.lat) f.lat.value = data.lat || ''; if (f.lng) f.lng.value = data.lng || ''; }

    function doLookup(){
      if (!pc || !pc.value) { showMsg('Enter a postcode to search.'); return; }
      var code = (pc.value||'').toUpperCase().replace(/\s+/g, ' ');
      var lookupCountry = normalizeLookupCountry((f.country && f.country.value) ? f.country.value : '', countryDefault);
      hideSelect(); showMsg('Looking up address...');
      var fd = new FormData();
      fd.append('action','tpw_lookup_postcode');
      fd.append('postcode', code);
      fd.append('country', lookupCountry);
      fd.append('nonce', nonce());
      fd.append('mode', supportsFull ? 'full' : 'basic');
      if (config.streetPrefixFrom) {
        var sp = $(form, config.streetPrefixFrom); if (sp && sp.value) fd.append('street_prefix', sp.value.trim());
      }
      postLookup(fd).then(function(res){
        if (res && res.success && res.data){
          var list = extractList(res.data);
          if (list && list.length){ showMsg(''); populateSelect(list); // also apply first result as a convenience
            try { apply(normalize(list[0], { postcode: code, country: countryDefault })); } catch(e){}
            return; }
          // basic fallback
          var d = res.data || {}; showMsg('');
          apply({ line1: f.line1 && f.line1.value || '', line2:'', city: d.town || d.city || d.district || '', county: d.county || d.region || '', postcode: d.postcode || code, country: d.country || countryDefault, lat: d.latitude || d.lat || '', lng: d.longitude || d.lng || '' });
        } else {
          hideSelect();
          showMsg((res && (res.message || (res.data && res.data.message))) || 'No addresses found for this postcode.');
        }
      }).catch(function(){ hideSelect(); showMsg('There was a problem looking up this postcode.'); });
    }

    if (sel) { sel.addEventListener('change', function(){ var opt = sel.options[sel.selectedIndex]; if (!opt || !opt.dataset.address) return; try { var data = JSON.parse(opt.dataset.address); apply(normalize(data, { postcode: pc && pc.value || '', country: countryDefault })); } catch(e){} }); }
    if (lookupBtn) { lookupBtn.addEventListener('click', function(e){ e.preventDefault(); doLookup(); }); }
    if (pc && !lookupBtn) { pc.addEventListener('blur', doLookup); }
  }

  window.TPWCorePostcode = { bind: bind };
})(window, document);
