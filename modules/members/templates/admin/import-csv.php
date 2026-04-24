<?php
defined( 'ABSPATH' ) || exit;
?>
<?php $upload_error = false; ?>

<div class="tpw-member-import-wrapper">
    <h2>Import Members from CSV</h2>
    <p>
    <a href="<?php echo esc_url( TPW_CORE_URL . 'modules/members/assets/sample-members-template.csv' ); ?>" class="tpw-btn tpw-btn-light" role="button">Download Sample CSV Template</a>
    </p>
    <?php if ($upload_error): ?>
        <div class="tpw-error">CSV upload failed. Please try again.</div>
    <?php endif; ?>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $upload_error = true;
        } else {
            $importer = new TPW_Member_CSV_Importer();
            $importer->handle_csv_upload();
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csv_uploaded'])) {
        $importer = new TPW_Member_CSV_Importer();
        $importer->process_mapped_import($_POST);
    } elseif ( isset( $_GET['import_run'] ) ) {
        $importer = new TPW_Member_CSV_Importer();
        $importer->render_saved_password_setup_run( wp_unslash( $_GET['import_run'] ) );
    }
    ?>

    <?php if ( isset( $_GET['tpw_import_password_setup_error'] ) ) : ?>
        <div class="notice notice-error"><p>
            <?php
            $error = sanitize_key( wp_unslash( $_GET['tpw_import_password_setup_error'] ) );
            if ( 'expired' === $error ) {
                esc_html_e( 'The password setup email import run has expired or is no longer available.', 'tpw-core' );
            } else {
                esc_html_e( 'The password setup email import run is invalid.', 'tpw-core' );
            }
            ?>
        </p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="?action=import_csv">
        <div class="form-group">
            <label for="csv_file">Choose CSV File:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        </div>

        <div class="form-group">
            <button type="submit" class="tpw-btn tpw-btn-primary" name="import_csv">Upload and Continue</button>
        </div>
    </form>
</div>