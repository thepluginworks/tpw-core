<?php
?>
<div class="tpw-control-dashboard">
    <h3><?php echo esc_html__( 'Welcome to TPW Control', 'tpw-core' ); ?></h3>
    <p><?php echo esc_html__( 'Use the menu to access admin tools such as Upload Pages and Menu Manager. External plugins can add their own sections here.', 'tpw-core' ); ?></p>
    <div class="tpw-control-quicklinks">
        <a class="button" href="<?php echo esc_url( TPW_Control_UI::menu_url('upload-pages') ); ?>"><?php echo esc_html__( 'Manage Upload Pages', 'tpw-core' ); ?></a>
        <a class="button" href="<?php echo esc_url( TPW_Control_UI::menu_url('menu-manager') ); ?>"><?php echo esc_html__( 'Front-end Menu Manager', 'tpw-core' ); ?></a>
    </div>
</div>
