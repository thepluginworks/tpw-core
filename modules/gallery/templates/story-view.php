<?php
/**
 * Gallery Public Story/Carousel View Template (inline, not a lightbox)
 * Shows one image at a time with Prev/Next, swipe, and keyboard support.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$base = trailingslashit( TPW_CORE_URL ) . 'modules/gallery/';
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
?>
  <div class="tpw-gallery-public-block">
    <?php if ( ! empty( $g['title'] ) ) : ?>
      <h3 class="tpw-gallery-public-title"><?php echo esc_html( $g['title'] ); ?></h3>
    <?php endif; ?>
    <?php if ( ! empty( $g['description'] ) ) : ?>
      <div class="tpw-gallery-public-desc"><?php echo wp_kses_post( wpautop( $g['description'] ) ); ?></div>
    <?php endif; ?>

    <?php
      // Precompute image data in order by existing sort_order
      $slides = [];
      foreach ( $images as $img ) {
        $aid = isset($img['attachment_id']) ? (int) $img['attachment_id'] : 0;
        if ( $aid <= 0 ) continue;
        $large = wp_get_attachment_image_src( $aid, 'large' );
        $full  = wp_get_attachment_image_src( $aid, 'full' );
        $url   = is_array($large) ? $large[0] : ( is_array($full) ? $full[0] : '' );
        $w     = is_array($large) ? (int) $large[1] : ( is_array($full) ? (int) $full[1] : 0 );
        $h     = is_array($large) ? (int) $large[2] : ( is_array($full) ? (int) $full[2] : 0 );
        $cap   = isset($img['caption']) && $img['caption'] !== '' ? (string) $img['caption'] : get_the_title( $aid );
        $slides[] = [ 'url' => $url, 'w' => $w, 'h' => $h, 'cap' => $cap ];
      }
      if ( empty( $slides ) ) continue;
      $gid = (int) $g['gallery_id'];
    ?>

    <div class="tpw-gallery-story" data-gallery-id="<?php echo $gid; ?>" tabindex="0" aria-label="Image carousel">
      <div class="tpw-gallery-story-viewport">
        <button class="tpw-gallery-story-nav prev" type="button" aria-label="Previous image">&#10094;</button>
        <img class="tpw-gallery-story-image" src="<?php echo esc_url( $slides[0]['url'] ); ?>" alt="<?php echo esc_attr( $slides[0]['cap'] ); ?>" decoding="async"<?php echo $slides[0]['w']>0 && $slides[0]['h']>0 ? ' width="' . (int) $slides[0]['w'] . '" height="' . (int) $slides[0]['h'] . '"' : ''; ?> />
        <button class="tpw-gallery-story-nav next" type="button" aria-label="Next image">&#10095;</button>
      </div>
      <div class="tpw-gallery-story-caption"><?php echo esc_html( $slides[0]['cap'] ); ?></div>
      <div class="tpw-gallery-story-meta" aria-hidden="true">
        <span class="tpw-gallery-story-counter"><span class="current">1</span>/<span class="total"><?php echo count( $slides ); ?></span></span>
      </div>
      <script type="application/json" class="tpw-gallery-story-data"><?php echo wp_json_encode( $slides ); ?></script>
    </div>
  </div>
<?php endforeach; ?>

<script src="<?php echo esc_url( $base . 'assets/story.js' ); ?>" defer></script>
