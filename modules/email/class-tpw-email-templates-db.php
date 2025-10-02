<?php
/**
 * Email Templates DB helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Email_Templates_DB {
    const TABLE = 'tpw_email_templates';

    /**
     * Create the email templates table.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_key VARCHAR(191) NOT NULL,
            template_group VARCHAR(191) NOT NULL DEFAULT '',
            template_label VARCHAR(191) NOT NULL DEFAULT '',
            subject_override TEXT NULL,
            body_override LONGTEXT NULL,
            use_logo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY template_key (template_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Fetch a single template override row by key.
     * @return array|null
     */
    public static function get_override( $template_key ) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE template_key = %s", $template_key ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Insert or update an override.
     * Returns true on success.
     */
    public static function upsert_override( $template_key, $group, $label, $subject_override, $body_override, $use_logo ) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        $now = current_time( 'mysql' );

        $existing = self::get_override( $template_key );
        if ( $existing ) {
            $data = [
                'template_group'   => $group,
                'template_label'   => $label,
                'subject_override' => $subject_override,
                'body_override'    => $body_override,
                'use_logo'         => (int) ( $use_logo ? 1 : 0 ),
                'updated_at'       => $now,
            ];
            $where = [ 'template_key' => $template_key ];
            $formats = [ '%s','%s','%s','%s','%d','%s' ];
            return false !== $wpdb->update( $table_name, $data, $where, $formats, [ '%s' ] );
        } else {
            $data = [
                'template_key'     => $template_key,
                'template_group'   => $group,
                'template_label'   => $label,
                'subject_override' => $subject_override,
                'body_override'    => $body_override,
                'use_logo'         => (int) ( $use_logo ? 1 : 0 ),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            $formats = [ '%s','%s','%s','%s','%s','%d','%s','%s' ];
            return false !== $wpdb->insert( $table_name, $data, $formats );
        }
    }

    /**
     * Delete an override.
     */
    public static function delete_override( $template_key ) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        return false !== $wpdb->delete( $table_name, [ 'template_key' => $template_key ], [ '%s' ] );
    }
}
