;(function($){
  function ajax(action, data){
    var url = (window.TPW_MEMBER_LINK && TPW_MEMBER_LINK.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php';
    var nonce = (window.TPW_MEMBER_DIR && TPW_MEMBER_DIR.nonce) || (window.TPW_MEMBER_LINK && TPW_MEMBER_LINK.nonce);
    return $.post(url, Object.assign({ action: action, _wpnonce: nonce }, data));
  }

  // Open details modal
  $(document).on('click', '.tpw-member-name-link', function(e){
    e.preventDefault();
    var id = $(this).data('member-id');
    $('#tpw-member-details-content').text('Loading...');
    $('#tpw-member-details-modal').removeAttr('hidden');
    ajax('tpw_member_get_details', { member_id: id }).done(function(resp){
      if(!resp || !resp.success){
        $('#tpw-member-details-content').text((resp&&resp.data&&resp.data.message)||'Error');
        return;
      }
      $('#tpw-member-details-content').html(resp.data.html);
    }).fail(function(){ $('#tpw-member-details-content').text('Error'); });
  });

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
