(function($){
  $(function(){
    // Toggle password visibility (supports <img role="button"> control)
    function togglePassword($ctrl){
      var $input = $('#tpw-password');
      var isHidden = ($input.attr('type') === 'password');
      if (isHidden) {
        $input.attr('type', 'text');
        $ctrl.attr({ 'aria-label':'Hide password', 'title':'Hide password', 'aria-pressed':'true' });
      } else {
        $input.attr('type', 'password');
        $ctrl.attr({ 'aria-label':'Show password', 'title':'Show password', 'aria-pressed':'false' });
      }
    }

    $(document).on('click', '.tpw-toggle-password', function(e){
      e.preventDefault();
      togglePassword($(this));
    });

    // Keyboard support (Enter/Space)
    $(document).on('keydown', '.tpw-toggle-password', function(e){
      var key = e.which || e.keyCode;
      if (key === 13 || key === 32) { // Enter or Space
        e.preventDefault();
        togglePassword($(this));
      }
    });

  });
})(jQuery);
