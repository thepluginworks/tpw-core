<?php
/**
 * Gallery Public Grid Template
 * @since 0.5.0 Public Display
 * @updated 0.7.0 New CSS grid layout, captions, and lightbox nav
 *
 * Expected vars:
 * - $items: array of galleries, each from tpw_gallery_get() => ['gallery'=>[], 'images'=>[]]
 * - $columns: int 1..6 (used to hint initial grid density; responsive takes over)
 * - $show_categories: bool Whether to show the categories toolbar (optional)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$col = max(1, min(6, (int) ($columns ?? 3)));
?>

<?php if ( ! empty( $show_categories ) ) : ?>
  <div class="tpw-gallery-toolbar">
    <?php echo do_shortcode('[tpw_gallery_categories]'); // optional categories browsing ?>
  </div>
<?php endif; ?>

<?php foreach ( $items as $item ) :
  $g = $item['gallery'];
  $images = $item['images'];
  if ( empty( $images ) ) continue;
?>
  <div class="tpw-gallery-public-block">
    <?php if ( ! empty( $g['title'] ) ) : ?>
      <h3 class="tpw-gallery-public-title"><?php echo esc_html( $g['title'] ); ?></h3>
    <?php endif; ?>
    <?php if ( ! empty( $g['description'] ) ) : ?>
      <div class="tpw-gallery-public-desc"><?php echo wp_kses_post( wpautop( $g['description'] ) ); ?></div>
    <?php endif; ?>

    <ul class="tpw-gallery-grid-public" data-columns="<?php echo (int) $col; ?>">
      <?php foreach ( $images as $img ) :
        $aid = isset($img['attachment_id']) ? (int) $img['attachment_id'] : 0;
        if ( $aid <= 0 ) continue;
  $thumb = wp_get_attachment_image_src( $aid, 'medium' );
        $full  = wp_get_attachment_image_src( $aid, 'full' );
        $turl  = is_array($thumb) ? $thumb[0] : '';
        $furl  = is_array($full)  ? $full[0]  : '';
  $tw    = is_array($thumb) ? (int) $thumb[1] : 0;
  $th    = is_array($thumb) ? (int) $thumb[2] : 0;
        // Prefer DB/attachment caption; fallback to attachment title
        $cap   = isset($img['caption']) && $img['caption'] !== '' ? (string) $img['caption'] : get_the_title( $aid );
      ?>
        <?php
          $fx = isset($img['focus_x']) && $img['focus_x'] !== null ? max(0.0, min(1.0, (float)$img['focus_x'])) : null;
          $fy = isset($img['focus_y']) && $img['focus_y'] !== null ? max(0.0, min(1.0, (float)$img['focus_y'])) : null;
          $style = '';
          if ( $fx !== null && $fy !== null ) {
            $style = '--focus-x:' . ( $fx * 100 ) . '; --focus-y:' . ( $fy * 100 ) . ';';
          }
        ?>
        <li class="tpw-gallery-item" style="<?php echo esc_attr( $style ); ?>">
          <a href="<?php echo esc_url( $furl ); ?>" class="tpw-gallery-lightbox" data-caption="<?php echo esc_attr( $cap ); ?>" data-group="gallery-<?php echo (int) $g['gallery_id']; ?>">
            <img src="<?php echo esc_url( $turl ); ?>" alt="<?php echo esc_attr( $cap ); ?>" loading="lazy" decoding="async"<?php echo $tw>0 && $th>0 ? ' width="' . (int) $tw . '" height="' . (int) $th . '"' : ''; ?> />
          </a>
          <?php if ( $cap ) : ?><div class="tpw-gallery-caption"><?php echo esc_html( $cap ); ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>

