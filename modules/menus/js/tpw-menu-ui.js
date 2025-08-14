(function(){
  "use strict";

  if (window.tpwMenuUIInitialized) return; // guard against double-binding
  window.tpwMenuUIInitialized = true;

  // Keep track of the opener that launched a modal
  const openerMap = new WeakMap();

  function getFocusable(container){
    return Array.prototype.slice.call(container.querySelectorAll([
      'a[href]','area[href]','button:not([disabled])','input:not([disabled]):not([type="hidden"])',
      'select:not([disabled])','textarea:not([disabled])','iframe','object','embed',
      '[contenteditable]','[tabindex]:not([tabindex="-1"])'
    ].join(','))).filter(function(el){
      return el.offsetParent !== null || el === document.activeElement; // visible-ish
    });
  }

  function openModal(modal, opener){
    if (!modal) return;
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('tpw-modal-open');

    if (opener) openerMap.set(modal, opener);

    // Focus the first focusable element or the dialog itself
    const dialog = modal.querySelector('.tpw-modal__dialog') || modal;
    const f = getFocusable(dialog);
    (f[0] || dialog).focus();

    // Set up focus trap while open
    modal.addEventListener('keydown', trapFocus, true);
  }

  function closeModal(modal){
    if (!modal) return;
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('tpw-modal-open');

    modal.removeEventListener('keydown', trapFocus, true);

    // Restore focus to the opener if we have it
    const opener = openerMap.get(modal);
    if (opener && typeof opener.focus === 'function') {
      opener.focus();
    }
  }

  function trapFocus(e){
    if (e.key !== 'Tab') return;
    const modal = e.currentTarget; // listener is on the modal
    const dialog = modal.querySelector('.tpw-modal__dialog') || modal;
    const focusables = getFocusable(dialog);
    if (focusables.length === 0) return;

    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first){
      last.focus();
      e.preventDefault();
    } else if (!e.shiftKey && document.activeElement === last){
      first.focus();
      e.preventDefault();
    }
  }

  // Delegated click handling for open/close
  document.addEventListener('click', function(e){
    const openBtn = e.target.closest('[data-tpw-open]');
    if (openBtn){
      const sel = openBtn.getAttribute('data-tpw-open');
      if (sel){
        const modal = document.querySelector(sel);
        if (modal){
          openModal(modal, openBtn);
          e.preventDefault();
          return;
        }
      }
    }

    // Close on any element with data-tpw-close inside an open modal
    const closeBtn = e.target.closest('[data-tpw-close]');
    if (closeBtn){
      const modal = closeBtn.closest('.tpw-modal');
      if (modal){
        closeModal(modal);
        e.preventDefault();
        return;
      }
    }

    // Backdrop click (only if click is directly on the backdrop)
    const backdrop = e.target.classList.contains('tpw-modal__backdrop') ? e.target : null;
    if (backdrop){
      const modal = backdrop.closest('.tpw-modal');
      if (modal){
        closeModal(modal);
        e.preventDefault();
        return;
      }
    }
  }, false);

  // ESC to close any open modal
  document.addEventListener('keydown', function(e){
    if (e.key !== 'Escape') return;
    const openModals = document.querySelectorAll('.tpw-modal:not([hidden])');
    if (openModals.length){
      // Close the top-most (last in DOM order)
      closeModal(openModals[openModals.length - 1]);
    }
  }, false);

  // Expose small API for debugging/tests
  window.tpwMenuUI = {
    open: function(selector){ const m = document.querySelector(selector); openModal(m); },
    close: function(selector){ const m = document.querySelector(selector); closeModal(m); }
  };
})();