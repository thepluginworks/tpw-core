<?php
/**
 * Template: TPW Core – Event Menu Modal
 * Expected: $event_id (int). Renders nothing if no menu is linked.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$event_id = isset( $event_id ) ? absint( $event_id ) : 0;
if ( ! $event_id ) {
    return;
}

$has_fn = function_exists( 'tpw_core_event_has_menu' );
if ( ! $has_fn ) {
    return;
}

$has_menu = tpw_core_event_has_menu( $event_id );
if ( ! $has_menu ) {
    return;
}

$payload = function_exists( 'tpw_core_get_menu_payload' ) ? tpw_core_get_menu_payload( $event_id ) : null;
if ( empty( $payload ) ) {
    return;
}
if ( empty( $payload['menu'] ) ) {
    return;
}

$menu      = $payload['menu'];        // ['id','name','description','number_of_courses','price']
$courses   = $payload['courses'];     // keyed by course_number => ['course_name' => '', 'choices' => [ ['label','description'], ... ]]
$modal_id  = 'tpw-menu-modal-' . $event_id;
$btn_label = apply_filters( 'tpw_core/menu_modal_button_label', __( 'View Menu', 'tpw-core' ), $event_id, $menu );
$title     = apply_filters( 'tpw_core/menu_modal_title', esc_html( $menu['name'] ), $event_id, $menu );

$courses_count = is_array($courses) ? count($courses) : 0;

// Flag to let Core enqueue assets once per page.
if ( function_exists( 'tpw_core_flag_need_ui_assets' ) ) {
    tpw_core_flag_need_ui_assets();
}
?>

<button type="button"
        class="tpw-btn tpw-btn-secondary tpw-menu-modal-trigger"
        data-tpw-open="#<?php echo esc_attr( $modal_id ); ?>">
    <?php echo esc_html( $btn_label ); ?>
</button>

<div class="tpw-modal tpw-menu-modal" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title" hidden>
    <div class="tpw-modal__backdrop" data-tpw-close></div>
    <div class="tpw-modal__dialog" role="document" tabindex="-1">
        <header class="tpw-modal__header">
            <h2 id="<?php echo esc_attr( $modal_id ); ?>-title" class="tpw-modal__title">
                <?php echo esc_html( $title ); ?>
            </h2>
            <button type="button" class="tpw-modal__close tpw-btn tpw-btn-secondary" aria-label="<?php esc_attr_e( 'Close', 'tpw-core' ); ?>" data-tpw-close><?php esc_html_e( 'Close', 'tpw-core' ); ?></button>
        </header>

        <?php if ( ! empty( $menu['description'] ) ) : ?>
            <p class="tpw-menu__description"><?php echo wp_kses_post( nl2br( $menu['description'] ) ); ?></p>
        <?php endif; ?>

        <?php if ( isset( $menu['price'] ) && $menu['price'] !== null ) : ?>
            <p class="tpw-menu__price">
                <strong><?php esc_html_e( 'Price', 'tpw-core' ); ?>:</strong>
                <?php
                $price_value = (float) $menu['price'];
                if ( function_exists( 'wc_price' ) ) {
                    echo esc_html( wc_price( $price_value ) );
                } else {
                    $currency_symbol = function_exists( 'tpw_core_get_currency_symbol' ) ? tpw_core_get_currency_symbol() : '£';
                    echo esc_html( apply_filters( 'tpw_core/menu_modal_price_html', $currency_symbol . number_format( $price_value, 2 ), $price_value, $event_id, $menu ) );
                }
                ?>
            </p>
        <?php endif; ?>

        <div class="tpw-menu__courses">
            <?php foreach ( $courses as $course_number => $course ) : ?>
                <section class="tpw-menu__course">
                    <h3 class="tpw-menu__course-title">
                        <?php
                        $course_heading = $course['course_name'] ?: sprintf( __( 'Course %d', 'tpw-core' ), (int) $course_number );
                        echo esc_html( $course_heading );
                        ?>
                    </h3>

                    <?php if ( ! empty( $course['choices'] ) ) : ?>
                        <ul class="tpw-menu__choices">
                            <?php foreach ( $course['choices'] as $choice ) : ?>
                                <li class="tpw-menu__choice">
                                    <span class="tpw-menu__choice-label"><?php echo esc_html( $choice['label'] ); ?></span>
                                    <?php if ( ! empty( $choice['description'] ) ) : ?>
                                        <div class="tpw-menu__choice-desc"><?php echo wp_kses_post( nl2br( $choice['description'] ) ); ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="tpw-menu__no-choices"><?php esc_html_e( 'Choices to be confirmed.', 'tpw-core' ); ?></p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        
    </div>
</div>