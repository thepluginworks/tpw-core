<?php
$resolve_workspace_url = static function( $slug, $workspace ) {
    if ( class_exists( 'TPW_Core_System_Pages' ) && method_exists( 'TPW_Core_System_Pages', 'get_permalink' ) ) {
        $permalink = (string) TPW_Core_System_Pages::get_permalink( $slug );
        if ( '' !== $permalink ) {
            return $permalink;
        }

        $portal_url = (string) TPW_Core_System_Pages::get_permalink( 'flexiclub' );
        if ( '' !== $portal_url ) {
            return add_query_arg( 'workspace', $workspace, $portal_url );
        }
    }

    return '';
};

$menu_management_url = $resolve_workspace_url( 'menu-management', 'menu-management' );
$archival_system_url = $resolve_workspace_url( 'archival-system', 'archival-system' );
$upload_pages_url    = TPW_Control_UI::menu_url( 'upload-pages' );
$menu_manager_url    = TPW_Control_UI::menu_url( 'menu-manager' );
?>
<div class="tpw-control-dashboard tpw-control-dashboard--legacy-workspace">
    <span class="tpw-badge"><?php echo esc_html__( 'Legacy Workspace', 'tpw-core' ); ?></span>
    <h3><?php echo esc_html__( 'FlexiClub Control has been split into separate FE workspaces', 'tpw-core' ); ?></h3>
    <p><?php echo esc_html__( 'Use the new Menu Management and Archival System workspaces for day-to-day front-end operations. This legacy page remains available during the transition so existing links, pages, and shortcodes continue to work.', 'tpw-core' ); ?></p>
    <div class="tpw-control-quicklinks">
        <?php if ( '' !== $menu_management_url ) : ?>
            <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $menu_management_url ); ?>"><?php echo esc_html__( 'Open Menu Management', 'tpw-core' ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== $archival_system_url ) : ?>
            <a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $archival_system_url ); ?>"><?php echo esc_html__( 'Open Archival System', 'tpw-core' ); ?></a>
        <?php endif; ?>
    </div>
    <p><?php echo esc_html__( 'Legacy quick links remain available below while the older combined workspace is phased into transition status.', 'tpw-core' ); ?></p>
    <div class="tpw-control-quicklinks">
        <a class="tpw-btn tpw-btn-light" href="<?php echo esc_url( $upload_pages_url ); ?>"><?php echo esc_html__( 'Legacy Upload Pages', 'tpw-core' ); ?></a>
        <a class="tpw-btn tpw-btn-light" href="<?php echo esc_url( $menu_manager_url ); ?>"><?php echo esc_html__( 'Legacy Menu Manager', 'tpw-core' ); ?></a>
    </div>
</div>
