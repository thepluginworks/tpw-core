<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Expect: $sources (array), $active_type (string)
?>
<aside class="tpw-sidebar" aria-label="Payments navigation">
  <div class="tpw-sidebar-heading" role="heading" aria-level="3"><?php echo esc_html__( 'Payment types', 'tpw-core' ); ?></div>
  <nav>
    <ul class="tpw-menu">
      <?php foreach ( $sources as $slug => $source ) :
        $is_active = ( $slug === $active_type );
        $url = add_query_arg( [ 'section' => 'payments', 'type' => $slug ] );
      ?>
        <li>
          <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $is_active ? 'is-active' : ''; ?>" <?php if ( $is_active ) echo 'aria-current="page"'; ?>>
            <?php echo esc_html( $source['label'] ); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
</aside>
