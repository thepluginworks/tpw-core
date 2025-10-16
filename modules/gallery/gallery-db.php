<?php
/**
 * TPW Core – Gallery Module DB (Phase 2 Schema)
 *
 * Creates the database tables for galleries, images, and categories.
 * No UI or runtime logic is hooked here.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Gallery_DB {
    /**
     * Schema version for migrations.
     */
    public const VERSION = '0.2';

    /**
     * Create/upgrade DB tables using dbDelta.
     * Tables:
     * - {$wpdb->prefix}tpw_galleries
     * - {$wpdb->prefix}tpw_gallery_images
     * - {$wpdb->prefix}tpw_gallery_categories
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tbl_galleries = $wpdb->prefix . 'tpw_galleries';
        $tbl_images    = $wpdb->prefix . 'tpw_gallery_images';
        $tbl_cats      = $wpdb->prefix . 'tpw_gallery_categories';

        $sql_galleries = "CREATE TABLE {$tbl_galleries} (
            gallery_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description TEXT NULL,
            category_id INT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (gallery_id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id),
            KEY created_by (created_by)
        ) {$charset_collate}";

        $sql_images = "CREATE TABLE {$tbl_images} (
            image_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            gallery_id INT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            caption TEXT NULL,
            focus_x FLOAT NULL,
            focus_y FLOAT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (image_id),
            KEY gallery_id (gallery_id),
            KEY attachment_id (attachment_id),
            KEY sort_order (sort_order)
        ) {$charset_collate}";

        $sql_categories = "CREATE TABLE {$tbl_cats} (
            category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description TEXT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (category_id),
            UNIQUE KEY slug (slug),
            KEY sort_order (sort_order)
        ) {$charset_collate}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_galleries );
        dbDelta( $sql_images );
        dbDelta( $sql_categories );

        // Persist schema version for future migrations
        update_option( 'tpw_gallery_db_version', self::VERSION );
    }
}
