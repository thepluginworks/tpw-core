(function($){
  function initTinyMCE(){
    if ( typeof tinymce !== 'undefined' ) {
      var $ta = $('.tpw-email-message');
      if ($ta.length && ! $ta.hasClass('mce-initialized')) {
        $ta.addClass('mce-initialized');
        tinymce.init({
          selector: '.tpw-email-message',
          menubar: false,
          statusbar: false,
          height: 220,
          plugins: 'link lists paste',
          toolbar: 'bold italic | bullist numlist | link | undo redo',
          branding: false,
        });
      }
    }
  }

  function validFile(file){
  var allowed = ['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document','image/jpeg','image/jpg','image/png'];
    var max = (window.TPW_EMAIL && TPW_EMAIL.maxBytes) ? TPW_EMAIL.maxBytes : (5 * 1024 * 1024);
    if (file.size > max) return {ok:false,msg:'File too large: '+file.name};
    if (allowed.indexOf(file.type) === -1) return {ok:false,msg:'File type not allowed: '+file.name};
    return {ok:true};
  }

  function bindForm(){
    var $form = $('#tpw-email-generic-form');
    if (!$form.length) return;

    // File validation
    $form.on('change', 'input[type=file][name="attachments[]"]', function(){
      var files = this.files || [];
      var $res = $('#tpw-email-generic-result');
      $res.empty();
      for (var i=0; i<files.length; i++){
        var v = validFile(files[i]);
        if (!v.ok){ $res.text(v.msg); this.value=''; break; }
      }
    });

    // Submit with AJAX
    $form.on('submit', function(e){
      e.preventDefault();
      var $btn = $('#tpw-email-generic-submit');
      var $res = $('#tpw-email-generic-result');
      // Sync TinyMCE to textarea
      if (typeof tinymce !== 'undefined') { tinymce.triggerSave(); }

      var fd = new FormData(this);
      $btn.prop('disabled', true).text((TPW_EMAIL && TPW_EMAIL.i18n && TPW_EMAIL.i18n.sending) || 'Sending…');
      $.ajax({
        url: (TPW_EMAIL && TPW_EMAIL.ajaxUrl) || (window.ajaxurl) || '/wp-admin/admin-ajax.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(resp){
          if (!resp || !resp.success){
            $res.text((resp && resp.data && resp.data.message) || 'Error');
          } else {
            $res.text((resp && resp.data && resp.data.message) || 'Email sent');
          }
        },
        error: function(jqXHR){
          var msg = 'Error sending email';
          try {
            var json = jqXHR.responseJSON || (jqXHR.responseText ? JSON.parse(jqXHR.responseText) : null);
            if (json && json.data && json.data.message) { msg = json.data.message; }
          } catch(e) { /* ignore parse errors */ }
          $res.text(msg);
        },
        complete: function(){
          $btn.prop('disabled', false).text((TPW_EMAIL && TPW_EMAIL.i18n && TPW_EMAIL.i18n.send) || 'Send');
        }
      });
    });
  }

  function bindModal(){
    $(document).on('click', '.tpw-email-modal-close', function(){
      $(this).closest('.tpw-email-modal').attr('hidden', true);
    });
  }

  $(function(){
    initTinyMCE();
    bindForm();
    bindModal();
  });
})(jQuery);
