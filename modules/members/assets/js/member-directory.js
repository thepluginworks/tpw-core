;(function($){
  function ajax(action, data){
    var url = (window.TPW_MEMBER_LINK && TPW_MEMBER_LINK.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php';
    var nonce = (window.TPW_MEMBER_DIR && TPW_MEMBER_DIR.nonce) || (window.TPW_MEMBER_LINK && TPW_MEMBER_LINK.nonce);
    return $.post(url, Object.assign({ action: action, _wpnonce: nonce }, data));
  }

  // Open details modal
  var tpwDetailsReqSeq = 0; // incrementing sequence id
  var tpwDetailsXhr = null; // track in-flight jqXHR
  function bindMemberLinks(){ /* delegated handler already set below; kept for explicit rebind hook */ }
  $(document).on('click', '.tpw-member-name-link', function(e){
    e.preventDefault();
    // Prefer attribute over jQuery .data cache to avoid stale values after dynamic DOM updates
    var idAttr = $(this).attr('data-member-id');
    var id = parseInt(idAttr,10);
    if((!id || id<=0) && $(this).closest('[data-member-id]').length){
      var parentAttr = $(this).closest('[data-member-id]').attr('data-member-id');
      id = parseInt(parentAttr,10);
    }
    console.log('[tpw] member link click', { rawAttr: idAttr, resolved: id, elem: this });
    if(!id || id <= 0){ return; }
    var mySeq = ++tpwDetailsReqSeq;
    if (tpwDetailsXhr && tpwDetailsXhr.abort) { try { tpwDetailsXhr.abort(); } catch(e){} }
    $('#tpw-member-details-content').text('Loading...');
    $('#tpw-member-details-modal').removeAttr('hidden');
  // Nonce sourced from TPW_MEMBER_DIR localization object
  tpwDetailsXhr = ajax('tpw_member_get_details', { member_id: id });
    tpwDetailsXhr.done(function(resp){
      if(mySeq !== tpwDetailsReqSeq){ return; } // stale response, ignore
      if(!resp || !resp.success){
        $('#tpw-member-details-content').text((resp&&resp.data&&resp.data.message)||'Error');
        return;
      }
      var requested_id = resp.data && resp.data.requested_id;
      var returned_id = resp.data && resp.data.returned_id;
      if (parseInt(requested_id,10) !== parseInt(returned_id,10)) {
        console.warn('[tpw] details id mismatch', {requested_id, returned_id});
        return; // ignore mismatched response
      }
      $('#tpw-member-details-content').html(resp.data.html);
      console.log('[tpw] details loaded', {requested_id, returned_id, seq: mySeq});
    }).fail(function(){ if(mySeq === tpwDetailsReqSeq){ $('#tpw-member-details-content').text('Error'); } });
  });

  // Rebind links after possible dynamic re-render (full page reload not needed, but hook provided)
  $(document).on('tpw_members_list_rendered', function(){ bindMemberLinks(); });

  // Open email modal (reusable TPW Email module)
  $(document).on('click', '.tpw-member-email-link', function(e){
    e.preventDefault();
    var $modal = $('#tpw-members-email-modal');
    var $form = $modal.find('#tpw-email-generic-form');
    if ($form.length) {
      // Reset message/attachments only, preserve sender readonly values
      $form.find('textarea[name=message]').val('');
      $form.find('input[type=file][name="attachments[]"]').val('');
      // Recipient info from data attributes
      var recipName = $(this).data('recipient-name') || '';
      var recipEmail = $(this).data('recipient-email') || '';
      $form.find('input[name=recipient_name]').val(recipName);
      $form.find('input[name=recipient_email]').val(recipEmail);
    }
    $modal.removeAttr('hidden');
  });

  // Close handlers for Member Details modal
  $(document).on('click', '#tpw-member-details-modal .tpw-dir-modal-close', function(){
    $('#tpw-member-details-modal').attr('hidden', true);
  });
  // Click outside dialog closes
  $(document).on('click', '#tpw-member-details-modal', function(e){
    if (e.target && e.target.id === 'tpw-member-details-modal') {
      $('#tpw-member-details-modal').attr('hidden', true);
    }
  });
  // Escape key closes
  $(document).on('keydown', function(e){
    if (e.key === 'Escape') {
      var $modal = $('#tpw-member-details-modal');
      if ($modal.length && !$modal.is('[hidden]')) {
        $modal.attr('hidden', true);
      }
    }
  });

  // Note: Submission and close handlers are managed by the reusable module's email.js
})(jQuery);

// Clear basic filters (including dynamic adv_* params) and reload base page
(function($){
  $(document).on('click', '#tpw-clear-filters', function(e){
    e.preventDefault();
    try {
      var base = new URL(window.location.href.split('#')[0]);
      base.search = '';
      window.location.href = base.toString();
    } catch(err){
      window.location.href = window.location.pathname;
    }
  });
})(jQuery);
