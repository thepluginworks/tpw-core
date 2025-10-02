<?php
/**
 * Manager to retrieve and render email templates with token replacement.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Email_Template_Manager {
    /**
     * Render a template by key with token replacements.
     *
     * @param string $template_key
     * @param array  $token_values  e.g. [ '{fixture-name}' => 'Oxford v Cambridge' ]
     * @return array { subject, body, use_logo }
     */
    public static function get_rendered_template( $template_key, $token_values = [] ) {
        $def = class_exists('TPW_Email_Template_Registry') ? TPW_Email_Template_Registry::get( $template_key ) : null;
        if ( ! $def ) {
            // Unknown template key – return pass-through
            return [
                'subject' => '',
                'body'    => '',
                'use_logo'=> true,
            ];
        }

        $subject = (string) $def['default_subject'];
        $body    = (string) $def['default_body'];
        $use_logo = true;

        // Load override from DB if available
        if ( class_exists('TPW_Email_Templates_DB') ) {
            $ov = TPW_Email_Templates_DB::get_override( $template_key );
            if ( $ov ) {
                if ( isset( $ov['subject_override'] ) && $def['editable_subject'] && $ov['subject_override'] !== null && $ov['subject_override'] !== '' ) {
                    $subject = (string) $ov['subject_override'];
                }
                if ( isset( $ov['body_override'] ) && $def['editable_body'] && $ov['body_override'] !== null && $ov['body_override'] !== '' ) {
                    $body = (string) $ov['body_override'];
                }
                $use_logo = isset( $ov['use_logo'] ) ? (bool) $ov['use_logo'] : true;
            }
        }

        // Token replacement via simple str_replace.
        if ( is_array( $token_values ) && ! empty( $token_values ) ) {
            $search = array_keys( $token_values );
            $replace = array_values( $token_values );
            $subject = str_replace( $search, $replace, $subject );
            $body    = str_replace( $search, $replace, $body );
        }

        return [
            'subject' => $subject,
            'body'    => $body,
            'use_logo'=> (bool) $use_logo,
        ];
    }
}
