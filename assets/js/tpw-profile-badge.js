/* TPW Core – Profile Badge Dropdown Interaction (touch devices only)
 * Namespace: tpwCoreProfileBadge
 * Behaviour:
 * - On hover-capable devices, CSS handles dropdown (no JS).
 * - On touch devices (hover: none), first tap opens, second tap or outside tap closes.
 * - ESC closes when open.
 */
(function(){
  if (!('matchMedia' in window)) return;
  var isTouch = window.matchMedia('(hover: none)').matches;
  if (!isTouch) return; // desktop handled purely by CSS

  var badges = document.querySelectorAll('.tpw-profile-badge[data-has-dropdown="1"]');
  if (!badges.length) return;

  function closeAll(except){
    badges.forEach(function(b){
      if (except && b === except) return;
      b.classList.remove('tpw-profile-badge--open');
      var link = b.querySelector('.tpw-profile-badge__link');
      if (link) link.setAttribute('aria-expanded','false');
    });
  }

  badges.forEach(function(b){
    var link = b.querySelector('.tpw-profile-badge__link');
    if (!link) return;
    link.addEventListener('click', function(e){
      // First tap toggles; second tap closes (prevent navigation when toggling)
      var open = b.classList.toggle('tpw-profile-badge--open');
      link.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        e.preventDefault(); // consume tap to open
        closeAll(b); // close others
      } else {
        // allow navigation to profile if closing and link has profile href
        // do not preventDefault so user can navigate on second tap when already open
      }
    });
  });

  // Outside click closes
  document.addEventListener('click', function(ev){
    var inside = false;
    badges.forEach(function(b){ if (b.contains(ev.target)) inside = true; });
    if (!inside) closeAll();
  });

  // ESC key closes
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') closeAll();
  });

  // Expose namespace for potential future extension
  window.tpwCoreProfileBadge = {
    closeAll: closeAll
  };
})();
