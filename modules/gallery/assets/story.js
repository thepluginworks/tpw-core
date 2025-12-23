(function(){
  'use strict';
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

  function Story(el){
    this.el = el;
    this.viewport = el.querySelector('.tpw-gallery-story-viewport');
    this.img = el.querySelector('.tpw-gallery-story-image');
    this.cap = el.querySelector('.tpw-gallery-story-caption');
    this.meta = el.querySelector('.tpw-gallery-story-meta');
    this.counterCurrent = el.querySelector('.tpw-gallery-story-counter .current');
    this.total = 0;
    this.index = 0;
    this.slides = [];
    this.prevBtn = el.querySelector('.tpw-gallery-story-nav.prev');
    this.nextBtn = el.querySelector('.tpw-gallery-story-nav.next');

    var dataNode = el.querySelector('.tpw-gallery-story-data');
    if (dataNode) {
      try { this.slides = JSON.parse(dataNode.textContent || '[]') || []; }
      catch(e){ this.slides = []; }
    }
    this.total = this.slides.length;

    // Preload only prev/next (avoid loading the whole gallery).
    this._preloadPrev = null;
    this._preloadNext = null;

    this.bind();
    this.update(0, false);
  }

  Story.prototype.bind = function(){
    var self = this;
    if (this.prevBtn) this.prevBtn.addEventListener('click', function(){ self.prev(); });
    if (this.nextBtn) this.nextBtn.addEventListener('click', function(){ self.next(); });

    // Keyboard arrows on focus
    this.el.addEventListener('keydown', function(ev){
      if (ev.key === 'ArrowLeft') { ev.preventDefault(); self.prev(); }
      if (ev.key === 'ArrowRight') { ev.preventDefault(); self.next(); }
    });

    // Basic swipe support
    var startX = 0, startY = 0, dx = 0, dy = 0, active = false;
    var threshold = 32; // px
    this.el.addEventListener('touchstart', function(e){
      if (!e.changedTouches || !e.changedTouches[0]) return;
      active = true; dx = dy = 0;
      startX = e.changedTouches[0].clientX;
      startY = e.changedTouches[0].clientY;
    }, {passive:true});
    this.el.addEventListener('touchmove', function(e){
      if (!active || !e.changedTouches || !e.changedTouches[0]) return;
      dx = e.changedTouches[0].clientX - startX;
      dy = e.changedTouches[0].clientY - startY;
    }, {passive:true});
    this.el.addEventListener('touchend', function(){
      if (!active) return; active = false;
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > threshold) {
        if (dx < 0) self.next(); else self.prev();
      }
    });

    // Resize observer to keep height stable (optional)
    var self = this;
    function adjust(){ self.adjustMaxHeight(); }
    window.addEventListener('resize', adjust);
    // Small debounce to avoid thrashing during resize
    var rAF;
    window.addEventListener('resize', function(){
      if (rAF) cancelAnimationFrame(rAF);
      rAF = requestAnimationFrame(adjust);
    });
  };

  Story.prototype.update = function(idx, animate){
    if (!this.slides || !this.slides.length) return;
    idx = (idx + this.slides.length) % this.slides.length;
    this.index = idx;
    var slide = this.slides[idx];
    if (!slide) return;
    if (animate) {
      this.el.classList.add('transitioning');
      var self = this;
      setTimeout(function(){ self.el && self.el.classList.remove('transitioning'); }, 180);
    }
    // Swap image and caption
    if (this.img) {
      if (slide.w && slide.h) { this.img.setAttribute('width', slide.w); this.img.setAttribute('height', slide.h); }
      this.img.src = slide.url;
      this.img.alt = slide.cap || '';
    }
    if (this.cap) this.cap.textContent = slide.cap || '';
    if (this.counterCurrent) this.counterCurrent.textContent = String(idx + 1);

    this.preloadNeighbors();

    // Update buttons disabled state
    if (this.prevBtn) this.prevBtn.disabled = (this.total <= 1);
    if (this.nextBtn) this.nextBtn.disabled = (this.total <= 1);

    // Recalculate available height so caption remains visible
    this.adjustMaxHeight();
  };

  Story.prototype.preloadNeighbors = function(){
    if (!this.slides || this.slides.length <= 1) return;
    var prevIdx = (this.index - 1 + this.slides.length) % this.slides.length;
    var nextIdx = (this.index + 1) % this.slides.length;
    var prev = this.slides[prevIdx];
    var next = this.slides[nextIdx];
    if (prev && prev.url) {
      if (!this._preloadPrev) this._preloadPrev = new Image();
      if (this._preloadPrev.src !== prev.url) this._preloadPrev.src = prev.url;
    }
    if (next && next.url) {
      if (!this._preloadNext) this._preloadNext = new Image();
      if (this._preloadNext.src !== next.url) this._preloadNext.src = next.url;
    }
  };

  Story.prototype.prev = function(){ this.update(this.index - 1, true); };
  Story.prototype.next = function(){ this.update(this.index + 1, true); };

  Story.prototype.adjustMaxHeight = function(){
    if (!this.img) return;
    var winH = window.innerHeight || document.documentElement.clientHeight || 800;
    var capH = this.cap ? this.cap.offsetHeight : 0;
    var metaH = this.meta ? this.meta.offsetHeight : 0;
    var gaps = 28; // combined vertical gaps/margins between elements
    var seventyVH = Math.round(winH * 0.70);
    var available = Math.max(120, winH - capH - metaH - gaps);
    var target = Math.max(120, Math.min(seventyVH, available));
    this.img.style.maxHeight = target + 'px';
  };

  ready(function(){
    var nodes = document.querySelectorAll('.tpw-gallery-story');
    for (var i=0; i<nodes.length; i++) new Story(nodes[i]);
  });
})();
