<?php
/**
 * Gallery Admin – List View
 * @since 0.4.0 Gallery UI
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) { // temporary capability; replace with manage_galleries
    wp_die( esc_html__( 'You do not have permission to access this page.', 'tpw-core' ) );
}

$items = function_exists('tpw_gallery_all_with_counts') ? tpw_gallery_all_with_counts() : [];
$cats  = function_exists('tpw_gallery_get_categories') ? tpw_gallery_get_categories() : [];
?>
<div class="tpw-admin-ui">
  <div class="tpw-admin-header">
    <h1><?php esc_html_e('Galleries', 'tpw-core'); ?></h1>
    <div class="tpw-row" style="gap:8px;align-items:center;">
      <a href="#add" class="tpw-btn tpw-btn-primary" id="tpw-add-gallery-btn"><?php esc_html_e('Add Gallery', 'tpw-core'); ?></a>
      <button type="button" class="tpw-btn tpw-btn-secondary" id="tpw-manage-categories-btn"><?php esc_html_e('Manage Categories', 'tpw-core'); ?></button>
      <a href="<?php echo esc_url( home_url( '/gallery-help/' ) ); ?>" class="tpw-btn tpw-btn-secondary">
        <?php esc_html_e('Help', 'tpw-core'); ?>
      </a>
    </div>
  </div>

  <div class="tpw-card">
    <div class="table-container">
      <div class="table-row table-head">
        <div class="table-cell"><?php esc_html_e('Cat ID', 'tpw-core'); ?></div>
        <div class="table-cell"><?php esc_html_e('Title', 'tpw-core'); ?></div>
        <div class="table-cell"><?php esc_html_e('Category', 'tpw-core'); ?></div>
        <div class="table-cell"><?php esc_html_e('Created', 'tpw-core'); ?></div>
        <div class="table-cell"><?php esc_html_e('Images', 'tpw-core'); ?></div>
        <div class="table-cell"><?php esc_html_e('Actions', 'tpw-core'); ?></div>
      </div>

      <?php if ( empty($items) ) : ?>
        <div class="table-row">
          <div class="table-cell"><?php esc_html_e('No galleries yet.', 'tpw-core'); ?></div>
        </div>
      <?php else: foreach ( $items as $row ) : ?>
        <div class="table-row" data-gallery-id="<?php echo (int) $row['gallery_id']; ?>">
          <div class="table-cell"><?php echo (int) ($row['category_id'] ?? 0); ?></div>
          <div class="table-cell"><?php echo esc_html( $row['title'] ); ?></div>
          <div class="table-cell"><?php echo esc_html( $row['category_name'] ?? '' ); ?></div>
          <div class="table-cell"><?php echo esc_html( tpw_format_datetime( $row['created_at'] ) ); ?></div>
          <div class="table-cell"><?php echo (int) ($row['image_count'] ?? 0); ?></div>
          <div class="table-cell">
            <button class="tpw-btn tpw-gallery-quick-edit tpw-btn-secondary" data-id="<?php echo (int) $row['gallery_id']; ?>"><?php esc_html_e('Quick Edit', 'tpw-core'); ?></button>
            &nbsp;<a class="tpw-btn tpw-btn-primary" href="<?php echo esc_url( add_query_arg( 'gallery_id', (int) $row['gallery_id'], home_url( '/gallery-admin/' ) ) ); ?>"><?php esc_html_e('Edit', 'tpw-core'); ?></a>
            &nbsp;<button class="tpw-btn tpw-gallery-delete tpw-btn-danger" data-id="<?php echo (int) $row['gallery_id']; ?>"><?php esc_html_e('Delete', 'tpw-core'); ?></button>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  
</div>

<!-- Modal: Add/Edit Gallery -->
<div id="tpw-gallery-modal" class="tpw-modal" aria-hidden="true" style="display:none;">
  <div class="tpw-modal__overlay" data-tpw-modal-close></div>
  <div class="tpw-modal__content" role="dialog" aria-modal="true" aria-labelledby="tpw-gallery-modal-title">
    <div class="tpw-modal__header">
      <h2 id="tpw-gallery-modal-title" class="tpw-modal__title"><?php esc_html_e('Add Gallery', 'tpw-core'); ?></h2>
      <button type="button" class="tpw-btn tpw-btn-small" id="tpw-gallery-modal-close" aria-label="<?php esc_attr_e('Close', 'tpw-core'); ?>">&times;</button>
    </div>
    <div class="tpw-modal__body">
      <div id="tpw-gallery-form-container">
        <?php
          // Inline include of the form template; defaults to empty (add) state
          $gallery = null;
          include __DIR__ . '/form.php';
        ?>
      </div>
    </div>
  </div>
  </div>

<!-- Modal: Manage Categories -->
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
