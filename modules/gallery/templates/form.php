<?php
/**
 * Gallery Admin – Form View
 * @since 0.4.0 Gallery UI
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) { // temporary capability; replace with manage_galleries
    wp_die( esc_html__( 'You do not have permission to access this page.', 'tpw-core' ) );
}

$cats = function_exists('tpw_gallery_get_categories') ? tpw_gallery_get_categories() : [];
$gallery = isset($gallery) && is_array($gallery) ? $gallery : null; // allow preloaded
?>
<div class="tpw-admin-ui">
  <div class="tpw-card">
    <form id="tpw-gallery-form">
      <input type="hidden" name="gallery_id" value="<?php echo $gallery ? (int) $gallery['gallery']['gallery_id'] : 0; ?>">
      <div class="tpw-field">
        <label><?php esc_html_e('Title', 'tpw-core'); ?></label>
        <input type="text" name="title" value="<?php echo $gallery ? esc_attr($gallery['gallery']['title']) : ''; ?>" required>
      </div>
      <div class="tpw-field">
        <label><?php esc_html_e('Description', 'tpw-core'); ?></label>
        <textarea name="description" rows="3"><?php echo $gallery ? esc_textarea($gallery['gallery']['description']) : ''; ?></textarea>
      </div>
      <div class="tpw-field">
        <label><?php esc_html_e('Category', 'tpw-core'); ?></label>
        <select name="category_id">
          <option value="0"><?php esc_html_e('Uncategorised', 'tpw-core'); ?></option>
          <?php foreach ( $cats as $c ) : ?>
            <option value="<?php echo (int) $c['category_id']; ?>" <?php if ( $gallery && (int)$gallery['gallery']['category_id'] === (int)$c['category_id'] ) echo 'selected'; ?>>
              <?php echo esc_html( $c['name'] ); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="tpw-field">
        <label><?php esc_html_e('Images', 'tpw-core'); ?></label>
        <div class="tpw-row" style="gap:8px; align-items:center; position:relative;">
          <button type="button" class="tpw-btn tpw-btn-primary" id="tpw-add-images-menu-btn" aria-haspopup="true" aria-expanded="false">
            <?php esc_html_e('Add Images', 'tpw-core'); ?>
          </button>
          <div id="tpw-add-images-menu" class="tpw-dropdown" role="menu" aria-hidden="true" style="display:none;">
            <button type="button" class="tpw-btn tpw-btn-secondary tpw-dropdown__item" data-action="library"><?php esc_html_e('From Media Library', 'tpw-core'); ?></button>
            <button type="button" class="tpw-btn tpw-btn-secondary tpw-dropdown__item" data-action="upload"><?php esc_html_e('Upload New Files', 'tpw-core'); ?></button>
          </div>
          <input type="file" id="tpw-gallery-file" accept="image/*" multiple style="display:none;" />
        </div>
        <ul id="tpw-gallery-thumbs" class="tpw-grid">
          <?php if ( $gallery && ! empty( $gallery['images'] ) ) : foreach ( $gallery['images'] as $img ) :
            $aid = (int) ( $img['attachment_id'] ?? 0 );
            $full = $aid ? wp_get_attachment_image_src( $aid, 'full' ) : null;
            $thumb = $aid ? wp_get_attachment_image_src( $aid, 'thumbnail' ) : null;
            $full_url = $full && is_array($full) ? $full[0] : ( $img['url'] ?? '' );
            $thumb_url = $thumb && is_array($thumb) ? $thumb[0] : $full_url;
            $cap = get_post_field( 'post_title', $aid );
          ?>
            <li data-image-id="<?php echo (int) $img['image_id']; ?>">
              <?php if ( $full_url ) : ?>
                <a href="<?php echo esc_url( $full_url ); ?>" class="tpw-gallery-lightbox tpw-thumb" data-caption="<?php echo esc_attr( $cap ); ?>">
                  <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
                </a>
              <?php else : ?>
                <div class="tpw-thumb-placeholder">#</div>
              <?php endif; ?>
              <div class="tpw-row" style="gap:6px; width:100%;">
                <button type="button" class="tpw-btn tpw-btn-gallery tpw-btn-secondary tpw-gallery-remove-image" data-image-id="<?php echo (int) $img['image_id']; ?>"><?php esc_html_e('Remove', 'tpw-core'); ?></button>
                <button type="button" class="tpw-btn tpw-btn-gallery tpw-btn-danger tpw-gallery-remove-image-perm" data-image-id="<?php echo (int) $img['image_id']; ?>"><?php esc_html_e('Delete permanently', 'tpw-core'); ?></button>
              </div>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>

      <div class="tpw-actions">
        <button type="submit" class="tpw-btn tpw-btn-primary"><?php esc_html_e('Save Gallery', 'tpw-core'); ?></button>
        <button type="button" class="tpw-btn" id="tpw-gallery-cancel"><?php esc_html_e('Cancel', 'tpw-core'); ?></button>
      </div>
    </form>
  </div>
</div>
