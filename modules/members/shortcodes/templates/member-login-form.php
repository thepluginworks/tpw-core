<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="tpw-login-wrap">
  <?php if (!empty($data['messages'])): ?>
    <div class="tpw-login-messages">
      <?php foreach ($data['messages'] as $type => $msg): ?>
        <div class="tpw-msg tpw-msg-<?php echo esc_attr($type); ?>"><?php echo esc_html($msg); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php
    // Compute redirect_to from the query string once so both forms can use it
    $redirect_qs = isset($_GET['redirect_to']) ? (string) wp_unslash( $_GET['redirect_to'] ) : '';
    $current_action = isset($_GET['action']) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

    $login_view_url = $data['action_url'] ?? '';
    $lost_password_url = $data['action_url'] ?? '';
    if ( $lost_password_url !== '' ) {
      $lost_password_url = add_query_arg( 'action', 'lostpassword', $lost_password_url );
    }
    if ( $redirect_qs !== '' ) {
      $login_view_url = add_query_arg( 'redirect_to', $redirect_qs, $login_view_url );
      $lost_password_url = add_query_arg( 'redirect_to', $redirect_qs, $lost_password_url );
    }
  ?>

  <?php
    // If arriving via reset link (action=rp), show the set new password form instead of login/reset-request
    $is_rp = $current_action === 'rp' && isset($_GET['key']) && isset($_GET['login']);
    $is_lost_password = $current_action === 'lostpassword';
  ?>

  <?php if ( $is_rp ) :
      $key   = isset($_GET['key']) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
      $login = isset($_GET['login']) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';
  ?>
    <form method="post" class="tpw-set-password-form" action="<?php echo esc_url( $data['action_url'] ?? '' ); ?>">
      <?php wp_nonce_field('tpw_member_do_reset', 'tpw_member_do_reset_nonce', false, true); ?>
      <input type="hidden" name="tpw_member_login_action" value="do_reset" />
      <input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" />
      <input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>" />
      <?php if ( ! empty( $redirect_qs ) ) : ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_qs ); ?>" />
      <?php endif; ?>

      <div class="tpw-field">
        <label for="tpw-pass1">New password</label>
        <input type="password" id="tpw-pass1" name="pass1" autocomplete="new-password" required />
      </div>
      <div class="tpw-field">
        <label for="tpw-pass2">Confirm new password</label>
        <input type="password" id="tpw-pass2" name="pass2" autocomplete="new-password" required />
      </div>

      <div class="tpw-actions">
        <button type="submit" class="tpw-btn tpw-btn-primary">Set new password</button>
      </div>
    </form>

  <?php elseif ( $is_lost_password ) : ?>

  <form method="post" class="tpw-reset-form" action="<?php echo esc_url( $data['action_url'] ?? '' ); ?>">
    <?php
      // Our plugin-specific nonce
      wp_nonce_field('tpw_member_reset', 'tpw_member_reset_nonce', false, true);
      // Also include WordPress core lostpassword nonce for broader compatibility
      wp_nonce_field('lostpassword');
    ?>
    <input type="hidden" name="tpw_member_login_action" value="reset" />
    <?php if ( ! empty( $redirect_qs ) ) : ?>
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_qs ); ?>" />
    <?php endif; ?>

    <div class="tpw-field">
      <label for="tpw-identifier">Email or Username</label>
      <input type="text" id="tpw-identifier" name="identifier" required />
    </div>

    <div class="tpw-actions">
      <button type="submit" class="tpw-btn tpw-btn-primary">Send reset link</button>
      <a href="<?php echo esc_url( $login_view_url ); ?>" class="tpw-back-to-login">Back to login</a>
    </div>
  </form>

  <?php else : ?>

  <form method="post" class="tpw-login-form" action="<?php echo esc_url( $data['action_url'] ?? '' ); ?>">
    <?php wp_nonce_field('tpw_member_login', 'tpw_member_login_nonce', false, true); ?>
    <input type="hidden" name="tpw_member_login_action" value="login" />
    <?php if ( $redirect_qs !== '' ) : ?>
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_qs ); ?>" />
    <?php endif; ?>

    <div class="tpw-field">
      <label for="tpw-login">Email or Username</label>
      <input type="text" id="tpw-login" name="login" autocomplete="username" required />
    </div>

    <div class="tpw-field tpw-password-field">
      <label for="tpw-password">Password</label>
      <div class="tpw-password-input">
        <input type="password" id="tpw-password" name="password" autocomplete="current-password" required />
          <?php $tpw_eye_url = defined('TPW_CORE_URL') ? TPW_CORE_URL . 'assets/images/eye-thin-full.svg' : ''; ?>
          <img src="<?php echo esc_url( $tpw_eye_url ); ?>"
               class="tpw-toggle-password"
               alt=""
               aria-label="Show password"
               title="Show password"
               tabindex="0"
               role="button"
               width="20"
               height="20"
          />
      </div>
    </div>

    <div class="tpw-field tpw-inline">
      <label><input type="checkbox" name="remember" value="1" /> Remember me</label>
    </div>

    <div class="tpw-actions">
      <button type="submit" class="tpw-btn tpw-btn-primary">Login</button>
      <a href="<?php echo esc_url( $lost_password_url ); ?>" class="tpw-lost-password">Lost your password?</a>
    </div>
  </form>
  <?php endif; ?>
</div>
