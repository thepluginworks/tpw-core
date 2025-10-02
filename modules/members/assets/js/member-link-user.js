;(function($){
  function ajax(action, data){
    var url = (window.TPW_MEMBER_LINK && TPW_MEMBER_LINK.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php';
    return $.post(url, Object.assign({ action: action, _wpnonce: TPW_MEMBER_LINK.nonce }, data));
  }

  $(document).on('click', '#tpw-link-user-btn', function(e){
    e.preventDefault();
    $('#tpw-link-user-modal').removeAttr('hidden');
    $('#tpw-link-user-search').val('').trigger('input');
    $('#tpw-link-user-results').empty().text('Type to search users...');
  });

  $(document).on('click', '#tpw-link-user-close', function(){
    $('#tpw-link-user-modal').attr('hidden', true);
  });

  let timer=null;
  $(document).on('input', '#tpw-link-user-search', function(){
    const term = $(this).val();
    clearTimeout(timer);
    timer = setTimeout(function(){
      $('#tpw-link-user-results').text('Searching...');
      ajax('tpw_member_search_users', { term }).done(function(resp){
        if(!resp || !resp.success){
          $('#tpw-link-user-results').text(resp && resp.data && resp.data.message ? resp.data.message : 'Error');
          return;
        }
        const list = $('<ul class="tpw-user-search-list"></ul>');
        const results = resp.data.results || [];
        if(results.length === 0){
          $('#tpw-link-user-results').text('No users found');
          return;
        }
        results.forEach(function(u){
          const name = (u.first_name||'') + ' ' + (u.last_name||'');
          const li = $('<li></li>');
          const btn = $('<button type="button" class="button">Select</button>');
          btn.on('click', function(){
            $('#tpw-selected-user').text(u.user_login + ' (' + u.user_email + ')');
            $('#tpw-selected-user').data('user-id', u.id);
            $('#tpw-link-user-confirm').prop('disabled', false);
          });
          li.append('<strong>'+u.user_login+'</strong> '+u.user_email+' '+name);
          li.append(' ').append(btn);
          list.append(li);
        });
        $('#tpw-link-user-results').empty().append(list);
      }).fail(function(xhr){
        var msg = 'AJAX error ' + (xhr && xhr.status ? xhr.status : '');
        $('#tpw-link-user-results').text(msg);
      });
    }, 250);
  });

  $(document).on('click', '#tpw-link-user-confirm', function(){
    const userId = $('#tpw-selected-user').data('user-id');
    if(!userId){ return; }
    $('#tpw-link-user-confirm').prop('disabled', true).text('Creating...');
    ajax('tpw_member_create_from_user', { user_id: userId }).done(function(resp){
      if(!resp || !resp.success){
        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Error creating member');
        $('#tpw-link-user-confirm').prop('disabled', false).text('Create Member');
        return;
      }
      // Reload to see new member in list
      window.location.reload();
    }).fail(function(xhr){
      alert('AJAX error ' + (xhr && xhr.status ? xhr.status : ''));
      $('#tpw-link-user-confirm').prop('disabled', false).text('Create Member');
    });
  });
})(jQuery);
