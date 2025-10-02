<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
// $data array provided by TPW_Email_Form::render
?>
<div id="<?php echo esc_attr( $data['modal_id'] ); ?>" class="tpw-email-modal" hidden>
  <div class="tpw-email-modal__dialog">
    <div class="tpw-email-modal__header">
  <h3><?php esc_html_e('Contact', 'tpw-core'); ?></h3>
  <button type="button" class="button tpw-email-modal-close"><?php esc_html_e('Close','tpw-core'); ?></button>
    </div>
    <div class="tpw-email-modal__body">
      <form id="tpw-email-generic-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="tpw_email_send" />
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce('tpw_email_send') ); ?>" />
        <input type="hidden" name="plugin_slug" value="<?php echo esc_attr( $data['plugin_slug'] ); ?>" />

        <div class="tpw-email-row">
          <label><?php esc_html_e("Recipient's Name", 'tpw-core'); ?></label>
          <input type="text" name="recipient_name" value="<?php echo esc_attr( $data['recipient_name'] ); ?>" readonly />
        </div>
        <div class="tpw-email-row">
          <label><?php esc_html_e("Recipient's Email", 'tpw-core'); ?></label>
          <input type="email" name="recipient_email" value="<?php echo esc_attr( $data['recipient_email'] ); ?>" readonly />
        </div>

        <?php if ( ! empty( $data['from_readonly'] ) ) : ?>
          <div class="tpw-email-row">
            <label><?php esc_html_e('Your Name', 'tpw-core'); ?></label>
            <div class="tpw-email-text"><?php echo esc_html( $data['from_name'] ); ?></div>
            <input type="hidden" name="from_name" value="<?php echo esc_attr( $data['from_name'] ); ?>" />
          </div>
          <div class="tpw-email-row">
            <label><?php esc_html_e('Your Email', 'tpw-core'); ?></label>
            <div class="tpw-email-text"><?php echo esc_html( $data['from_email'] ); ?></div>
            <input type="hidden" name="from_email" value="<?php echo esc_attr( $data['from_email'] ); ?>" />
          </div>
        <?php else: ?>
          <div class="tpw-email-row">
            <label><?php esc_html_e('Your Name', 'tpw-core'); ?></label>
            <input type="text" name="from_name" value="<?php echo esc_attr( $data['from_name'] ); ?>" />
          </div>
          <div class="tpw-email-row">
            <label><?php esc_html_e('Your Email', 'tpw-core'); ?></label>
            <input type="email" name="from_email" value="<?php echo esc_attr( $data['from_email'] ); ?>" />
          </div>
        <?php endif; ?>

        <div class="tpw-email-row">
          <label><?php esc_html_e('Subject', 'tpw-core'); ?></label>
          <input type="text" name="subject" value="<?php echo esc_attr( $data['subject'] ); ?>" placeholder="<?php esc_attr_e('Subject', 'tpw-core'); ?>" />
        </div>

        <div class="tpw-email-row">
          <label><?php esc_html_e('Message', 'tpw-core'); ?></label>
          <textarea class="tpw-email-message" name="message" rows="8"><?php echo esc_textarea( $data['message'] ); ?></textarea>
        </div>

        <div class="tpw-email-row">
          <label><?php esc_html_e('Attachments', 'tpw-core'); ?></label>
          <input type="file" name="attachments[]" multiple />
          <?php
            $max_mb = isset($data['max_bytes']) ? round( (int)$data['max_bytes'] / (1024*1024) ) : 5;
          ?>
          <small><?php echo esc_html( sprintf( __( 'Allowed: PDF, DOCX, JPG, PNG. Max %dMB each.', 'tpw-core' ), $max_mb ) ); ?></small>
        </div>

        <div class="tpw-email-row">
          <label>
            <input type="checkbox" name="send_copy" value="1" <?php checked( ! empty($data['send_copy']) ); ?> />
            <?php esc_html_e('Send a copy to me', 'tpw-core'); ?>
          </label>
        </div>

        <div id="tpw-email-generic-result" class="tpw-email-result" aria-live="polite"></div>

        <div class="tpw-email-actions">
          <button type="submit" class="button button-primary" id="tpw-email-generic-submit"><?php esc_html_e('Send', 'tpw-core'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>