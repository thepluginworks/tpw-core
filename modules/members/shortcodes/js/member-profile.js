(function($){
  function ajax(action, data){
    return $.post(TPW_MEMBER_PROFILE.ajaxUrl, $.extend({action: action, _wpnonce: TPW_MEMBER_PROFILE.nonce}, data||{}));
  }

  // Open modal with field
  $(document).on('click', '.tpw-profile-edit', function(){
    var key = $(this).data('key');
    var row = $(this).closest('.tpw-table-row');
    var label = row.find('.tpw-table-cell').eq(0).text();
    var val = row.find('.tpw-table-cell').eq(1).text();
    $('#tpw-profile-label').text(label);
    $('#tpw-profile-form [name=field_key]').val(key);
    $('#tpw-profile-form [name=field_value]').val(val);
    $('#tpw-profile-result').empty();
    $('#tpw-profile-modal').removeAttr('hidden');
  });

  // Close modal
  $(document).on('click', '.tpw-dir-modal-close', function(){
    $(this).closest('.tpw-dir-modal').attr('hidden', true);
  });

  // Submit update
  $(document).on('submit', '#tpw-profile-form', function(e){
    e.preventDefault();
    var key = $('#tpw-profile-form [name=field_key]').val();
    var val = $('#tpw-profile-form [name=field_value]').val();
    var $btn = $(this).find('button[type=submit]');
    $btn.prop('disabled', true).text('Saving...');
    ajax('tpw_member_profile_update', { field_key: key, field_value: val })
      .done(function(resp){
        if(!resp || !resp.success){
          var msg = (resp && resp.data && (resp.data.message || resp.data.error)) || 'Error';
          $('#tpw-profile-result').text(msg);
          return;
        }
        // Update the visible value in the row
        var row = $('.tpw-profile-edit[data-key='+ key +']').closest('.tpw-table-row');
        row.find('.tpw-table-cell').eq(1).text(val);
        $('#tpw-profile-modal').attr('hidden', true);
      })
      .fail(function(xhr){
        var msg = 'Error';
        try {
          var data = xhr && xhr.responseJSON;
          if (data && data.data && (data.data.message || data.data.error)) {
            msg = data.data.message || data.data.error;
          }
        } catch(e){}
        $('#tpw-profile-result').text(msg);
      })
      .always(function(){
        $btn.prop('disabled', false).text('Confirm');
      });
  });
})(jQuery);
