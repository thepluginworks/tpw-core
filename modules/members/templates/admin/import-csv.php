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
    }
    ?>

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