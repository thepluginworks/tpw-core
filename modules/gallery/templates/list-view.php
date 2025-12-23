<?php
/**
 * Gallery Public List View Template
 * Simple vertical list of images with captions; links open lightbox.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$col = max(1, min(6, (int) ($columns ?? 3))); // unused in list view but kept for consistency
?>
<?php if ( ! empty( $show_categories ) ) : ?>
  <div class="tpw-gallery-toolbar">
    <?php echo do_shortcode('[tpw_gallery_categories]'); ?>
  </div>
<?php endif; ?>
<?php foreach ( $items as $item ) :
  $g = $item['gallery'];
  $images = $item['images'];
  if ( empty( $images ) ) continue;

  $per_page = isset( $per_page ) ? max( 0, (int) $per_page ) : 0;
  $paging_enabled = ( $per_page > 0 );
  $gid = (int) ( $g['gallery_id'] ?? 0 );
  $qv = $gid > 0 ? ( 'tpw_gallery_page_' . $gid ) : '';
  $page = 1;
  if ( $paging_enabled && $qv !== '' ) {
    $page = isset( $_GET[ $qv ] ) ? max( 1, (int) $_GET[ $qv ] ) : 1;
  }
  $total_images = is_array( $images ) ? count( $images ) : 0;
  $total_pages = ( $paging_enabled && $total_images > 0 ) ? (int) ceil( $total_images / $per_page ) : 1;
  if ( $paging_enabled && $total_pages > 1 && $page > $total_pages ) $page = $total_pages;
  $offset = ( $paging_enabled ? ( ( $page - 1 ) * $per_page ) : 0 );
  $images_to_show = ( $paging_enabled ? array_slice( $images, $offset, $per_page ) : $images );
?>
  <div class="tpw-gallery-public-block">
    <?php if ( ! empty( $g['title'] ) ) : ?>
      <h3 class="tpw-gallery-public-title"><?php echo esc_html( $g['title'] ); ?></h3>
    <?php endif; ?>
    <?php if ( ! empty( $g['description'] ) ) : ?>
      <div class="tpw-gallery-public-desc"><?php echo wp_kses_post( wpautop( $g['description'] ) ); ?></div>
    <?php endif; ?>
    <ul class="tpw-gallery-list-public">
      <?php foreach ( $images_to_show as $img ) :
        $aid = isset($img['attachment_id']) ? (int) $img['attachment_id'] : 0;
        if ( $aid <= 0 ) continue;
        $thumb = wp_get_attachment_image_src( $aid, 'thumbnail' );
        $full  = wp_get_attachment_image_src( $aid, 'full' );
        $turl  = is_array($thumb) ? $thumb[0] : '';
        $furl  = is_array($full)  ? $full[0]  : '';
        $cap   = isset($img['caption']) && $img['caption'] !== '' ? (string) $img['caption'] : get_the_title( $aid );
        $fx = isset($img['focus_x']) && $img['focus_x'] !== null ? max(0.0, min(1.0, (float)$img['focus_x'])) : null;
        $fy = isset($img['focus_y']) && $img['focus_y'] !== null ? max(0.0, min(1.0, (float)$img['focus_y'])) : null;
        $style = '';
        if ( $fx !== null && $fy !== null ) {
          $style = '--focus-x:' . ( $fx * 100 ) . '%; --focus-y:' . ( $fy * 100 ) . '%;';
        }
      ?>
        <li class="tpw-gallery-list-item" style="<?php echo esc_attr( $style ); ?>">
          <a href="<?php echo esc_url( $furl ); ?>" class="tpw-gallery-lightbox tpw-gallery-list-thumb" data-caption="<?php echo esc_attr( $cap ); ?>" data-group="gallery-<?php echo (int) $g['gallery_id']; ?>">
            <img src="<?php echo esc_url( $turl ); ?>" alt="<?php echo esc_attr( $cap ); ?>" loading="lazy" decoding="async" />
          </a>
          <?php if ( $cap ) : ?><div class="tpw-gallery-list-caption"><?php echo esc_html( $cap ); ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ( $paging_enabled && $total_pages > 1 && $qv !== '' ) :
      $base = add_query_arg( null, null );
      $prev = max( 1, $page - 1 );
      $next = min( $total_pages, $page + 1 );
      $prev_url = $prev <= 1 ? remove_query_arg( $qv, $base ) : add_query_arg( $qv, $prev, $base );
      $next_url = add_query_arg( $qv, $next, $base );
    ?>
      <div class="tpw-gallery-pagination" aria-label="Gallery pagination">
        <a class="tpw-btn tpw-btn-light small<?php echo $page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url( $prev_url ); ?>" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>"<?php echo $page <= 1 ? ' tabindex="-1"' : ''; ?>><?php echo esc_html__( 'Previous', 'tpw-core' ); ?></a>
        <span class="tpw-btn tpw-btn-light small disabled tpw-gallery-pagination-status" aria-hidden="true"><?php echo (int) $page; ?>/<?php echo (int) $total_pages; ?></span>
        <a class="tpw-btn tpw-btn-light small<?php echo $page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url( $next_url ); ?>" aria-disabled="<?php echo $page >= $total_pages ? 'true' : 'false'; ?>"<?php echo $page >= $total_pages ? ' tabindex="-1"' : ''; ?>><?php echo esc_html__( 'Next', 'tpw-core' ); ?></a>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
