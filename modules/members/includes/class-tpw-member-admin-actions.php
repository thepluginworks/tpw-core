<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class TPW_Member_Admin_Actions {
    public static function init() {
        // Secure admin-post handler (logged-in only)
        add_action( 'admin_post_tpw_create_wp_user', [ __CLASS__, 'handle_create_wp_user' ] );

        // Extend the Edit Member admin form with a minimal Household section (UI only).
        add_action( 'tpw_members_admin_form_extra_fields', [ __CLASS__, 'render_household_section' ], 10, 4 );

        // Household assignment actions (admin-post handlers).
        add_action( 'admin_post_tpw_members_household_create', [ __CLASS__, 'handle_household_create' ] );
        add_action( 'admin_post_tpw_members_household_assign', [ __CLASS__, 'handle_household_assign' ] );
    }

    /**
     * Determine if the current user is allowed to manage members.
     * Matches TPW_Member_Form_Handler::user_can_manage() behaviour.
     *
     * @return bool
     */
    protected static function user_can_manage() {
        if ( ! class_exists( 'TPW_Member_Access' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-access.php';
        }

        return TPW_Member_Access::can_manage_members_current();
    }

    /**
     * Check whether household UI is enabled.
     *
     * Default is OFF.
     *
     * @return bool
     */
    protected static function households_enabled() {
        return '1' === get_option( 'tpw_members_enable_households', '0' );
    }

    /**
     * Build a display name for a member row.
     *
     * @param object $member Member row.
     * @return string
     */
    protected static function get_member_display_name( $member ) {
        if ( function_exists( 'tpw_members_get_display_name' ) ) {
            return (string) tpw_members_get_display_name( $member );
        }
        $first   = ( is_object( $member ) && isset( $member->first_name ) ) ? trim( (string) $member->first_name ) : '';
        $surname = ( is_object( $member ) && isset( $member->surname ) ) ? trim( (string) $member->surname ) : '';
        $name    = trim( $surname . ( '' !== $surname && '' !== $first ? ', ' : '' ) . $first );
        return '' !== $name ? $name : '—';
    }

    /**
     * Get all primary household members within a society.
     *
     * @param int $society_id Society ID.
     * @param int $exclude_id Member ID to exclude.
     * @return array<object>
     */
    protected static function get_primary_members_for_society( $society_id, $exclude_id = 0 ) {
        global $wpdb;
        $society_id = tpw_core_resolve_entity_society_id( $society_id );
        $exclude_id = (int) $exclude_id;
        if ( 0 >= $society_id ) {
            return [];
        }

        $table_household = $wpdb->prefix . 'tpw_members_household';
        $table_member    = $wpdb->prefix . 'tpw_members_household_member';
        $table_members   = $wpdb->prefix . 'tpw_members';

        $exclude_sql = '';
        $args        = [ $society_id ];
        if ( 0 < $exclude_id ) {
            $exclude_sql = ' AND m.id <> %d';
            $args[]      = $exclude_id;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT DISTINCT m.*
            FROM {$table_members} AS m
            INNER JOIN {$table_member} AS hm ON hm.member_id = m.id AND hm.is_primary = 1
            INNER JOIN {$table_household} AS h ON h.id = hm.household_id
            WHERE h.society_id = %d{$exclude_sql}
            ORDER BY m.surname ASC, m.first_name ASC";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $prepared = $wpdb->prepare( $sql, $args );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $prepared );
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Get the primary member for a household.
     *
     * @param int $household_id Household ID.
     * @return object|null
     */
    protected static function get_primary_member_for_household( $household_id ) {
        global $wpdb;
        $household_id = (int) $household_id;
        if ( 0 >= $household_id ) {
            return null;
        }

        $table_member  = $wpdb->prefix . 'tpw_members_household_member';
        $table_members = $wpdb->prefix . 'tpw_members';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prepared = $wpdb->prepare(
            "SELECT m.*
            FROM {$table_members} AS m
            INNER JOIN {$table_member} AS hm ON hm.member_id = m.id
            WHERE hm.household_id = %d AND hm.is_primary = 1
            LIMIT 1",
            $household_id
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row( $prepared );
    }

    /**
     * Resolve the effective society_id for household operations involving a member.
     *
     * @param object|null $member Member row.
     * @return int
     */
    protected static function resolve_member_society_id( $member ) {
        $society_id = 0;

        if ( is_object( $member ) && isset( $member->society_id ) ) {
            $society_id = (int) $member->society_id;
        }

        return tpw_core_resolve_entity_society_id( $society_id );
    }

    /**
     * Build a safe redirect URL back to the Edit Member screen.
     *
     * @param int   $member_id Member ID.
     * @param array $args      Query args to add.
     * @return string
     */
    protected static function get_edit_redirect_url( $member_id, $args = [] ) {
        $member_id = (int) $member_id;
        $ref       = wp_get_referer();
        if ( empty( $ref ) ) {
            $ref = site_url( '/manage-members/?action=edit_form&id=' . $member_id );
        }
        $ref = remove_query_arg( [ 'tpw_household_notice', 'tpw_household_error', 'tpw_household_id' ], $ref );
        return add_query_arg( $args, $ref );
    }

    /**
     * Render the Household section on the Edit Member screen.
     *
     * UI-only, read-only for now.
     *
     * @param string     $context   'add'|'edit'
     * @param int|null   $member_id Member ID in edit context.
     * @param object|null $member   Member row.
     * @param array      $meta     Member meta.
     * @return void
     */
    public static function render_household_section( $context, $member_id, $member, $meta ) {
        if ( 'edit' !== (string) $context ) {
            return;
        }

        if ( ! self::households_enabled() ) {
            return;
        }

        $member_id = (int) $member_id;
        if ( 0 >= $member_id ) {
            return;
        }

        if ( ! class_exists( 'TPW_Member_Household_Repository' ) ) {
            return;
        }

        $repo = new TPW_Member_Household_Repository();
        $membership = $repo->get_household_for_member( $member_id );

        $household_id = 0;
        $is_primary   = false;
        $role         = '';
        if ( $membership && isset( $membership->household_id ) ) {
            $household_id = (int) $membership->household_id;
            $is_primary = ( isset( $membership->is_primary ) && 1 === (int) $membership->is_primary );
            $role = isset( $membership->role ) ? (string) $membership->role : '';
        }
        if ( $is_primary ) {
            $role = 'primary';
        }

        echo '<fieldset class="tpw-section">';
        echo '<legend class="tpw-section__legend">' . esc_html__( 'Household', 'tpw-core' ) . '</legend>';
        echo '<div class="form-group">';

        // Flash notice (after admin-post actions).
        $notice = '';
        $error  = '';
        if ( isset( $_GET['tpw_household_notice'] ) ) {
            $notice = sanitize_key( wp_unslash( $_GET['tpw_household_notice'] ) );
        }
        if ( isset( $_GET['tpw_household_error'] ) ) {
            $error = sanitize_key( wp_unslash( $_GET['tpw_household_error'] ) );
        }
        if ( '' !== $notice ) {
            $messages = [
                'created' => __( 'Household created and member assigned.', 'tpw-core' ),
                'assigned' => __( 'Member assigned to household.', 'tpw-core' ),
            ];
            if ( isset( $messages[ $notice ] ) ) {
                echo '<div class="notice notice-success" style="margin:10px 0;"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
            }
        }
        if ( '' !== $error ) {
            $errors = [
                'permission' => __( 'You do not have permission to manage households.', 'tpw-core' ),
                'disabled' => __( 'Household support is disabled.', 'tpw-core' ),
                'invalid_member' => __( 'Invalid member.', 'tpw-core' ),
                'missing_society' => __( 'Cannot create household: member has no society assigned.', 'tpw-core' ),
                'create_failed' => __( 'Failed to create household.', 'tpw-core' ),
                'assign_failed' => __( 'Failed to assign member to household.', 'tpw-core' ),
                'invalid_primary' => __( 'Please select a valid primary member.', 'tpw-core' ),
                'society_mismatch' => __( 'Household belongs to a different society.', 'tpw-core' ),
            ];
            if ( isset( $errors[ $error ] ) ) {
                echo '<div class="notice notice-error" style="margin:10px 0;"><p>' . esc_html( $errors[ $error ] ) . '</p></div>';
            }
        }

        $member_society_id = 0;
        $member_society_id = self::resolve_member_society_id( $member );

        $primary_member     = ( 0 < $household_id ) ? self::get_primary_member_for_household( $household_id ) : null;
        $primary_member_id  = ( $primary_member && isset( $primary_member->id ) ) ? (int) $primary_member->id : 0;
        $primary_member_name = $primary_member ? self::get_member_display_name( $primary_member ) : '—';

        if ( 0 < $household_id ) {
            echo '<div><strong>' . esc_html__( 'Assigned:', 'tpw-core' ) . '</strong> ' . esc_html__( 'Yes', 'tpw-core' ) . '</div>';
            echo '<div><strong>' . esc_html__( 'Primary member:', 'tpw-core' ) . '</strong> ' . esc_html( $primary_member_name ) . '</div>';
            echo '<div><strong>' . esc_html__( 'Primary member (this record):', 'tpw-core' ) . '</strong> ' . esc_html( $is_primary ? __( 'Yes', 'tpw-core' ) : __( 'No', 'tpw-core' ) ) . '</div>';
            echo '<div><strong>' . esc_html__( 'Role:', 'tpw-core' ) . '</strong> ' . esc_html( '' !== $role ? ucfirst( $role ) : '—' ) . '</div>';

            // Household members list (admin-only UI on Edit Member screen).
            $household_members = (array) $repo->get_household_members( $household_id );
            if ( ! empty( $household_members ) ) {
                // Ensure deterministic ordering: Primary, then Partner, then Child.
                usort( $household_members, function( $a, $b ) {
                    $role_rank = function( $hm ) {
                        $is_primary = ( isset( $hm->is_primary ) && 1 === (int) $hm->is_primary );
                        if ( $is_primary ) {
                            return 0;
                        }
                        $role = isset( $hm->role ) ? strtolower( trim( (string) $hm->role ) ) : '';
                        if ( 'partner' === $role ) {
                            return 1;
                        }
                        if ( 'child' === $role ) {
                            return 2;
                        }
                        return 3;
                    };
                    $ra = $role_rank( $a );
                    $rb = $role_rank( $b );
                    if ( $ra !== $rb ) {
                        return $ra <=> $rb;
                    }
                    // Tie-breaker: surname, then first name, then member_id.
                    $sa = isset( $a->surname ) ? strtolower( trim( (string) $a->surname ) ) : '';
                    $sb = isset( $b->surname ) ? strtolower( trim( (string) $b->surname ) ) : '';
                    if ( $sa !== $sb ) {
                        return $sa <=> $sb;
                    }
                    $fa = isset( $a->first_name ) ? strtolower( trim( (string) $a->first_name ) ) : '';
                    $fb = isset( $b->first_name ) ? strtolower( trim( (string) $b->first_name ) ) : '';
                    if ( $fa !== $fb ) {
                        return $fa <=> $fb;
                    }
                    $ia = isset( $a->member_id ) ? (int) $a->member_id : 0;
                    $ib = isset( $b->member_id ) ? (int) $b->member_id : 0;
                    return $ia <=> $ib;
                } );

                echo '<div style="margin-top:10px;">';
                echo '<strong>' . esc_html__( 'Household members:', 'tpw-core' ) . '</strong>';
                echo '<div style="margin-top:6px;">';
                echo '<ul style="margin:0; padding-left:18px;">';
                foreach ( $household_members as $hm ) {
                    $hm_id = isset( $hm->member_id ) ? (int) $hm->member_id : 0;
                    if ( 0 >= $hm_id ) {
                        continue;
                    }
                    $hm_role = isset( $hm->role ) ? strtolower( trim( (string) $hm->role ) ) : '';
                    $hm_is_primary = ( isset( $hm->is_primary ) && 1 === (int) $hm->is_primary );
                    if ( $hm_is_primary ) {
                        $hm_role = 'primary';
                    }
                    $role_label = '';
                    if ( 'primary' === $hm_role ) {
                        $role_label = __( 'Primary', 'tpw-core' );
                    } elseif ( 'partner' === $hm_role ) {
                        $role_label = __( 'Partner', 'tpw-core' );
                    } elseif ( 'child' === $hm_role ) {
                        $role_label = __( 'Child', 'tpw-core' );
                    } else {
                        $role_label = __( 'Member', 'tpw-core' );
                    }
                    $badge = '<span style="display:inline-block; font-size:11px; line-height:1; padding:3px 6px; border-radius:999px; border:1px solid #cbd5e1; color:#334155; background:#f8fafc; margin-left:6px;">' . esc_html( $role_label ) . '</span>';

                    $first = isset( $hm->first_name ) ? trim( (string) $hm->first_name ) : '';
                    $surname = isset( $hm->surname ) ? trim( (string) $hm->surname ) : '';
                    $hm_name = trim( $surname . ( '' !== $surname && '' !== $first ? ', ' : '' ) . $first );
                    if ( '' === $hm_name ) {
                        $hm_name = '—';
                    }

                    $is_this = ( $hm_id === $member_id );
                    $this_marker = $is_this ? '<em style="margin-left:6px; color:#475569;">' . esc_html__( 'This record', 'tpw-core' ) . '</em>' : '';

                    $edit_url = esc_url( site_url( '/manage-members/?action=edit_form&id=' . $hm_id ) );
                    echo '<li style="margin:4px 0;">';
                    if ( $is_this ) {
                        echo '<span>' . esc_html( $hm_name ) . '</span>';
                    } else {
                        echo '<a href="' . $edit_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $hm_name ) . '</a>';
                    }
                    echo $badge; // escaped above
                    echo $this_marker; // escaped above
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div><strong>' . esc_html__( 'Status:', 'tpw-core' ) . '</strong> ' . esc_html__( 'Not assigned to a household', 'tpw-core' ) . '</div>';
        }

        $primary_options = self::get_primary_members_for_society( $member_society_id, $member_id );

        // If this member is the household primary, the UI is read-only.
        if ( $is_primary ) {
            echo '<div class="description" style="margin-top:10px;">' . esc_html__( 'This member is the primary contact for their household. Household changes are disabled here.', 'tpw-core' ) . '</div>';
        } else {
            // Editable UI for non-primary members.
            if ( 'primary' === $role ) {
                $role = 'partner';
            }

            $reveal_btn_id = 'tpw-household-structure-toggle-' . (int) $member_id;
            $controls_id   = 'tpw-household-structure-controls-' . (int) $member_id;
            $select_household_id = 'tpw-household-primary-select-' . (int) $member_id;
            $select_role_id      = 'tpw-household-role-select-' . (int) $member_id;
            $checkbox_primary_id = 'tpw-household-make-primary-' . (int) $member_id;
            $update_btn_id       = 'tpw-household-update-btn-' . (int) $member_id;
            $warn_move_id        = 'tpw-household-warn-move-' . (int) $member_id;
            $warn_replace_id     = 'tpw-household-warn-replace-' . (int) $member_id;

            echo '<div style="margin-top:12px;">';
            echo '<button type="button" id="' . esc_attr( $reveal_btn_id ) . '" class="tpw-btn tpw-btn-light" aria-expanded="false" aria-controls="' . esc_attr( $controls_id ) . '">' . esc_html__( 'Change household / primary member', 'tpw-core' ) . '</button>';
            echo '<div class="description" style="margin-top:6px;">' . esc_html__( 'Choose a primary member to attach or move this member into that household.', 'tpw-core' ) . '</div>';
            echo '</div>';

            echo '<div id="' . esc_attr( $controls_id ) . '" hidden style="margin-top:12px;" data-current-household-id="' . esc_attr( (string) (int) $household_id ) . '" data-current-primary-member-id="' . esc_attr( (string) (int) $primary_member_id ) . '">';
            echo '<input type="hidden" name="member_id" value="' . esc_attr( (string) $member_id ) . '">';
            wp_nonce_field( 'tpw_members_household_create', 'tpw_members_household_create_nonce' );
            wp_nonce_field( 'tpw_members_household_assign', 'tpw_members_household_assign_nonce' );

            if ( 0 >= $household_id ) {
                echo '<div style="margin-bottom:12px;">';
                echo '<button type="submit" name="action" value="tpw_members_household_create" class="tpw-btn tpw-btn-secondary" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" formnovalidate>' . esc_html__( 'Create new household with this member as primary', 'tpw-core' ) . '</button>';
                echo '</div>';
            }

            echo '<div>';
            // Primary select
            echo '<div style="margin-top:10px; margin-bottom:8px;">';
            echo '<label for="' . esc_attr( $select_household_id ) . '" style="display:block; margin-bottom:4px;">' . esc_html__( 'Choose household (primary member)', 'tpw-core' ) . '</label>';
            echo '<select id="' . esc_attr( $select_household_id ) . '" name="tpw_household_primary_member_id" style="min-width:260px;">';
            echo '<option value="0">' . esc_html__( '— Select —', 'tpw-core' ) . '</option>';
            foreach ( $primary_options as $pm ) {
                $pm_id = isset( $pm->id ) ? (int) $pm->id : 0;
                if ( 0 >= $pm_id ) {
                    continue;
                }
                $selected = ( 0 < $primary_member_id && $pm_id === $primary_member_id ) ? ' selected' : '';
                echo '<option value="' . esc_attr( (string) $pm_id ) . '"' . $selected . '>' . esc_html( self::get_member_display_name( $pm ) ) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div id="' . esc_attr( $warn_move_id ) . '" style="display:none; margin:8px 0; padding:8px 10px; border:1px solid #fcd34d; background:#fffbeb; color:#92400e; border-radius:6px;">⚠️ ' . esc_html__( 'This will move the member to a different household.', 'tpw-core' ) . '</div>';

            // Role select
            echo '<div style="margin-bottom:10px;">';
            echo '<label for="' . esc_attr( $select_role_id ) . '" style="display:block; margin-bottom:4px;">' . esc_html__( 'Household role for this member', 'tpw-core' ) . '</label>';
            echo '<select id="' . esc_attr( $select_role_id ) . '" name="tpw_household_role" style="min-width:260px;">';
            echo '<option value="partner"' . ( 'partner' === $role ? ' selected' : '' ) . '>' . esc_html__( 'Partner', 'tpw-core' ) . '</option>';
            echo '<option value="child"' . ( 'child' === $role ? ' selected' : '' ) . '>' . esc_html__( 'Child', 'tpw-core' ) . '</option>';
            echo '</select>';
            echo '</div>';

            // Primary toggle
            echo '<div style="margin-top:8px;">';
            echo '<label style="display:inline-block; margin-right:10px;">';
            echo '<input id="' . esc_attr( $checkbox_primary_id ) . '" type="checkbox" name="tpw_household_make_primary" value="1" style="margin-right:4px;">';
            echo esc_html__( 'Make this member the primary member for this household', 'tpw-core' );
            echo '</label>';
            echo '<div class="description" style="margin-top:4px;">' . esc_html__( 'This will replace the existing primary member.', 'tpw-core' ) . '</div>';
            echo '</div>';
            echo '<div id="' . esc_attr( $warn_replace_id ) . '" style="display:none; margin:8px 0; padding:8px 10px; border:1px solid #fcd34d; background:#fffbeb; color:#92400e; border-radius:6px;">⚠️ ' . esc_html__( 'This will replace the existing primary member for this household.', 'tpw-core' ) . '</div>';

            // Submit button (own row)
            echo '<div style="margin-top:10px;">';
            echo '<button id="' . esc_attr( $update_btn_id ) . '" type="submit" name="action" value="tpw_members_household_assign" class="tpw-btn tpw-btn-secondary" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" formnovalidate disabled>' . esc_html__( 'Update household', 'tpw-core' ) . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Inline progressive-disclosure toggle (UX only).
            echo '<script>(function(){
                var b=document.getElementById(' . wp_json_encode( $reveal_btn_id ) . ');
                var c=document.getElementById(' . wp_json_encode( $controls_id ) . ');
                var selHouse=document.getElementById(' . wp_json_encode( $select_household_id ) . ');
                var selRole=document.getElementById(' . wp_json_encode( $select_role_id ) . ');
                var chkPrimary=document.getElementById(' . wp_json_encode( $checkbox_primary_id ) . ');
                var btnSave=document.getElementById(' . wp_json_encode( $update_btn_id ) . ');
                var warnMove=document.getElementById(' . wp_json_encode( $warn_move_id ) . ');
                var warnReplace=document.getElementById(' . wp_json_encode( $warn_replace_id ) . ');
                if(!b||!c){return;}
                var currentHouseholdId=parseInt(c.getAttribute("data-current-household-id")||"0",10)||0;
                var currentPrimaryMemberId=parseInt(c.getAttribute("data-current-primary-member-id")||"0",10)||0;
                function setDisabled(el, disabled){ if(!el){return;} if(disabled){ el.setAttribute("disabled","disabled"); } else { el.removeAttribute("disabled"); } }
                function setLockedStyle(el, locked){ if(!el){return;} el.style.opacity = locked ? "0.65" : ""; }
                function updateState(){
                    if(!selHouse||!selRole||!chkPrimary||!btnSave){return;}
                    var householdVal=parseInt(selHouse.value||"0",10)||0;
                    var makePrimary=!!chkPrimary.checked;
                    // Rule 1: Primary cannot be child; lock role to adult equivalent (partner) when primary is checked.
                    if(makePrimary){
                        selRole.value="partner";
                        setDisabled(selRole,true);
                        setLockedStyle(selRole,true);
                    }else{
                        setDisabled(selRole,false);
                        setLockedStyle(selRole,false);
                    }
                    // Rule 2: Child cannot be primary.
                    if(selRole.value==="child"){
                        chkPrimary.checked=false;
                        setDisabled(chkPrimary,true);
                        setLockedStyle(chkPrimary,true);
                    }else{
                        setDisabled(chkPrimary,false);
                        setLockedStyle(chkPrimary,false);
                    }
                    // Warnings
                    if(warnMove){
                        var showMove = (currentHouseholdId>0 && householdVal>0 && currentPrimaryMemberId>0 && householdVal!==currentPrimaryMemberId);
                        warnMove.style.display = showMove ? "block" : "none";
                    }
                    if(warnReplace){
                        var showReplace = (chkPrimary.checked && householdVal>0);
                        warnReplace.style.display = showReplace ? "block" : "none";
                    }
                    // Save button enablement
                    var roleVal=selRole.value;
                    var validRole = (roleVal==="partner" || roleVal==="child");
                    var ok = (householdVal>0 && validRole);
                    setDisabled(btnSave, !ok);
                }
                b.addEventListener("click",function(){
                    var isHidden=c.hasAttribute("hidden");
                    if(isHidden){
                        c.removeAttribute("hidden");
                        b.setAttribute("aria-expanded","true");
                        updateState();
                    }else{
                        c.setAttribute("hidden","");
                        b.setAttribute("aria-expanded","false");
                    }
                });
                if(selHouse){ selHouse.addEventListener("change", updateState); }
                if(selRole){ selRole.addEventListener("change", updateState); }
                if(chkPrimary){ chkPrimary.addEventListener("change", updateState); }
            })();</script>';
        }

        echo '</div>';
        echo '</fieldset>';
    }

    /**
     * Create a new household for the member's society and assign the member as primary.
     *
     * @return void
     */
    public static function handle_household_create() {
        if ( ! self::households_enabled() ) {
            wp_die( 'Household support is disabled.', 403 );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( 'Permission denied.', 403 );
        }
        if ( ! self::user_can_manage() ) {
            wp_die( 'Permission denied.', 403 );
        }
        check_admin_referer( 'tpw_members_household_create', 'tpw_members_household_create_nonce' );

        $member_id = 0;
        if ( isset( $_POST['member_id'] ) ) {
            $member_id = absint( wp_unslash( $_POST['member_id'] ) );
        }
        if ( 0 >= $member_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_member' ] );
            wp_safe_redirect( $url );
            exit;
        }

        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        $member     = $controller->get_member( $member_id );
        if ( ! $member ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_member' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $society_id = isset( $member->society_id ) ? (int) $member->society_id : 0;
        $society_id = self::resolve_member_society_id( $member );
        if ( 0 >= $society_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'missing_society' ] );
            wp_safe_redirect( $url );
                $member_society_id = self::resolve_member_society_id( $member );
            exit;
        }

        $repo = new TPW_Member_Household_Repository();
        $household_id = $repo->create_household( $society_id );
        if ( 0 >= $household_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'create_failed' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $ok = $repo->assign_member( $household_id, $member_id, 'primary', true );
        if ( $ok ) {
            $repo->set_primary_contact( $household_id, $member_id );
        }
        if ( ! $ok ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'assign_failed' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $url = self::get_edit_redirect_url( $member_id, [
            'tpw_household_notice' => 'created',
            'tpw_household_id'     => (string) $household_id,
        ] );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Assign/move a member into an existing household.
     *
     * Validates household exists and matches member society.
     *
     * @return void
     */
    public static function handle_household_assign() {
        if ( ! self::households_enabled() ) {
            wp_die( 'Household support is disabled.', 403 );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( 'Permission denied.', 403 );
        }
        if ( ! self::user_can_manage() ) {
            wp_die( 'Permission denied.', 403 );
        }
        check_admin_referer( 'tpw_members_household_assign', 'tpw_members_household_assign_nonce' );

        $member_id = 0;
        if ( isset( $_POST['member_id'] ) ) {
            $member_id = absint( wp_unslash( $_POST['member_id'] ) );
        }
        if ( 0 >= $member_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_member' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $primary_member_id = 0;
        if ( isset( $_POST['tpw_household_primary_member_id'] ) ) {
            $primary_member_id = absint( wp_unslash( $_POST['tpw_household_primary_member_id'] ) );
        }
        if ( 0 >= $primary_member_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_primary' ] );
            wp_safe_redirect( $url );
            exit;
        }

        require_once plugin_dir_path( __FILE__ ) . 'class-tpw-member-controller.php';
        $controller = new TPW_Member_Controller();
        $member     = $controller->get_member( $member_id );
        if ( ! $member ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_member' ] );
            wp_safe_redirect( $url );
            exit;
        }
        $member_society_id = isset( $member->society_id ) ? (int) $member->society_id : 0;

        $repo = new TPW_Member_Household_Repository();
        $current_membership = $repo->get_household_for_member( $member_id );
        if ( $current_membership && ! empty( $current_membership->is_primary ) && 1 === (int) $current_membership->is_primary ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'assign_failed' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $primary_membership = $repo->get_household_for_member( $primary_member_id );
        if ( ! $primary_membership || empty( $primary_membership->household_id ) || 1 !== (int) $primary_membership->is_primary ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'invalid_primary' ] );
            wp_safe_redirect( $url );
            exit;
        }
        $household_id = (int) $primary_membership->household_id;

        $household = $repo->get_household( $household_id );
        if ( ! $household || ! isset( $household->society_id ) ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'assign_failed' ] );
            wp_safe_redirect( $url );
            exit;
        }
        if ( (int) $household->society_id !== (int) $member_society_id ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'society_mismatch' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $role = 'partner';
        if ( isset( $_POST['tpw_household_role'] ) ) {
            $role = sanitize_key( wp_unslash( $_POST['tpw_household_role'] ) );
        }
        $allowed_roles = [ 'partner', 'child' ];
        if ( ! in_array( $role, $allowed_roles, true ) ) {
            $role = 'partner';
        }

        $make_primary = false;
        if ( isset( $_POST['tpw_household_make_primary'] ) && '1' === wp_unslash( $_POST['tpw_household_make_primary'] ) ) {
            $make_primary = true;
        }

        $ok = $repo->assign_member( $household_id, $member_id, $role, false );
        if ( $ok && $make_primary ) {
            $ok = $repo->set_primary_contact( $household_id, $member_id );
        }
        if ( ! $ok ) {
            $url = self::get_edit_redirect_url( $member_id, [ 'tpw_household_error' => 'assign_failed' ] );
            wp_safe_redirect( $url );
            exit;
        }

        $url = self::get_edit_redirect_url( $member_id, [
            'tpw_household_notice' => 'assigned',
            'tpw_household_id'     => (string) $household_id,
        ] );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Handle manual creation/linking of a WP user for a member.
     * Preconditions:
     * - Nonce: tpw_create_wp_user
    * - Capability: members admin access (WP admin, TPW admin, or members manager)
     * - Member exists, has email, and has no linked user_id
     */
    public static function handle_create_wp_user() {
        // Basic auth/cap checks
        if ( ! is_user_logged_in() ) {
            wp_die( 'Permission denied.', 403 );
        }
        // Align capability with the members admin UI.
        if ( ! class_exists( 'TPW_Member_Access' ) ) {
            require_once plugin_dir_path(__FILE__) . 'class-tpw-member-access.php';
        }
        $can_manage = TPW_Member_Access::can_manage_members_current();
        if ( ! $can_manage ) { wp_die( 'Permission denied.', 403 ); }
        check_admin_referer( 'tpw_create_wp_user' );

        $member_id = isset($_REQUEST['member_id']) ? (int) $_REQUEST['member_id'] : 0;
        if ( $member_id <= 0 ) {
            wp_die( 'Invalid member.', 400 );
        }

        require_once plugin_dir_path(__FILE__) . 'class-tpw-member-controller.php';
        require_once plugin_dir_path(__FILE__) . 'class-tpw-member-roles.php';

        $controller = new TPW_Member_Controller();
        $member = $controller->get_member( $member_id );
        if ( ! $member ) {
            wp_die( 'Invalid member.', 404 );
        }
        $email = isset($member->email) ? trim((string) $member->email) : '';
        if ( $email === '' || ! is_email( $email ) ) {
            wp_die( 'Invalid member or email.', 400 );
        }
        if ( ! empty( $member->user_id ) ) {
            wp_die( 'Member already linked to a WordPress user.', 400 );
        }

        // If a WP user already exists for this email, link it; else create a new one
        $existing = get_user_by( 'email', $email );
        if ( $existing && isset($existing->ID) ) {
            $user_id = (int) $existing->ID;
        } else {
            // Derive username from member.username or email local part; ensure uniqueness
            $desired = isset($member->username) ? (string) $member->username : '';
            $user_login = $desired;
            if ( $user_login === '' || username_exists( $user_login ) || strlen( $user_login ) > 60 ) {
                $user_login = sanitize_user( current( explode( '@', $email ) ), true );
            }
            if ( $user_login === '' ) {
                $user_login = 'member_' . wp_generate_password( 8, false, false );
            }
            // Prepare display names
            $first = isset($member->first_name) ? (string) $member->first_name : '';
            $last  = isset($member->surname) ? (string) $member->surname : '';
            $display_name = trim( $first . ' ' . $last );

            $user_id = wp_insert_user( [
                'user_login'   => $user_login,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password(),
                'display_name' => $display_name,
                'first_name'   => $first,
                'last_name'    => $last,
                // Keep role assignment minimal; ensure caps via TPW_Member_Roles below
                'role'         => 'member',
            ] );

            if ( is_wp_error( $user_id ) ) {
                wp_die( 'Failed to create user: ' . esc_html( $user_id->get_error_message() ) );
            }

            // Ensure member capabilities are applied
            TPW_Member_Roles::ensure_member_cap( (int) $user_id );
        }

        // Link the WP user to this member
        $updated = $controller->update_member( $member_id, [ 'user_id' => (int) $user_id ] );
        if ( $updated === false ) {
            wp_die( 'Failed to link user to member.', 500 );
        }
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            @error_log('[TPW Members] WP user manually created/linked for Member #' . (int) $member_id . ' (' . $email . ') user_id=' . (int) $user_id );
        }

        // Optionally send credentials email using template system
        $send_creds = isset($_REQUEST['send_credentials']) && $_REQUEST['send_credentials'] === '1';
        if ( $send_creds && class_exists('TPW_Email') && class_exists('TPW_Email_Template_Registry') ) {
            // Resolve friendly member login URLs (no tokens to append; both identical)
            $member_login_url = site_url( '/member-login/' );
            $org = (string) get_option( 'tpw_brand_title', '' );
            if ( $org === '' ) { $org = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES ); }
            $tokens = [
                '{member_first_name}'  => isset($member->first_name) ? (string) $member->first_name : '',
                '{member_last_name}'   => isset($member->surname) ? (string) $member->surname : '',
                '{site_name}'          => wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES ),
                '{member_login_url}'   => $member_login_url,
                '{password_reset_url}' => $member_login_url,
                '{organisation_name}'  => $org,
            ];
            // Sender details from site settings
            $from = [
                'name'  => $org,
                'email' => get_option( 'admin_email' ),
            ];
            // Do not send copy to sender by default for credential emails
            TPW_Email::send_with_template( $email, $from, 'member_new_wp_user_created', $tokens, [], false );
        }

        // Redirect back with success flag
        $ref = wp_get_referer();
        if ( ! $ref ) {
            $ref = site_url( '/manage-members/?action=edit_form&id=' . $member_id );
        }
        $url = add_query_arg( 'wp_user_created', '1', $ref );
        wp_safe_redirect( $url );
        exit;
    }
}
