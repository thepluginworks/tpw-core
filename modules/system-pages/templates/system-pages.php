<?php
/** @var void */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'TPW_Core_System_Pages' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'System Pages manager not loaded.', 'tpw-core' ) . '</p></div>';
    return;
}

// Pull any persisted notices
settings_errors();

$rows = TPW_Core_System_Pages::get_all();

// Enqueue TPW button styles if available
if ( defined('TPW_CORE_URL') ) {
    $css = TPW_CORE_URL . 'assets/css/tpw-buttons.css';
    wp_enqueue_style( 'tpw-buttons', $css, [], null );
}

$admin_post = admin_url( 'admin-post.php' );
$base_url   = admin_url( 'options-general.php?page=tpw-core-settings&tab=system-pages' );
?>
<div class="tpw-system-pages">
    <p><?php echo esc_html__( 'Registered TPW system pages across plugins. This list does not auto-create WordPress pages. Use Recreate to restore a missing page for an existing slug.', 'tpw-core' ); ?></p>

    <?php if ( empty( $rows ) ) : ?>
        <p><em><?php echo esc_html__( 'No system pages registered yet.', 'tpw-core' ); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Linked WP Page', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Plugin', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Required', 'tpw-core' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'tpw-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $r ) :
                    $title = $r->title ?: $r->slug;
                    $slug  = $r->slug;
                    $pid   = (int) $r->wp_page_id;
                    $plugin = $r->plugin;
                    $required = (int) $r->required;

                    $page_obj = $pid ? get_post( $pid ) : null;
                    $is_live = false;
                    $perm = '';
                    if ( $page_obj && $page_obj->post_type === 'page' && $page_obj->post_status === 'publish' ) {
                        $is_live = true;
                        $perm = get_permalink( $pid );
                    }
                ?>
                <tr>
                    <td><?php echo esc_html( $title ); ?></td>
                    <td><code><?php echo esc_html( $slug ); ?></code></td>
                    <td>
                        <?php if ( $pid > 0 ) : ?>
                            <div>
                                <strong>#<?php echo (int) $pid; ?></strong>
                                <?php if ( $perm ) : ?>
                                    <br /><a href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $perm ); ?></a>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:4px;">
                                <?php if ( $perm ) : ?>
                                    <a class="button button-small" href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
                                <?php endif; ?>
                                <?php if ( $page_obj ) : ?>
                                    <a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid, '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'tpw-core' ); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <em><?php esc_html_e( 'Not linked', 'tpw-core' ); ?></em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $is_live ) : ?>
                            <span style="color:#008a20;">✅ <?php esc_html_e( 'Exists', 'tpw-core' ); ?></span>
                        <?php else : ?>
                            <span style="color:#b52727;">❌ <?php esc_html_e( 'Missing', 'tpw-core' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $plugin ); ?></td>
                    <td>
                        <?php if ( $required ) : ?>
                            <span class="tpw-badge tpw-badge-required" style="background:#0b6cad;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;">Required</span>
                        <?php else : ?>
                            <span class="tpw-badge" style="background:#e1e1e1;color:#333;padding:2px 6px;border-radius:4px;font-size:11px;">Optional</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $is_live ) : ?>
                            <?php if ( $perm ) : ?>
                                <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
                            <?php endif; ?>
                            <?php if ( $page_obj ) : ?>
                                <a class="tpw-btn tpw-btn-primary" href="<?php echo esc_url( get_edit_post_link( $pid, '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'tpw-core' ); ?></a>
                            <?php endif; ?>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url( $admin_post ); ?>" style="display:inline;">
                                <?php wp_nonce_field( 'tpw_system_pages_action', 'tpw_sys_pages_nonce' ); ?>
                                <input type="hidden" name="action" value="tpw_system_pages_action" />
                                <input type="hidden" name="op" value="recreate" />
                                <input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
                                <button type="submit" class="tpw-btn tpw-btn-primary" onclick="return confirm('<?php echo esc_js( sprintf( __( "Recreate the '%s' page?", 'tpw-core' ), $title ) ); ?>');"><?php esc_html_e( 'Recreate', 'tpw-core' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
