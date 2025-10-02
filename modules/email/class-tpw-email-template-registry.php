<?php
/**
 * Registry for email templates. Plugins register their templates here during init.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Email_Template_Registry {
    /** @var array<string,array> */
    protected static $templates = [];

    /**
     * Register a template definition.
     *
     * @param array $t { key, group, label, default_subject, default_body, editable_subject, editable_body, placeholders }
     */
    public static function register_template( $t ) {
        if ( ! is_array( $t ) ) return;
        $key_raw = isset( $t['key'] ) ? (string) $t['key'] : '';
        $key = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $key_raw ) );
        if ( ! $key ) return;
        $group = isset( $t['group'] ) ? sanitize_text_field( $t['group'] ) : 'core';
        $label = isset( $t['label'] ) ? sanitize_text_field( $t['label'] ) : $key;
        $default_subject = isset( $t['default_subject'] ) ? (string) $t['default_subject'] : '';
        $default_body    = isset( $t['default_body'] ) ? (string) $t['default_body'] : '';
        $editable_subject = ! empty( $t['editable_subject'] );
        $editable_body    = ! empty( $t['editable_body'] );
        $placeholders     = isset( $t['placeholders'] ) && is_array( $t['placeholders'] ) ? $t['placeholders'] : [];

        self::$templates[ $key ] = [
            'key'              => $key,
            'group'            => $group,
            'label'            => $label,
            'default_subject'  => $default_subject,
            'default_body'     => $default_body,
            'editable_subject' => (bool) $editable_subject,
            'editable_body'    => (bool) $editable_body,
            'placeholders'     => $placeholders,
        ];
    }

    /**
     * Get a single registered template by key.
     */
    public static function get( $key ) {
        $key = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $key ) );
        return isset( self::$templates[ $key ] ) ? self::$templates[ $key ] : null;
    }

    /**
     * Get all registered templates grouped by group key.
     * @return array<string,array> group => [ templates ]
     */
    public static function all_grouped() {
        $grouped = [];
        foreach ( self::$templates as $t ) {
            $g = $t['group'] ?: 'core';
            if ( ! isset( $grouped[ $g ] ) ) $grouped[ $g ] = [];
            $grouped[ $g ][] = $t;
        }
        ksort( $grouped );
        return $grouped;
    }

    /**
     * Get all registered templates flat list.
     */
    public static function all() {
        return self::$templates;
    }
}
