(function($){

  // Enhanced lightbox with captions and prev/next navigation
  var state = {
    group: null,
    links: [],
    index: -1
  };

  function ensure(){
    if ($('#tpw-gallery-lb').length) return;
    $('body').append(
      '<div id="tpw-gallery-lb" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:99999">\
        <button class="tpw-lb-close" style="position:absolute;top:12px;right:12px" aria-label="Close">✕</button>\
        <button class="tpw-lb-prev" aria-label="Previous" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);">‹</button>\
        <button class="tpw-lb-next" aria-label="Next" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);">›</button>\
        <div class="tpw-lb-inner" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);max-width:90vw;max-height:85vh">\
          <img class="tpw-lb-img" src="" alt="" style="max-width:100%;max-height:85vh;display:block;margin:0 auto"/>\
          <div class="tpw-lb-cap" style="color:#fff;text-align:center;margin-top:8px"></div>\
        </div>\
      </div>'
    );
    // Bind touch/pointer listeners immediately and signal readiness
    attachTouchListeners();
    $(document).trigger('tpw:lightbox:ready');
  }

  function setFromIndex(){
    if (state.index < 0) return;
    var $a = $(state.links[state.index]);
    var url = $a.attr('href');
    var cap = $a.data('caption') || '';
    // Force-load the image when opening lightbox, irrespective of lazy state
    $('#tpw-gallery-lb .tpw-lb-img').attr({ src: url, alt: cap });
    $('#tpw-gallery-lb .tpw-lb-cap').text(cap);
    // Toggle nav visibility at edges
    $('#tpw-gallery-lb .tpw-lb-prev').toggle(state.index > 0);
    $('#tpw-gallery-lb .tpw-lb-next').toggle(state.index < state.links.length - 1);
  }

  function openFrom($link){
    ensure();
    var group = $link.data('group') || null;
    state.group = group;
    state.links = [];
    if (group) {
      $('a.tpw-gallery-lightbox[data-group="' + group + '"]').each(function(){ state.links.push(this); });
    } else {
      state.links.push($link.get(0));
    }
    state.index = Math.max(0, state.links.indexOf($link.get(0)));
    setFromIndex();
    $('html, body').addClass('tpw-lb-open');
    $('#tpw-gallery-lb').fadeIn(100);
  }

  function close(){ $('#tpw-gallery-lb').fadeOut(100, function(){ $('html, body').removeClass('tpw-lb-open'); }); }

  // Open
  $(document).on('click', 'a.tpw-gallery-lightbox', function(e){
    e.preventDefault();
    openFrom($(this));
  });

  // Close behavior
  $(document).on('click', '#tpw-gallery-lb, #tpw-gallery-lb .tpw-lb-close', function(e){
    if (e.target.id==='tpw-gallery-lb' || $(e.target).hasClass('tpw-lb-close')) close();
  });
  $(document).on('keydown', function(e){
    if (!$('#tpw-gallery-lb').is(':visible')) return;
    if (e.key==='Escape') return close();
    if (e.key==='ArrowLeft' && state.index > 0) { state.index--; setFromIndex(); }
    if (e.key==='ArrowRight' && state.index < state.links.length - 1) { state.index++; setFromIndex(); }
  });

  // Prev/Next clicks
  $(document).on('click', '#tpw-gallery-lb .tpw-lb-prev', function(e){ e.stopPropagation(); if (state.index > 0) { state.index--; setFromIndex(); } });
  $(document).on('click', '#tpw-gallery-lb .tpw-lb-next', function(e){ e.stopPropagation(); if (state.index < state.links.length - 1) { state.index++; setFromIndex(); } });

  // Touch swipe (left/right) to navigate — native listeners to control passive behavior
  var touchStartX = 0, touchStartY = 0, touchActive = false;
  var SWIPE_X_THRESHOLD = 30; // px
  function onTouchStart(ev){
    var t = (ev.touches && ev.touches[0]) || (ev.originalEvent && ev.originalEvent.touches && ev.originalEvent.touches[0]);
    if (!t) return;
    touchActive = true;
    touchStartX = t.clientX;
    touchStartY = t.clientY;
  }
  function onTouchMove(ev){
    if (!touchActive) return;
    var t = (ev.touches && ev.touches[0]) || (ev.originalEvent && ev.originalEvent.touches && ev.originalEvent.touches[0]);
    if (!t) return;
    var dx = t.clientX - touchStartX;
    var dy = t.clientY - touchStartY;
    if (Math.abs(dx) > Math.abs(dy)) {
      // prevent page scroll during horizontal swipe
      if (ev && ev.cancelable && typeof ev.preventDefault === 'function') ev.preventDefault();
    }
  }
  function onTouchEnd(ev){
    if (!touchActive) return;
    touchActive = false;
    var t = (ev.changedTouches && ev.changedTouches[0]) || (ev.originalEvent && ev.originalEvent.changedTouches && ev.originalEvent.changedTouches[0]);
    if (!t) return;
    var dx = t.clientX - touchStartX;
    var dy = t.clientY - touchStartY;
    if (Math.abs(dx) > SWIPE_X_THRESHOLD && Math.abs(dy) < 80) {
      if (dx > 0 && state.index > 0) { state.index--; setFromIndex(); }
      else if (dx < 0 && state.index < state.links.length - 1) { state.index++; setFromIndex(); }
    }
  }

  function attachTouchListeners(){
    var el = document.getElementById('tpw-gallery-lb');
    if (!el || el.__tpwTouchBound) return;
    el.__tpwTouchBound = true;
    if (window.PointerEvent) {
      var pointerId = null, pType = null;
      function onPointerDown(e){ if (e.pointerType === 'mouse') return; pointerId = e.pointerId; pType = e.pointerType; touchActive = true; touchStartX = e.clientX; touchStartY = e.clientY; }
      function onPointerMove(e){ if (!touchActive || e.pointerId !== pointerId) return; var dx = e.clientX - touchStartX; var dy = e.clientY - touchStartY; if (Math.abs(dx) > Math.abs(dy)) e.preventDefault(); }
      function onPointerUp(e){ if (!touchActive || e.pointerId !== pointerId) return; touchActive = false; var dx = e.clientX - touchStartX; var dy = e.clientY - touchStartY; if (Math.abs(dx) > SWIPE_X_THRESHOLD && Math.abs(dy) < 80) { if (dx > 0 && state.index > 0) { state.index--; setFromIndex(); } else if (dx < 0 && state.index < state.links.length - 1) { state.index++; setFromIndex(); } } pointerId = null; pType = null; }
      function onPointerCancel(){ touchActive = false; pointerId = null; pType = null; }
      el.addEventListener('pointerdown', onPointerDown, { passive: true });
      el.addEventListener('pointermove', onPointerMove, { passive: false });
      el.addEventListener('pointerup', onPointerUp, { passive: true });
      el.addEventListener('pointercancel', onPointerCancel, { passive: true });
    } else {
      // Use passive: false for touchmove so preventDefault works
      el.addEventListener('touchstart', onTouchStart, { passive: true });
      el.addEventListener('touchmove', onTouchMove, { passive: false });
      el.addEventListener('touchend', onTouchEnd, { passive: true });
    }
    // Prevent horizontal wheel/trackpad scroll from bubbling in overlay
    el.addEventListener('wheel', function(e){
      if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
        e.preventDefault();
      }
    }, { passive: false });
  }

  // Ensure touch listeners are bound when lightbox DOM is created
  $(document).on('tpw:lightbox:ready', attachTouchListeners);
})(jQuery);

// Phase 8: Portrait detection for grid thumbnails
(function(){
  var didScan = false;
  function markPortrait(img){
    try {
      if (!img || !img.naturalWidth || !img.naturalHeight) return;
      if (img.naturalHeight > img.naturalWidth) {
        var card = img.closest('.tpw-gallery-item');
        if (card) card.classList.add('portrait');
      }
    } catch(e){}
  }
  function scan(){
    if (didScan) return; didScan = true;
  document.querySelectorAll('.tpw-gallery-grid .tpw-gallery-item img, .tpw-gallery-grid-public .tpw-gallery-item img').forEach(function(img){
      if (img.complete) markPortrait(img);
      else img.addEventListener('load', function(){ markPortrait(img); }, { once: true });
      // Optional diagnostics: log computed object-position after a tick
      setTimeout(function(){
        try {
          var cs = window.getComputedStyle(img);
          if (cs && cs.objectPosition) {
            // Uncomment next line to enable diagnostics
            // console.debug('[TPW Gallery] object-position', cs.objectPosition, img.src);
          }
        } catch(e){}
      }, 50);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
  else scan();
})();
