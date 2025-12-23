(function($){
  // Admin interactions. Assumes jQuery.
  const AJAX_URL = (window.tpwGallery && window.tpwGallery.ajaxurl) ? window.tpwGallery.ajaxurl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
  const ajax = (action, data) => $.post(AJAX_URL, Object.assign({ action }, data));

  // Modal helpers
  const $modal = $('#tpw-gallery-modal');
  const openModal = (title) => {
    $('#tpw-gallery-modal-title').text(title||'');
    $modal.show().attr('aria-hidden', 'false');
    $('html, body').addClass('tpw-modal-open');
  };
  const closeModal = () => {
    $modal.hide().attr('aria-hidden', 'true');
    $('html, body').removeClass('tpw-modal-open');
  };

  // Add Gallery -> open modal with clean form
  $(document).on('click', '#tpw-add-gallery-btn', function(e){
    // Fallback allowed: if JS disabled, link navigates to #add
    e.preventDefault();
    // Reset form to add-state
    const $form = $('#tpw-gallery-form');
    if ($form.length) {
      $form[0].reset();
      $form.find('input[name="gallery_id"]').val('0');
    }
    openModal(tpwGallery.i18nAddTitle || 'Add Gallery');
  });

  // Quick Edit -> open modal with populated form (support legacy class too)
  $(document).on('click', '.tpw-gallery-quick-edit, .tpw-gallery-edit', function(e){
    e.preventDefault();
    const id = parseInt($(this).data('id')||0,10);
    if (!id) return;
    // Fetch gallery payload to prefill (reuse public API via a lightweight admin endpoint)
    ajax('tpw_gallery_get', { id, _wpnonce: tpwGallery.nonce }).done(resp => {
      if (!resp || !resp.success || !resp.data) { alert((resp&&resp.data)||'Error'); return; }
      const g = resp.data.gallery || {};
      const $form = $('#tpw-gallery-form');
      $form.find('input[name="gallery_id"]').val(g.gallery_id||0);
      $form.find('input[name="title"]').val(g.title||'');
      $form.find('textarea[name="description"]').val(g.description||'');
      $form.find('select[name="category_id"]').val(parseInt(g.category_id||0,10));

      // Populate thumbnails list
      const images = Array.isArray(resp.data.images) ? resp.data.images : [];
      const $ul = $('#tpw-gallery-thumbs');
      $ul.empty();
      if (images.length === 0) {
        $ul.append('<li class="tpw-thumb-empty" style="opacity:.7">' + (tpwGallery.i18nNoImages||'No images in this gallery yet.') + '</li>');
      } else {
        images.forEach(img => {
          const full = img.url || '';
          const thumb = img.thumb_url || full;
          const cap = img.caption || '';
          const fx = (typeof img.focus_x === 'number') ? img.focus_x : null;
          const fy = (typeof img.focus_y === 'number') ? img.focus_y : null;
          const li = $('<li/>', { 'data-image-id': img.image_id });
          if (full) {
            // In admin, disable lightbox interaction on thumbnails
            if (tpwGallery.isAdmin) {
              const $thumb = $('<div/>', { 'class': 'tpw-thumb tpw-thumb--natural', css: { position: 'relative' } }).append(
                $('<img/>', { src: thumb, alt: cap, css: { width:'100%', height:'auto' } })
              );
              // Focal point dot (reference only; editing opens modal)
              const $fp = $('<div/>', { 'class':'tpw-focal-handle', title: (tpwGallery.i18nFocusHelp||'Click image to edit focal point') })
                .css({ position:'absolute', width:'12px', height:'12px', borderRadius:'50%', background:'#10b981', border:'2px solid #fff', boxShadow:'0 1px 3px rgba(0,0,0,.25)', transform:'translate(-50%,-50%)', pointerEvents:'none', zIndex:2, display:'block' });
              // Default to center if not set
              const _fx = (fx !== null ? fx : 0.5);
              const _fy = (fy !== null ? fy : 0.5);
              $fp.css({ left: (_fx*100)+'%', top: (_fy*100)+'%' });
              $thumb.append($fp);
              li.append($thumb);
            } else {
              li.append(
                $('<a/>', { href: full, 'class': 'tpw-gallery-lightbox tpw-thumb', 'data-caption': cap }).append(
                  $('<img/>', { src: thumb, alt: cap })
                )
              );
            }
          } else {
            li.append($('<div/>', { 'class': 'tpw-thumb-placeholder', text: '#' }));
          }
          // Caption display + edit controls (admin only)
          if (tpwGallery.isAdmin) {
            const capWrap = $('<div/>', { 'class': 'tpw-cap-wrap', style: 'width:100%;margin-top:6px;' });
            const capRow  = $('<div/>', { 'class':'tpw-row', style:'justify-content:space-between;align-items:center;gap:6px;' });
            const capText = $('<div/>', { 'class': 'tpw-cap-text', text: cap || '', tabindex: 0, title: tpwGallery.i18nEditCaption||'Edit caption' });
            capRow.append(capText);
            capWrap.append(capRow);
            // Hidden editor row
            const editor = $('<div/>', { 'class': 'tpw-cap-editor', style: 'display:none;gap:6px;margin-top:6px;' });
            const input = $('<input/>', { type: 'text', value: cap||'', 'class':'tpw-cap-input', style:'flex:1 1 auto;' });
            const save = $('<button/>', { type: 'button', 'class':'tpw-btn tpw-btn-small tpw-btn-primary tpw-cap-save' }).text(tpwGallery.i18nSave||'Save');
            const cancel = $('<button/>', { type: 'button', 'class':'tpw-btn tpw-btn-small tpw-btn-secondary tpw-cap-cancel' }).text(tpwGallery.i18nCancel||'Cancel');
            editor.append(input, save, cancel);
            capWrap.append(editor);
            li.append(capWrap);
          }
          // Actions row: Remove (unlink) + Delete (destructive)
          const actions = $('<div/>', { 'class': 'tpw-row tpw-gallery-actions', 'style': 'gap:8px; width:100%;' });
          actions.append(
            $('<button/>', {
              type: 'button',
              'class': 'tpw-btn tpw-btn-small tpw-btn-secondary tpw-gallery-remove-image',
              'data-image-id': img.image_id
            }).text(tpwGallery.i18nRemove || 'Remove')
          );
          actions.append(
            $('<button/>', {
              type: 'button',
              'class': 'tpw-btn tpw-btn-small tpw-btn-danger tpw-gallery-remove-image-perm',
              'data-image-id': img.image_id
            }).text(tpwGallery.i18nDeletePermShort || 'Delete')
          );
          li.append(actions);
          $ul.append(li);
        });
      }
      // Open modal first, then enable drag-and-drop sorting
      openModal(tpwGallery.i18nEditTitle || 'Edit Gallery');
      initThumbSortable();
    });
  });

  // Close controls (button, overlay, ESC)
  $(document).on('click', '#tpw-gallery-modal-close,[data-tpw-modal-close]', function(e){
    e.preventDefault();
    closeModal();
  });
  $(document).on('keyup', function(e){ if (e.key === 'Escape') closeModal(); });

  // Form submission (create or update)
  $(document).on('submit', '#tpw-gallery-form', function(e){
    e.preventDefault();
    const $form = $(this);
    const payload = {
      title: $.trim($form.find('input[name="title"]').val()||''),
      description: $.trim($form.find('textarea[name="description"]').val()||''),
      category_id: parseInt($form.find('select[name="category_id"]').val()||0,10),
      _wpnonce: tpwGallery.nonce
    };
    const gid = parseInt($form.find('input[name="gallery_id"]').val()||0,10);
    const action = gid > 0 ? 'tpw_gallery_update' : 'tpw_gallery_create';
    if (gid > 0) payload.gallery_id = gid;
    ajax(action, payload).done(resp => {
      if (resp && resp.success) {
        if (tpwGallery.isEditorPage) {
          // Inline toast message on editor page
          const $title = $('#tpw-gallery-modal-title');
          const host = $title.length ? $title : $('.tpw-admin-header h1').first();
          const $toast = $('<span/>', { text: ' ' + (tpwGallery.i18nSaved||'Saved'), style:'margin-left:8px;color:#2e7d32;font-size:.9em;' });
          host.after($toast); setTimeout(()=> $toast.fadeOut(200,()=> $toast.remove()), 1500);
        } else {
          closeModal();
          // Refresh to update list and counts
          location.reload();
        }
      } else {
        alert(resp && resp.data ? resp.data : 'Error');
      }
    });
  });

  // Deletion remains as-is
  $(document).on('click', '.tpw-gallery-delete', function(){
    const id = parseInt($(this).data('id')||0,10);
    if (!id) return;
    if (!confirm(tpwGallery.i18nConfirmDelete||'Delete this gallery?')) return;
    ajax('tpw_gallery_delete', { id, _wpnonce: tpwGallery.nonce }).done(resp => {
      if (resp && resp.success) location.reload();
      else alert(resp && resp.data ? resp.data : 'Error');
    });
  });

  // Add Images dropdown
  const closeAddMenu = () => { $('#tpw-add-images-menu').hide().attr('aria-hidden','true'); $('#tpw-add-images-menu-btn').attr('aria-expanded','false'); };
  const openAddMenu = () => { $('#tpw-add-images-menu').show().attr('aria-hidden','false'); $('#tpw-add-images-menu-btn').attr('aria-expanded','true'); };
  $(document).on('click', '#tpw-add-images-menu-btn', function(e){ e.preventDefault(); const $m = $('#tpw-add-images-menu'); $m.is(':visible') ? closeAddMenu() : openAddMenu(); });
  $(document).on('click', function(e){ const $m = $('#tpw-add-images-menu'); const $btn = $('#tpw-add-images-menu-btn'); if (!$m.is(e.target) && !$btn.is(e.target) && $m.has(e.target).length===0 && $btn.has(e.target).length===0) closeAddMenu(); });
  $(document).on('keyup', function(e){ if (e.key==='Escape') closeAddMenu(); });

  // Handle selected files: upload to gallery folder via AJAX (one by one)
  $(document).on('change', '#tpw-gallery-file', function(){
    const files = this.files; if (!files || !files.length) return;
    let gid = parseInt($('input[name="gallery_id"]').val()||0,10);
    const $ul = $('#tpw-gallery-thumbs');
    if ($ul.find('.tpw-thumb-empty').length) $ul.empty();

    // Ensure gallery exists: auto-create if needed
    const ensureGallery = () => new Promise((resolve, reject) => {
      if (gid > 0) return resolve(gid);
      const $form = $('#tpw-gallery-form');
      const payload = {
        title: $.trim($form.find('input[name="title"]').val()||''),
        description: $.trim($form.find('textarea[name="description"]').val()||''),
        category_id: parseInt($form.find('select[name="category_id"]').val()||0,10),
        _wpnonce: tpwGallery.nonce
      };
      if (!payload.title) { payload.title = 'Untitled Gallery'; }
      ajax('tpw_gallery_create', payload).done(resp => {
        if (resp && resp.success && resp.data && resp.data.gallery_id){
          gid = parseInt(resp.data.gallery_id, 10);
          $('input[name="gallery_id"]').val(gid);
          // Toast
          const $title = $('#tpw-gallery-modal-title');
          const $toast = $('<span/>', { text: ' ' + (tpwGallery.i18nAutoSavedBeforeUpload || 'Gallery saved automatically before upload.'), style:'margin-left:8px;color:#2e7d32;font-size:.85em;' });
          $title.after($toast);
          setTimeout(()=> $toast.fadeOut(200,()=> $toast.remove()), 2000);
          resolve(gid);
        } else {
          reject(resp && resp.data ? resp.data : 'Error creating gallery');
        }
      }).fail(() => reject('Error creating gallery'));
    });

    ensureGallery().then(()=>{
    const uploadOne = (idx) => {
      if (idx >= files.length) { return; }
      // Insert a temporary uploading placeholder
      const fname = files[idx].name || '';
      const $placeholder = $('<li/>', { 'class':'tpw-uploading' }).append(
        $('<div/>', { 'class':'tpw-thumb' }).append(
          $('<div/>', { 'class':'tpw-thumb-placeholder', text: '…' })
        ),
        $('<div/>', { 'class':'tpw-upload-label', text: (tpwGallery.i18nUploading || 'Uploading...') + (fname ? ' ' + fname : '') })
      );
      $ul.append($placeholder);
      const fd = new FormData();
      fd.append('action', 'tpw_gallery_upload_image');
      fd.append('_wpnonce', tpwGallery.nonce);
      fd.append('gallery_id', gid);
      fd.append('file', files[idx]);
      $.ajax({ url: AJAX_URL, method: 'POST', data: fd, processData: false, contentType: false }).done(resp => {
        if (resp && resp.success && resp.data) {
          const rec = resp.data;
          const full = rec.url || '';
          const thumb = rec.thumb_url || full;
          const cap = rec.caption || '';
          const li = $('<li/>', { 'data-image-id': rec.image_id });
          if (tpwGallery.isEditorPage) {
            const $card = $('<div/>', { 'class': 'tpw-card tpw-gallery-card' });
            const $media = $('<div/>', { 'class': 'tpw-card__media' });
            if (full) {
              const fxp = (typeof rec.focus_x === 'number') ? Math.max(0, Math.min(100, Math.round(rec.focus_x * 100))) : 50;
              const fyp = (typeof rec.focus_y === 'number') ? Math.max(0, Math.min(100, Math.round(rec.focus_y * 100))) : 50;
              const $a = $('<a/>', { href: full, 'class': 'tpw-gallery-lightbox tpw-thumb', 'data-caption': cap });
              // Apply focal custom properties so object-position centers correctly
              $a.css('--focus-x', fxp + '%').css('--focus-y', fyp + '%');
              const $img = $('<img/>', { src: thumb, alt: cap });
              // Inline object-position to ensure focal is honored
              $img.css('object-fit', 'cover').css('object-position', 'var(--focus-x, 50%) var(--focus-y, 50%)');
              $a.append($img);
              $media.append($a);
            } else {
              $media.append($('<div/>', { 'class': 'tpw-thumb-placeholder', text: '#' }));
            }
            // Caption editor block (editor page)
            const $capWrap = $('<div/>', { 'class': 'tpw-cap-wrap', style: 'width:100%;margin-top:4px;' });
            const $capRow  = $('<div/>', { 'class': 'tpw-row', style: 'justify-content:space-between;align-items:center;gap:6px;' });
            const $capText = $('<div/>', { 'class': 'tpw-cap-text', text: cap || '', tabindex: 0, title: tpwGallery.i18nEditCaption||'Edit caption' });
            $capRow.append($capText);
            const $capEditor = $('<div/>', { 'class':'tpw-cap-editor', style:'display:none;gap:6px;margin-top:6px;' });
            const $capInput  = $('<input/>', { type:'text', 'class':'tpw-cap-input', value: cap||'', style:'flex:1 1 auto;' });
            const $capSave   = $('<button/>', { type:'button', 'class':'tpw-btn tpw-btn-small tpw-btn-primary tpw-cap-save' }).text(tpwGallery.i18nSave||'Save');
            const $capCancel = $('<button/>', { type:'button', 'class':'tpw-btn tpw-btn-small tpw-btn-secondary tpw-cap-cancel' }).text(tpwGallery.i18nCancel||'Cancel');
            $capEditor.append($capInput, $capSave, $capCancel);
            $capWrap.append($capRow, $capEditor);

            const $footer = $('<div/>', { 'class': 'tpw-card__footer' });
            const $actionsMain = $('<div/>', { 'class': 'tpw-row tpw-gallery-actions tpw-gallery-actions--main', 'style': 'gap:6px; width:100%;' });
            $actionsMain
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-secondary tpw-gallery-focal', 'data-image-id': rec.image_id }).text('Focal'))
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-secondary tpw-gallery-remove-image', 'data-image-id': rec.image_id }).text(tpwGallery.i18nRemove||'Remove'));
            const $actionsDanger = $('<div/>', { 'class': 'tpw-row tpw-gallery-actions tpw-gallery-actions--danger', 'style': 'gap:6px; width:100%;' });
            $actionsDanger
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-danger tpw-gallery-remove-image-perm', 'data-image-id': rec.image_id }).text(tpwGallery.i18nDelete||tpwGallery.i18nDeletePermShort||'Delete'));
            $footer.append($actionsMain).append($actionsDanger);
            $card.append($media).append($capWrap).append($footer);
            li.append($card);
          } else {
            // Modal quick-edit layout
            if (full) {
              li.append(
                $('<div/>', { 'class': 'tpw-thumb' }).append(
                  $('<img/>', { src: thumb, alt: cap })
                )
              );
            } else {
              li.append($('<div/>', { 'class': 'tpw-thumb-placeholder', text: '#' }));
            }
            const $actions = $('<div/>', { 'class': 'tpw-row tpw-gallery-actions', 'style': 'gap:8px; width:100%;' });
            $actions
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-secondary tpw-gallery-focal', 'data-image-id': rec.image_id }).text('Focal'))
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-secondary tpw-gallery-remove-image', 'data-image-id': rec.image_id }).text(tpwGallery.i18nRemove||'Remove'))
              .append($('<button/>', { 'type': 'button', 'class': 'tpw-btn tpw-btn-small tpw-btn-danger tpw-gallery-remove-image-perm', 'data-image-id': rec.image_id }).text(tpwGallery.i18nDelete||tpwGallery.i18nDeletePermShort||'Delete'));
            li.append($actions);
          }
          $placeholder.replaceWith(li);
        }
        uploadOne(idx+1);
      }).fail(()=>{
        // Mark failure on placeholder
        $placeholder.find('.tpw-upload-label').text((tpwGallery.i18nUploadFailed||'Upload failed') + (fname ? ' ' + fname : ''));
        $placeholder.addClass('tpw-upload-failed');
        setTimeout(()=>{ $placeholder.remove(); }, 2000);
        uploadOne(idx+1);
      });
    };
    uploadOne(0);
    }).catch(err => { alert(err); });
  });

  // Menu actions
  $(document).on('click', '#tpw-add-images-menu .tpw-dropdown__item', function(){
    const action = $(this).data('action');
    closeAddMenu();
    const gid = parseInt($('input[name="gallery_id"]').val()||0,10);
    if (!gid) { alert('Save gallery first.'); return; }
    if (action === 'library') {
      if (typeof wp === 'undefined' || !wp.media) return alert('Media library is not available on this page.');
      const frame = wp.media({ multiple: true });
      frame.on('select', function(){
        const ids = frame.state().get('selection').map(a=>a.id);
        ajax('tpw_gallery_add_attachments', { ids: ids.join(','), gallery_id: gid, _wpnonce: tpwGallery.nonce }).done(resp=>{
          if (resp && resp.success) location.reload(); else alert(resp && resp.data ? resp.data : 'Error');
        });
      });
      frame.open();
    } else if (action === 'upload') {
      $('#tpw-gallery-file').trigger('click');
    }
  });

  // Permanent delete
  $(document).on('click', '.tpw-gallery-remove-image-perm', function(e){
    e.preventDefault();
    const $btn = $(this);
    const imageId = parseInt($btn.data('image-id')||0, 10);
    if (!imageId) return;
    if (!confirm(tpwGallery.i18nDeletePerm || 'Delete this image permanently from the Media Library? This cannot be undone.')) return;
    ajax('tpw_gallery_delete_image_permanently', { image_id: imageId, _wpnonce: tpwGallery.nonce }).done(resp => {
      if (resp && resp.success) {
        $btn.closest('li').remove();
      } else {
        alert(resp && resp.data ? resp.data : 'Error');
      }
    });
  });

  // Remove single image from gallery (inline)
  $(document).on('click', '.tpw-gallery-remove-image', function(e){
    e.preventDefault();
    const $btn = $(this);
    const imageId = parseInt($btn.data('image-id')||0, 10);
    if (!imageId) return;
    if (!confirm(tpwGallery.i18nRemoveConfirm || 'Remove this image from the gallery?')) return;
    ajax('tpw_gallery_delete_image', { image_id: imageId, _wpnonce: tpwGallery.nonce }).done(resp => {
      if (resp && resp.success) {
        $btn.closest('li').remove();
      } else {
        alert(resp && resp.data ? resp.data : 'Error');
      }
    });
  });

  // Caption editing interactions (admin)
  function startEdit($wrap){
    $wrap.find('.tpw-cap-editor').show();
    $wrap.find('.tpw-cap-input').focus().select();
  }
  function stopEdit($wrap){
    $wrap.find('.tpw-cap-editor').hide();
  }
  function saveCaption($li, $wrap){
    const imageId = parseInt($li.data('image-id')||0,10);
    if (!imageId) return;
    const val = $wrap.find('.tpw-cap-input').val();
    ajax('tpw_gallery_update_caption', { image_id: imageId, caption: val, _wpnonce: tpwGallery.nonce }).done(resp=>{
      if (resp && resp.success && resp.data){
        const saved = resp.data.caption || '';
        $wrap.find('.tpw-cap-text').text(saved);
        stopEdit($wrap);
        // Tiny toast
        const tick = $('<span/>', { 'class':'tpw-cap-saved', text: '✔ ' + (tpwGallery.i18nSaved||'Saved') , style:'margin-left:6px;color:#2e7d32;font-size:.85em;' });
        $wrap.find('.tpw-cap-text').after(tick);
        setTimeout(()=>tick.fadeOut(200,()=>tick.remove()), 1200);
        // Clear any pending intent flags
        $wrap.removeData('capAction');
      } else {
        alert(resp && resp.data ? resp.data : 'Error');
        $wrap.removeData('capAction');
      }
    });
  }
  // Click caption to edit
  $(document).on('click', '.tpw-cap-text', function(){
    const $wrap = $(this).closest('.tpw-cap-wrap');
    startEdit($wrap);
  });
  // Keyboard access on caption (Enter/Space)
  $(document).on('keydown', '.tpw-cap-text', function(e){
    if (e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      const $wrap = $(this).closest('.tpw-cap-wrap');
      startEdit($wrap);
    }
  });
  // Double-click image to edit
  $(document).on('dblclick', '#tpw-gallery-thumbs li .tpw-thumb img', function(){
    if (!tpwGallery.isAdmin) return;
    const $wrap = $(this).closest('li').find('.tpw-cap-wrap');
    if ($wrap.length) startEdit($wrap);
  });
  // Pre-mark intent on mousedown to distinguish blur cause
  $(document).on('mousedown', '.tpw-cap-save', function(){
    $(this).closest('.tpw-cap-wrap').data('capAction', 'save');
  });
  $(document).on('mousedown', '.tpw-cap-cancel', function(){
    $(this).closest('.tpw-cap-wrap').data('capAction', 'cancel');
  });
  // Save
  $(document).on('click', '.tpw-cap-save', function(e){
    e.preventDefault();
    e.stopPropagation();
    const $wrap = $(this).closest('.tpw-cap-wrap');
    $wrap.data('capAction', 'save');
    const $li = $(this).closest('li');
    saveCaption($li, $wrap);
  });
  // Cancel
  $(document).on('click', '.tpw-cap-cancel', function(e){
    e.preventDefault();
    e.stopPropagation();
    const $wrap = $(this).closest('.tpw-cap-wrap');
    // Explicitly cancel edit and clear intent
    stopEdit($wrap);
    $wrap.removeData('capAction');
  });
  // Enter / blur autosave with intent awareness
  $(document).on('keydown', '.tpw-cap-input', function(e){
    if (e.key==='Enter'){
      e.preventDefault();
      const $wrap = $(this).closest('.tpw-cap-wrap');
      const $li = $(this).closest('li');
      $wrap.data('capAction', 'save');
      saveCaption($li, $wrap);
    }
  });
  $(document).on('blur', '.tpw-cap-input', function(){
    const $wrap = $(this).closest('.tpw-cap-wrap');
    const intent = $wrap.data('capAction');
    const $li = $(this).closest('li');
    if (intent === 'cancel') {
      // Cancel requested: just close editor, no save
      stopEdit($wrap);
      $wrap.removeData('capAction');
      return;
    }
    if (intent === 'save') {
      // Save will be/was handled by click/enter handler; skip duplicate
      return;
    }
    // No explicit intent: treat as autosave on blur
    saveCaption($li, $wrap);
  });

  // Focal point editor modal (admin)
  function ensureFocalModal(){
    if ($('#tpw-focal-modal').length) return;
    const html = '\
    <div id="tpw-focal-modal" class="tpw-modal tpw-focal-modal" aria-hidden="true">\
      <div class="tpw-modal__overlay" data-tpw-focal-close></div>\
      <div class="tpw-modal__content">\
        <div class="tpw-modal__header">\
          <h3 class="tpw-modal__title">Edit Focal Point</h3>\
          <button class="tpw-btn tpw-btn-small" data-tpw-focal-close aria-label="Close">✕</button>\
        </div>\
        <div class="tpw-modal__body">\
          <div class="tpw-focal-modal__imgwrap">\
            <img class="tpw-focal-img" src="" alt=""/>\
            <div class="tpw-focal-handle tpw-focal-handle--lg"></div>\
          </div>\
          <div class="tpw-actions">\
            <button class="tpw-btn tpw-btn-primary" id="tpw-focal-save">' + (tpwGallery.i18nSave||'Save') + '</button>\
            <button class="tpw-btn tpw-btn-secondary" data-tpw-focal-close>' + (tpwGallery.i18nCancel||'Cancel') + '</button>\
          </div>\
        </div>\
      </div>\
    </div>';
    $('body').append(html);
  }

  function openFocalModal(context){
    ensureFocalModal();
    const $m = $('#tpw-focal-modal');
    const $img = $m.find('.tpw-focal-img');
    const $handle = $m.find('.tpw-focal-handle');
    let fx = (typeof context.fx === 'number') ? context.fx : 0.5;
    let fy = (typeof context.fy === 'number') ? context.fy : 0.5;
    const imageId = context.imageId;
    const $thumbLi = context.$thumbLi;
    $img.attr('src', context.url || '').attr('alt', context.alt||'');
    function positionHandle(){
      const rect = $img.get(0).getBoundingClientRect();
      const left = rect.left + fx * rect.width;
      const top  = rect.top  + fy * rect.height;
      $handle.css({ left: left + 'px', top: top + 'px' });
    }
    function setByEvent(ev){
      const pt = (ev.touches && ev.touches[0]) || ev;
      const rect = $img.get(0).getBoundingClientRect();
      fx = (pt.clientX - rect.left) / rect.width; fx = Math.max(0, Math.min(1, fx));
      fy = (pt.clientY - rect.top) / rect.height; fy = Math.max(0, Math.min(1, fy));
      positionHandle();
    }
    // Center if image not loaded yet; then reposition on load
    $handle.css({ position:'fixed', transform:'translate(-50%,-50%)', width:'18px', height:'18px', borderRadius:'50%', background:'#10b981', border:'2px solid #fff', boxShadow:'0 2px 6px rgba(0,0,0,.3)', cursor:'grab', zIndex:100002 });
    $img.off('load').on('load', function(){ positionHandle(); });
    // Click to set within image
    $m.off('click.tpwFocal').on('click.tpwFocal', '.tpw-focal-modal__imgwrap', function(e){ setByEvent(e); });
    // Drag handle
    $handle.off('mousedown.tpwFocal touchstart.tpwFocal').on('mousedown.tpwFocal touchstart.tpwFocal', function(e){
      e.preventDefault(); e.stopPropagation();
      function move(ev){ ev.preventDefault(); setByEvent(ev); }
      function up(){ $(document).off('mousemove.tpwFocal mouseup.tpwFocal touchmove.tpwFocal touchend.tpwFocal'); }
      $(document).on('mousemove.tpwFocal', move).on('mouseup.tpwFocal', up).on('touchmove.tpwFocal', move).on('touchend.tpwFocal', up);
    });
    // Save
    $m.off('click.saveFocal').on('click.saveFocal', '#tpw-focal-save', function(){
      ajax('tpw_gallery_update_image_focus', { image_id: imageId, focus_x: fx, focus_y: fy, _wpnonce: tpwGallery.nonce }).done(function(resp){
        if (resp && resp.success) {
          // Update inline reference dot
          const $dot = $thumbLi.find('.tpw-focal-handle');
          $dot.css({ left: (fx*100)+'%', top: (fy*100)+'%' });
          // Update focal custom properties on editor page thumbnail if present
          const $thumbEl = $thumbLi.find('.tpw-thumb').first();
          if ($thumbEl.length) {
            const el = $thumbEl.get(0);
            if (el && el.style && typeof el.style.setProperty === 'function') {
              el.style.setProperty('--focus-x', Math.round(fx * 100) + '%');
              el.style.setProperty('--focus-y', Math.round(fy * 100) + '%');
            } else {
              $thumbEl.css('--focus-x', Math.round(fx * 100) + '%');
              $thumbEl.css('--focus-y', Math.round(fy * 100) + '%');
            }
          }
          closeFocalModal();
        } else {
          alert(resp && resp.data ? resp.data : 'Error');
        }
      });
    });
    // Close mechanics
    function closeFocalModal(){ $m.hide().attr('aria-hidden','true'); $('html, body').removeClass('tpw-modal-open'); }
    $m.off('click.closeFocal').on('click.closeFocal', '[data-tpw-focal-close], .tpw-modal__overlay', function(e){ e.preventDefault(); closeFocalModal(); });
    $(document).off('keyup.tpwFocalEsc').on('keyup.tpwFocalEsc', function(e){ if (e.key==='Escape' && $m.is(':visible')) closeFocalModal(); });
    // Open
    $m.show().attr('aria-hidden','false'); $('html, body').addClass('tpw-modal-open');
  }

  // Helper to open the focal editor for a list item
  function openFocalForLi($li){
    const imageId = parseInt($li.data('image-id')||0,10);
    if (!imageId) return;
    const $img = $li.find('img');
    const alt = $img.attr('alt')||'';
    ajax('tpw_gallery_get', { id: parseInt($('#tpw-gallery-form input[name="gallery_id"]').val()||0,10), _wpnonce: tpwGallery.nonce }).done(function(resp){
      if (!resp || !resp.success || !resp.data) return;
      const images = resp.data.images || [];
      const rec = images.find(i=> parseInt(i.image_id,10)===imageId);
      if (!rec) return;
      openFocalModal({ imageId, url: rec.url||rec.thumb_url||$img.attr('src')||'', alt, fx: (typeof rec.focus_x==='number'? rec.focus_x : null), fy: (typeof rec.focus_y==='number'? rec.focus_y : null), $thumbLi: $li });
    });
  }

  // Click a thumbnail to open focal editor modal
  $(document).on('click', '#tpw-gallery-thumbs li .tpw-thumb', function(e){
    // Ignore clicks on action buttons within the card
    if ($(e.target).closest('.tpw-btn, .tpw-cap-wrap, .tpw-row').length) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const $li = $(this).closest('li');
    openFocalForLi($li);
  });

  // Dedicated Focal button
  $(document).on('click', '.tpw-gallery-focal', function(e){
    e.preventDefault();
    e.stopPropagation();
    const $li = $(this).closest('li');
    openFocalForLi($li);
  });

  // Prevent lightbox within admin editor/modal
  $(document).on('click', '.tpw-admin-ui #tpw-gallery-thumbs a.tpw-gallery-lightbox', function(e){
    if (tpwGallery && tpwGallery.isAdmin) { e.preventDefault(); e.stopImmediatePropagation(); }
  });

    // Categories: add
    $(document).on('click', '#tpw-add-cat', function(e){
      e.preventDefault();
      const $input = $('#tpw-cat-name');
      const name = $.trim($input.val()||'');
      if (!name) { $input.focus(); return; }
      ajax('tpw_gallery_add_category', { name, _wpnonce: tpwGallery.nonce }).done(resp => {
        if (resp && resp.success && resp.data) {
          const c = resp.data;
          const $tbody = $('#tpw-cat-table tbody');
          const $row = $('<tr/>', { 'data-id': c.category_id }).append(
            $('<td/>').text(c.name),
            $('<td/>').text(c.slug),
            $('<td/>').text(parseInt(c.sort_order||0,10)),
            $('<td/>').append(
              $('<button/>', { 'class':'tpw-btn tpw-btn-small tpw-btn-danger tpw-cat-delete', 'data-id': c.category_id }).text('Delete')
            )
          );
          $tbody.append($row);
          $input.val('');
        } else {
          alert(resp && resp.data ? resp.data : 'Error');
        }
      });
    });

    // Categories: delete
    $(document).on('click', '.tpw-cat-delete', function(e){
      e.preventDefault();
      if (!confirm(tpwGallery.i18nDeleteCategoryConfirm || 'Delete this category?')) return;
      const $btn = $(this);
      const id = parseInt($btn.data('id')||0,10);
      if (!id) return;
      ajax('tpw_gallery_delete_category', { id, _wpnonce: tpwGallery.nonce }).done(resp => {
        if (resp && resp.success) {
          $btn.closest('tr').remove();
        } else {
          alert(resp && resp.data ? resp.data : 'Error');
        }
      });
    });

    // Manage Categories modal controls
    function openCatModal(){ $('#tpw-categories-modal').show().attr('aria-hidden','false'); $('html, body').addClass('tpw-modal-open'); }
    function closeCatModal(){ $('#tpw-categories-modal').hide().attr('aria-hidden','true'); $('html, body').removeClass('tpw-modal-open'); refreshCategoryDropdown(); }
    $(document).on('click', '#tpw-manage-categories-btn', function(e){ e.preventDefault(); openCatModal(); });
    $(document).on('click', '#tpw-categories-modal-close,[data-tpw-categories-modal-close]', function(e){ e.preventDefault(); closeCatModal(); });
    $(document).on('keyup', function(e){ if (e.key==='Escape' && $('#tpw-categories-modal').is(':visible')) closeCatModal(); });

    // Refresh the category dropdown in the gallery form after managing categories
    function refreshCategoryDropdown(){
      // Re-fetch categories from server and rebuild options
      if (typeof ajax !== 'function') return;
      // Lightweight endpoint: reuse existing getter via admin AJAX? Not present, so refresh via window.location as fallback if needed
      // We'll rebuild from the categories table currently in DOM to avoid an extra endpoint.
      const $sel = $('#tpw-gallery-form select[name="category_id"]'); if (!$sel.length) return;
      const currentVal = parseInt($sel.val()||0,10);
      const $rows = $('#tpw-cat-table tbody tr');
      const options = [{ value: 0, label: 'Uncategorised' }];
      $rows.each(function(){
        const $tr = $(this);
        const name = $tr.find('td').eq(0).text();
        const idAttr = parseInt($tr.data('id')||0,10);
        if (idAttr>0 && name) options.push({ value: idAttr, label: name });
      });
      // Replace options
      $sel.empty();
      options.forEach(o=>{ $sel.append($('<option/>',{ value: o.value, text: o.label })); });
      // Restore selection if still valid
      $sel.val(currentVal);
    }

    // Drag-and-drop sorting for thumbnails (modal and editor page)
    function initThumbSortable(){
    const $ul = $('#tpw-gallery-thumbs');
    if (!$ul.length || typeof $ul.sortable !== 'function') return;
    if ($ul.data('tpw-sortable')) return; // already enabled
    $ul.sortable({
      items: '> li',
      tolerance: 'pointer',
      forcePlaceholderSize: true,
      placeholder: 'tpw-thumb-drag-placeholder',
      update: function(){
        const gid = parseInt($('#tpw-gallery-form input[name="gallery_id"]').val()||0,10);
        if (!gid) return;
        const ordered = $ul.children('li').map(function(){ return $(this).data('image-id'); }).get();
        if (!ordered.length) return;
        ajax('tpw_gallery_reorder_images', { gallery_id: gid, order: ordered.join(','), _wpnonce: tpwGallery.nonce }).done(function(resp){
          if (!(resp && resp.success)) alert(resp && resp.data ? resp.data : 'Error saving order');
        });
      }
    }).disableSelection();
    $ul.data('tpw-sortable', true);
    }

    // If we are on the full editor page, initialize sortable immediately
    $(function(){ if (tpwGallery && tpwGallery.isEditorPage) { initThumbSortable(); } });
  })(jQuery);
