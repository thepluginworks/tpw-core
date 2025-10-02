<?php
// Fetch the uploaded CSV file data (this would typically come from session or temp storage)
$csv_headers = isset($_SESSION['tpw_import_csv_headers']) ? $_SESSION['tpw_import_csv_headers'] : [];
$member_fields = isset($_SESSION['tpw_import_member_fields']) ? $_SESSION['tpw_import_member_fields'] : [];

if (empty($csv_headers) || empty($member_fields)) {
    echo '<div class="notice notice-error"><p>Missing import data. Please re-upload your CSV file.</p></div>';
    return;
}
?>

<div class="tpw-import-mapping">
    <h2>Map CSV Columns to Member Fields</h2>
    <form method="post" action="">
        <input type="hidden" name="tpw_import_mapping_nonce" value="<?php echo wp_create_nonce('tpw_import_mapping'); ?>">
        <table class="form-table widefat striped">
            <thead>
                <tr>
                    <th>CSV Column</th>
                    <th>Map To Member Field</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($csv_headers as $column): ?>
                    <tr>
                        <td><?php echo esc_html($column); ?></td>
                        <td>
                            <select name="field_map[<?php echo esc_attr($column); ?>]">
                                <option value="">-- Select Field --</option>
                                <?php foreach ($member_fields as $field_key => $field_label): ?>
                                    <option value="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <button type="submit" class="tpw-btn tpw-btn-primary">Continue Import</button>
        </p>
    </form>
</div>