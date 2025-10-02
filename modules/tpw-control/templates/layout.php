<?php
/** @var array $sections */
/** @var string $current */
?>
<div class="tpw-control">
    <aside class="tpw-control__sidebar">
        <div class="tpw-control__header">
            <h2 class="tpw-control__title"><?php echo esc_html__( 'TPW Control', 'tpw-core' ); ?></h2>
        </div>
        <ul class="tpw-control__menu">
            <?php foreach ( $sections as $key => $section ) :
                if ( ! TPW_Control_UI::section_is_visible( $section ) ) continue;
                $url = TPW_Control_UI::menu_url( $section['key'] );
                $active = $current === $section['key'] ? ' is-active' : '';
                $label = $section['label'];
            ?>
                <li>
                    <a class="<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $url ); ?>">
                        <?php if ( ! empty( $section['icon'] ) ) : ?>
                            <span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" style="margin-right:6px"></span>
                        <?php endif; ?>
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php do_action( 'tpw_control/sidebar_after_menu', $sections, $current ); ?>
        </ul>
    </aside>
    <main class="tpw-control__content">
        <?php TPW_Control_Router::render_content_only(); ?>
    </main>
</div>
