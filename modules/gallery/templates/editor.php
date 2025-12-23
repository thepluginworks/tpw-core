<?php
/**
 * Gallery Admin – Full Page Editor
 * Reuses the same form and JS as the modal, for heavy galleries.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'tpw-core' ) );
}

$gallery = isset($gallery) && is_array($gallery) ? $gallery : null;
?>
<div class="tpw-admin-ui tpw-gallery-editor">
  <div class="tpw-admin-header">
    <h1><?php echo $gallery ? esc_html( $gallery['gallery']['title'] ) : esc_html__( 'Edit Gallery', 'tpw-core' ); ?></h1>
    <div class="tpw-row" style="gap:8px;align-items:center;">
      <a href="<?php echo esc_url( home_url( '/gallery-admin/' ) ); ?>" class="tpw-btn tpw-btn-secondary">&larr; <?php esc_html_e('Back to Galleries', 'tpw-core'); ?></a>
    </div>
  </div>

  <div class="tpw-card">
    <?php
      // Render the same form the modal uses, but inline.
      include __DIR__ . '/form.php';
    ?>
  </div>
</div>

<!-- Categories modal (reused) -->
<div id="tpw-categories-modal" class="tpw-modal" aria-hidden="true" style="display:none;">
  <div class="tpw-modal__overlay" data-tpw-categories-modal-close></div>
  <div class="tpw-modal__content" role="dialog" aria-modal="true" aria-labelledby="tpw-categories-modal-title">
    <div class="tpw-modal__header">
      <h2 id="tpw-categories-modal-title" class="tpw-modal__title"><?php esc_html_e('Manage Categories', 'tpw-core'); ?></h2>
      <button type="button" class="tpw-btn tpw-btn-small" id="tpw-categories-modal-close" aria-label="<?php esc_attr_e('Close', 'tpw-core'); ?>">&times;</button>
    </div>
    <div class="tpw-modal__body">
      <div id="tpw-categories-container">
        <?php include __DIR__ . '/categories.php'; ?>
      </div>
    </div>
  </div>
</div>
