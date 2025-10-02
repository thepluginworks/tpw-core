<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="tpw-login-wrap">
  <?php if (!empty($data['messages'])): ?>
    <div class="tpw-login-messages">
      <?php foreach ($data['messages'] as $type => $msg): ?>
        <div class="tpw-msg tpw-msg-<?php echo esc_attr($type); ?>"><?php echo esc_html($msg); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="tpw-login-form">
    <?php wp_nonce_field('tpw_member_login', 'tpw_member_login_nonce'); ?>
    <input type="hidden" name="tpw_member_login_action" value="login" />

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
      <a href="#" class="tpw-lost-password" data-toggle="tpw-reset-form">Lost your password?</a>
    </div>
  </form>

  <form method="post" class="tpw-reset-form" style="display:none">
    <?php wp_nonce_field('tpw_member_reset', 'tpw_member_reset_nonce'); ?>
    <input type="hidden" name="tpw_member_login_action" value="reset" />

    <div class="tpw-field">
      <label for="tpw-identifier">Email or Username</label>
      <input type="text" id="tpw-identifier" name="identifier" required />
    </div>

    <div class="tpw-actions">
      <button type="submit" class="tpw-btn tpw-btn-primary">Send reset link</button>
      <a href="#" class="tpw-back-to-login" data-toggle="tpw-login-form">Back to login</a>
    </div>
  </form>
</div>
