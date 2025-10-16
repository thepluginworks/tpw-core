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
            <tbody id="tpw-system-pages-table-body">
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
                <tr data-slug="<?php echo esc_attr( $slug ); ?>">
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
                        <?php $ajax_nonce = wp_create_nonce( 'tpw_system_pages_ajax' ); ?>
                        <button class="tpw-btn tpw-btn-secondary js-tpw-sp-unlink" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Unlink', 'tpw-core' ); ?></button>
                        <button class="tpw-btn tpw-btn-primary js-tpw-sp-recreate" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Recreate', 'tpw-core' ); ?></button>
                        <?php if ( $is_live && $perm ) : ?>
                            <a class="tpw-btn" href="<?php echo esc_url( $perm ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function(){
    function on(action, handler){ document.addEventListener(action, handler); }
    function closest(el, sel){ while (el && el.nodeType===1) { if (el.matches(sel)) return el; el = el.parentElement; } return null; }
    function ajax(url, data){
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: new URLSearchParams(data).toString()
        }).then(function(r){ return r.json(); });
    }
    var tbody = document.getElementById('tpw-system-pages-table-body');
    if (!tbody) return;
    tbody.addEventListener('click', function(e){
        var btn = closest(e.target, '.js-tpw-sp-unlink, .js-tpw-sp-recreate');
        if (!btn) return;
        e.preventDefault();
        var slug = btn.getAttribute('data-slug');
        var nonce = btn.getAttribute('data-nonce');
        var op = btn.classList.contains('js-tpw-sp-unlink') ? 'unlink' : 'recreate';
        if (op==='recreate' && !confirm('Recreate the page for slug \''+slug+'\'?')) return;
        var row = closest(btn, 'tr[data-slug]');
        var url = (window.ajaxurl || '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>');
        btn.disabled = true;
        ajax(url, { action: op==='unlink' ? 'tpw_system_page_unlink' : 'tpw_system_page_recreate', slug: slug, nonce: nonce })
            .then(function(res){
                if (res && res.success && res.data && res.data.rowHtml) {
                    var wrapper = document.createElement('tbody');
                    wrapper.innerHTML = res.data.rowHtml.trim();
                    var newRow = wrapper.querySelector('tr');
                    if (row && newRow) {
                        row.parentNode.replaceChild(newRow, row);
                    }
                } else {
                    alert((res && res.data) ? (res.data.message || 'Operation failed') : 'Operation failed');
                }
            })
            .catch(function(){ alert('Request failed'); })
            .finally(function(){ btn.disabled = false; });
    });
})();
</script>
