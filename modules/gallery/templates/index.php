<?php
/**
 * Gallery Public Index Template
 *
 * Expected vars:
 * - $items: array from tpw_gallery_get_index_items()
 * - $layout: grid|list
 * - $columns: int 1..6
 * - $selected_id: int
 * - $selected_gallery_html: string
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$index_columns = max( 1, min( 6, (int) ( $columns ?? 3 ) ) );
$is_grid = ( $layout ?? 'grid' ) === 'grid';
$has_selection = ( $selected_gallery_html ?? '' ) !== '';
?>
<div class="tpw-gallery-index" id="tpw-gallery-browser">
  <?php if ( $has_selection ) : ?>
    <div class="tpw-gallery-index-selected" id="tpw-gallery-selected">
      <div class="tpw-gallery-index-back-wrap">
        <a class="tpw-btn tpw-btn-secondary tpw-gallery-index-back" href="<?php echo esc_url( tpw_gallery_get_index_url() ); ?>"><?php echo esc_html__( 'Back to Galleries', 'tpw-core' ); ?></a>
      </div>
      <?php echo $selected_gallery_html; ?>
    </div>
  <?php elseif ( $is_grid ) : ?>
    <ul class="tpw-gallery-grid-public tpw-gallery-grid-public--fixed tpw-gallery-index-grid" style="--tpw-gallery-columns: <?php echo (int) $index_columns; ?>;">
      <?php foreach ( $items as $item ) :
        $gallery_id = isset( $item['gallery_id'] ) ? (int) $item['gallery_id'] : 0;
        if ( $gallery_id <= 0 ) continue;
        $title = isset( $item['title'] ) ? (string) $item['title'] : '';
        $image_url = isset( $item['cover_image_url'] ) ? (string) $item['cover_image_url'] : '';
        $image_alt = isset( $item['cover_image_alt'] ) ? (string) $item['cover_image_alt'] : $title;
      ?>
        <li class="tpw-gallery-item tpw-gallery-index-card">
          <a href="<?php echo esc_url( tpw_gallery_get_selection_url( $gallery_id ) ); ?>" class="tpw-gallery-index-link">
            <?php if ( $image_url !== '' ) : ?>
              <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
            <?php else : ?>
              <span class="tpw-gallery-index-placeholder" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="tpw-gallery-index-title"><?php echo esc_html( $title ); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else : ?>
    <ul class="tpw-gallery-list-public tpw-gallery-index-list">
      <?php foreach ( $items as $item ) :
        $gallery_id = isset( $item['gallery_id'] ) ? (int) $item['gallery_id'] : 0;
        if ( $gallery_id <= 0 ) continue;
        $title = isset( $item['title'] ) ? (string) $item['title'] : '';
        $image_url = isset( $item['cover_image_url'] ) ? (string) $item['cover_image_url'] : '';
        $image_alt = isset( $item['cover_image_alt'] ) ? (string) $item['cover_image_alt'] : $title;
      ?>
        <li class="tpw-gallery-list-item tpw-gallery-index-list-item">
          <a href="<?php echo esc_url( tpw_gallery_get_selection_url( $gallery_id ) ); ?>" class="tpw-gallery-index-list-link">
            <?php if ( $image_url !== '' ) : ?>
              <span class="tpw-gallery-list-thumb tpw-gallery-index-list-thumb">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async" />
              </span>
            <?php else : ?>
              <span class="tpw-gallery-list-thumb tpw-gallery-index-list-thumb tpw-gallery-index-placeholder" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="tpw-gallery-list-caption tpw-gallery-index-list-title"><?php echo esc_html( $title ); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>