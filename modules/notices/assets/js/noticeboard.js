(function($){
  function openModal(){ $('#tpw-notice-modal').show(); }
  function closeModal(){ $('#tpw-notice-modal').hide(); }
  function setEditorContent(html){ if (typeof tinymce !== 'undefined' && tinymce.get('tpw_notice_content')) { tinymce.get('tpw_notice_content').setContent(html||''); } else { $('#tpw_notice_content').val(html||''); } }
  function getEditorContent(){ if (typeof tinymce !== 'undefined' && tinymce.get('tpw_notice_content')) { return tinymce.get('tpw_notice_content').getContent(); } return $('#tpw_notice_content').val(); }

  $(document).on('click','.tpw-notice-modal-close', function(){ closeModal(); });

  $(document).on('click', '.tpw-notice-add', function(){
    $('#tpw-notice-form')[0].reset();
    $('input[name="notice_id"]').val('');
    setEditorContent('');
    $('.tpw-image-preview').empty();
    openModal();
  });

  $(document).on('click', '.tpw-notice-edit', function(){
    var $card = $(this).closest('.tpw-notice-card');
    var id = $card.data('id');
    $('input[name="notice_id"]').val(id);
    $('input[name="title"]').val($card.find('.tpw-notice-title').text());
    $('textarea[name="excerpt"]').val($card.find('.tpw-notice-excerpt').text());
    $.getJSON('/wp-json/wp/v2/tpw_notice/'+id, function(post){ setEditorContent(post.content && post.content.rendered ? post.content.rendered : ''); });
    openModal();
  });

  $(document).on('click', '.tpw-pick-image', function(e){
    e.preventDefault();
    var frame = wp.media({ title: 'Select Image', button: { text: 'Use this image' }, multiple: false });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('input[name="thumbnail_id"]').val(attachment.id);
      $('.tpw-image-preview').html('<img src="'+attachment.url+'" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;" />');
    });
    frame.open();
  });

  $(document).on('click', '.tpw-notice-delete', function(){
    if (!confirm('Delete this notice?')) return;
    var $card = $(this).closest('.tpw-notice-card');
    var id = $card.data('id');
    $.post(TPWNoticeboard.ajaxUrl, { action: 'tpw_notice_delete', notice_id: id, _wpnonce: TPWNoticeboard.nonces.delete }, function(resp){
      if (resp && resp.success){ $card.remove(); } else { alert(resp.data && resp.data.message ? resp.data.message : 'Delete failed'); }
    });
  });

  $(document).on('click', '.tpw-notice-duplicate', function(){
    var $card = $(this).closest('.tpw-notice-card');
    var id = $card.data('id');
    $.post(TPWNoticeboard.ajaxUrl, { action: 'tpw_notice_duplicate', notice_id: id, _wpnonce: TPWNoticeboard.nonces.duplicate }, function(resp){
      if (resp && resp.success){ location.reload(); } else { alert(resp.data && resp.data.message ? resp.data.message : 'Duplicate failed'); }
    });
  });

  $(document).on('submit', '#tpw-notice-form', function(e){
    e.preventDefault();
    var data = $(this).serializeArray();
    var content = getEditorContent();
    data.push({ name: 'content', value: content });
    $.post(TPWNoticeboard.ajaxUrl, data, function(resp){
      if (resp && resp.success){ location.reload(); } else { alert(resp.data && resp.data.message ? resp.data.message : 'Save failed'); }
    });
  });

  $(document).on('click', '#tpw_add_category_btn', function(){
    var name = $('#tpw_new_category_name').val().trim();
    if (!name) { $('.tpw-add-cat-msg').text('Please enter a category name.').css('color','#b91c1c'); return; }
    $('.tpw-add-cat-msg').text('');
    $.post(TPWNoticeboard.ajaxUrl, { action:'tpw_notice_add_category', name: name, _wpnonce: TPWNoticeboard.nonces.addCategory }, function(resp){
      if (resp && resp.success && resp.data && resp.data.term){
        var term = resp.data.term;
        var $sel = $('#tpw_notice_category');
        var $opt = $('<option>').val(term.id).text(term.name);
        $sel.append($opt).val(term.id);
        $('#tpw_new_category_name').val('');
        $('.tpw-add-cat-msg').text('Category added.').css('color','#14532d');
      } else {
        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Add category failed';
        $('.tpw-add-cat-msg').text(msg).css('color','#b91c1c');
      }
    });
  });
})(jQuery);
