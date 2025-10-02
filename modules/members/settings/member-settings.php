<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper function to check if deletion is allowed.
 *
 * @return bool
 */
function tpw_members_allow_deletion() {
    return get_option( 'tpw_members_allow_deletion', '1' ) === '1';
}

/**
 * Get the default member status from settings.
 *
 * @return string
 */
function tpw_get_default_member_status() {
    return get_option( 'tpw_default_member_status', 'Active' );
}

// Handle form submission for front-end settings
$tpw_settings_saved = false;

if ( isset($_POST['tpw_member_settings_nonce']) && wp_verify_nonce($_POST['tpw_member_settings_nonce'], 'tpw_member_settings') ) {
        // Determine which tab we're saving (prefer POST, fallback to GET)
        $current_tab_post = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : ( isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general' );

        if ( $current_tab_post === 'general' ) {
            // General tab
            update_option( 'tpw_members_allow_deletion', isset($_POST['tpw_members_allow_deletion']) ? '1' : '0' );
            update_option( 'tpw_default_member_status', sanitize_text_field($_POST['tpw_default_member_status'] ?? 'Active') );
            update_option( 'tpw_members_use_photos', isset($_POST['tpw_members_use_photos']) ? '1' : '0' );
            $tpw_settings_saved = true;
        }

        if ( $current_tab_post === 'profile' ) {
            // Member Profile tab
            $editable = isset($_POST['tpw_member_editable_fields']) && is_array($_POST['tpw_member_editable_fields']) ? array_map('sanitize_text_field', $_POST['tpw_member_editable_fields']) : [];
            update_option( 'tpw_member_editable_fields', $editable );

            // Save viewable-by-member fields
            $viewable = isset($_POST['tpw_member_viewable_fields']) && is_array($_POST['tpw_member_viewable_fields']) ? array_map('sanitize_text_field', $_POST['tpw_member_viewable_fields']) : [];
            update_option( 'tpw_member_viewable_fields', $viewable );
            if ( isset($_POST['tpw_member_profile_page_id']) ) {
                $profile_page_id = (int) $_POST['tpw_member_profile_page_id'];
                update_option( 'tpw_member_profile_page_id', $profile_page_id );
            }
            $tpw_settings_saved = true;
        }

        if ( $current_tab_post === 'postcodes' ) {
            // Postcodes tab (save provider + API keys if posted)
            $provider = isset($_POST['tpw_postcode_provider']) ? sanitize_key($_POST['tpw_postcode_provider']) : null;
            if ( $provider !== null ) {
                $allowed = [ 'none', 'postcodesio', 'getaddress', 'google' ];
                if ( ! in_array( $provider, $allowed, true ) ) { $provider = 'postcodesio'; }
                $getaddr_key = isset($_POST['tpw_getaddress_api_key']) ? sanitize_text_field( wp_unslash($_POST['tpw_getaddress_api_key']) ) : '';
                $google_key  = isset($_POST['tpw_google_api_key']) ? sanitize_text_field( wp_unslash($_POST['tpw_google_api_key']) ) : '';
                $settings = [
                    'provider' => $provider,
                    'getaddress_api_key' => $getaddr_key,
                    'google_api_key' => $google_key,
                ];
                update_option( 'tpw_postcode_settings', $settings );
                $tpw_settings_saved = true;
            }
        }
}

// Tabs
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
$base_url = add_query_arg([ 'action' => 'settings' ], get_permalink());
$tab_url = function($tab) use ($base_url) { return esc_url( add_query_arg( 'tab', $tab, $base_url ) ); };

// Load fields for profile tab
require_once TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-field-loader.php';
require_once TPW_CORE_PATH . 'modules/members/includes/admin-settings-tabs.php';
$all_enabled_fields = TPW_Member_Field_Loader::get_all_enabled_fields();
$editable_selected = get_option( 'tpw_member_editable_fields', [] );
$editable_selected = is_array($editable_selected) ? $editable_selected : [];
$viewable_selected = get_option( 'tpw_member_viewable_fields', [] );
$viewable_selected = is_array($viewable_selected) ? $viewable_selected : [];
$protected_keys = [ 'status', 'is_committee', 'is_match_manager', 'is_admin', 'is_noticeboard_admin', 'password_hash', 'user_id', 'society_id' ];
$never_view_keys = [ 'password_hash', 'user_id', 'society_id' ];
$profile_page_id = (int) get_option( 'tpw_member_profile_page_id', 0 );

?>
<div class="tpw-member-settings">
    <div class="tpw-settings-card">
        <h2>Member Settings</h2>
        <?php tpw_members_render_settings_tabs( $current_tab ); ?>
        <h3>
            <?php
            if ( $current_tab === 'general' ) {
                echo 'General Settings';
            } elseif ( $current_tab === 'profile' ) {
                echo 'Member Profile';
            } elseif ( $current_tab === 'postcodes' ) {
                echo 'Postcode Lookup';
            } elseif ( $current_tab === 'help' ) {
                echo 'Help';
            } else {
                echo 'Member Settings';
            }
            ?>
        </h3>

    <form method="post">
        <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>" />
        <?php wp_nonce_field('tpw_member_settings', 'tpw_member_settings_nonce'); ?>

        <?php if ( $current_tab === 'general' ) : ?>
            <p>
                    <label>
                            <input type="checkbox" name="tpw_members_allow_deletion" value="1" <?php checked( tpw_members_allow_deletion(), true ); ?> />
                            Allow admins to delete members?
                    </label>
            </p>

            <p>
                    <label>
                            <input type="checkbox" name="tpw_members_use_photos" value="1" <?php checked( get_option('tpw_members_use_photos', '0'), '1' ); ?> />
                            Use Photos of Members
                    </label>
            </p>

            <p>
                    <label for="tpw_default_member_status"><strong>Default Member Status</strong></label><br>
                    <select name="tpw_default_member_status" id="tpw_default_member_status" style="width:auto; min-width: 220px; max-width: 320px;">
                            <?php
                            $status_options = [ 'Active','Inactive','Deceased','Honorary','Resigned','Suspended','Pending','Life Member' ];
                            $current_default = get_option('tpw_default_member_status', 'Active');
                            foreach ( $status_options as $option ) {
                                    echo '<option value="' . esc_attr($option) . '"' . selected($current_default, $option, false) . '>' . esc_html($option) . '</option>';
                            }
                            ?>
                    </select>
            </p>
        <?php elseif ( $current_tab === 'profile' ) : ?>
            <p>Select which fields members are allowed to edit on their profile.</p>
            <div class="tpw-table">
                <div class="tpw-table-header">
                    <div class="tpw-table-cell">Field</div>
                    <div class="tpw-table-cell">Label</div>
                    <div class="tpw-table-cell">Editable by Member?</div>
                    <div class="tpw-table-cell">Viewable by Member?</div>
                </div>
                <style>
                /* Responsive labels for Profile tab */
                .tpw-profile-resp { display:none; color:#555; margin-left:6px; }
                @media (max-width: 880px) {
                    .tpw-member-settings .tpw-table-header { display:none; }
                    .tpw-member-settings .tpw-table-row { display:block; padding:10px; margin:10px 0; border:1px solid #eee; border-radius:8px; background:#fafafa; }
                    .tpw-member-settings .tpw-table-cell { display:flex; align-items:center; justify-content:space-between; padding:6px 4px; }
                    .tpw-member-settings .tpw-table-cell:first-child { font-weight:600; }
                    .tpw-profile-resp { display:inline; }
                }
                </style>
                <?php foreach ( $all_enabled_fields as $field ): 
                    $key   = $field['key'];
                    $label = $field['label'];
                    $is_protected = in_array( $key, $protected_keys, true );
                    $checked = in_array( $key, $editable_selected, true );
                    $view_checked = in_array( $key, $viewable_selected, true );
                    $is_never_view = in_array( $key, $never_view_keys, true );
                ?>
                <div class="tpw-table-row">
                    <div class="tpw-table-cell"><code><?php echo esc_html($key); ?></code></div>
                    <div class="tpw-table-cell"><?php echo esc_html($label); ?></div>
                    <div class="tpw-table-cell">
                        <label style="display:inline-flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="tpw_member_editable_fields[]" value="<?php echo esc_attr($key); ?>" <?php checked( $checked ); ?> <?php echo $is_protected ? 'disabled title="Protected field"' : ''; ?> />
                            <span class="tpw-profile-resp">Editable by Member?</span>
                        </label>
                        <?php if ( $is_protected ): ?><em style="color:#777; margin-left:6px;">Protected</em><?php endif; ?>
                    </div>
                    <div class="tpw-table-cell">
                        <label style="display:inline-flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="tpw_member_viewable_fields[]" value="<?php echo esc_attr($key); ?>" <?php checked( $view_checked ); ?> <?php echo $is_never_view ? 'disabled title="Not viewable (system field)"' : ''; ?> />
                            <span class="tpw-profile-resp">Viewable by Member?</span>
                        </label>
                        <?php if ( $is_never_view ): ?><em style="color:#777; margin-left:6px;">System-only</em><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <p style="margin-top:12px;">
                <label for="tpw_member_profile_page_id"><strong>Profile Page</strong> (select a page that contains the [tpw_member_profile] shortcode)</label><br>
                <?php
                    wp_dropdown_pages([
                        'name'              => 'tpw_member_profile_page_id',
                        'id'                => 'tpw_member_profile_page_id',
                        'selected'          => $profile_page_id,
                        'show_option_none'  => '— Select —',
                        'option_none_value' => '0',
                    ]);
                ?>
            </p>

        <?php elseif ( $current_tab === 'postcodes' ) : ?>
            <?php $pc = get_option( 'tpw_postcode_settings', [] );
            $pc_provider = isset($pc['provider']) ? $pc['provider'] : 'postcodesio';
            $pc_getaddr  = isset($pc['getaddress_api_key']) ? $pc['getaddress_api_key'] : '';
            $pc_google   = isset($pc['google_api_key']) ? $pc['google_api_key'] : '';
            ?>
            <p class="description">Choose which postcode lookup service to use for Members and other modules.</p>
            <p>
                <label for="tpw_postcode_provider"><strong>Postcode Lookup Provider</strong></label><br>
                <select name="tpw_postcode_provider" id="tpw_postcode_provider">
                    <option value="none" <?php selected( $pc_provider, 'none' ); ?>>None</option>
                    <option value="postcodesio" <?php selected( $pc_provider, 'postcodesio' ); ?>>Postcodes.io (Default, GB only)</option>
                    <option value="getaddress" <?php selected( $pc_provider, 'getaddress' ); ?>>GetAddress.io</option>
                    <option value="google" <?php selected( $pc_provider, 'google' ); ?>>Google Maps API</option>
                </select>
                <br>
                <small class="description">Choose which postcode lookup service to use for Members and other modules.</small>
            </p>

            <div id="tpw_getaddress_section" style="display:none;">
                <p>
                    <label for="tpw_getaddress_api_key"><strong>GetAddress.io API Key</strong></label><br>
                    <input type="text" name="tpw_getaddress_api_key" id="tpw_getaddress_api_key" value="<?php echo esc_attr( $pc_getaddr ); ?>" style="max-width:420px; width:100%;">
                    <br>
                    <small class="description">You must create an account and copy your API key from getaddress.io</small>
                </p>
            </div>

            <div id="tpw_google_section" style="display:none;">
                <p>
                    <label for="tpw_google_api_key"><strong>Google Maps API Key</strong></label><br>
                    <input type="text" name="tpw_google_api_key" id="tpw_google_api_key" value="<?php echo esc_attr( $pc_google ); ?>" style="max-width:420px; width:100%;">
                    <br>
                    <small class="description">You must enable the Geocoding API and billing in your Google Cloud Console</small>
                </p>
            </div>

            <hr>
            <h4>Test Lookup</h4>
            <p class="description">Use a sample postcode to verify your current provider and API key (if required).</p>
            <p>
                <?php $ajax_url = admin_url('admin-ajax.php'); $nonce = wp_create_nonce('tpw_lookup_postcode'); ?>
                <label for="tpw_postcode_test"><strong>Sample Postcode</strong></label><br>
                <input type="text" id="tpw_postcode_test" value="SW1A 1AA" style="max-width:240px;">
                <button type="button" class="button" id="tpw_postcode_test_btn">Test Lookup</button>
            </p>
            <div id="tpw_postcode_test_result" class="description"></div>

            <script>
            (function(){
                var sel = document.getElementById('tpw_postcode_provider');
                var ga = document.getElementById('tpw_getaddress_section');
                var g  = document.getElementById('tpw_google_section');
                function toggle(){
                    var v = sel.value;
                    ga.style.display = (v === 'getaddress') ? '' : 'none';
                    g.style.display  = (v === 'google') ? '' : 'none';
                }
                if (sel){ sel.addEventListener('change', toggle); toggle(); }

                var btn = document.getElementById('tpw_postcode_test_btn');
                var input = document.getElementById('tpw_postcode_test');
                var out = document.getElementById('tpw_postcode_test_result');
                if (btn && input && out){
                    btn.addEventListener('click', function(){
                        var pc = (input.value || '').trim();
                        if (!pc){ out.textContent = 'Please enter a postcode to test.'; return; }
                        out.textContent = 'Testing…';
                        var fd = new FormData();
                        fd.append('action','tpw_lookup_postcode');
                        fd.append('postcode', pc);
                        fd.append('country','GB');
                        fd.append('nonce','<?php echo esc_js( $nonce ); ?>');
                        fetch('<?php echo esc_js( $ajax_url ); ?>', { method:'POST', credentials:'same-origin', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(json){
                            if (json && json.success && json.data){
                                var d = json.data;
                                out.textContent = 'Success → Town: ' + (d.town||d.district||'') + ', County: ' + (d.county||d.region||'') + (d.latitude? (' (Lat: '+d.latitude+', Lng: '+d.longitude+')') : '');
                            } else {
                                out.textContent = (json && json.message) ? ('Error → ' + json.message) : 'Error performing lookup';
                            }
                        })
                        .catch(function(){ out.textContent = 'Network error performing lookup.'; });
                    });
                }
            })();
            </script>
        <?php elseif ( $current_tab === 'help' ) : ?>
            <?php
                // Build Help hub using Markdown files in modules/members/docs/admin-help
                $help_dir  = trailingslashit( TPW_CORE_PATH . 'modules/members/docs/admin-help' );
                $help_base = add_query_arg( [ 'action' => 'settings', 'tab' => 'help' ], get_permalink() );
                $topics = [
                    'getting-started' => [ 'label' => 'Getting Started for Admins', 'file' => 'getting-started.md' ],
                    'managing-members' => [ 'label' => 'Managing Members', 'file' => 'managing-members.md' ],
                    'member-profiles' => [ 'label' => 'Member Profiles and Self‑Service', 'file' => 'member-profiles.md' ],
                    'roles-and-access' => [ 'label' => 'Roles and Access', 'file' => 'roles-and-access.md' ],
                    'postcode-lookup' => [ 'label' => 'Postcode Lookup', 'file' => 'postcode-lookup.md' ],
                ];

                $selected_slug = isset($_GET['help_topic']) ? sanitize_key($_GET['help_topic']) : '';
                $selected = isset($topics[$selected_slug]) ? $topics[$selected_slug] : null;
                $file_to_show = $selected ? $selected['file'] : 'README.md';
                $abs_path = $help_dir . $file_to_show;
                $markdown = file_exists($abs_path) ? file_get_contents($abs_path) : '# Topic not found' . "\n\nThe requested help topic could not be found.";

                // Minimal Markdown → HTML renderer (headings, lists, paragraphs, links, inline code, code fences)
                if ( ! function_exists('tpw_members_simple_markdown_to_html') ) {
                    function tpw_members_simple_markdown_to_html( $md ) {
                        $md = (string) $md;
                        $lines = preg_split('/\r?\n/', $md);
                        $html = '';
                        $in_ul = false; $in_code = false; $para = '';
                        $flush_para = function() use (&$html, &$para) {
                            $p = trim($para);
                            if ($p !== '') { $html .= '<p>' . $p . '</p>'; }
                            $para = '';
                        };
                        foreach ($lines as $ln) {
                            // Toggle code fences
                            if (preg_match('/^```/', $ln)) {
                                if ($in_code) { $html .= '</code></pre>'; $in_code = false; }
                                else { $flush_para(); if ($in_ul) { $html .= '</ul>'; $in_ul = false; } $html .= '<pre><code>'; $in_code = true; }
                                continue;
                            }
                            if ($in_code) { $html .= htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') . "\n"; continue; }

                            $trim = rtrim($ln);
                            if ($trim === '') { if ($in_ul) { $html .= '</ul>'; $in_ul = false; } $flush_para(); continue; }

                            // Headings
                            if (preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m)) {
                                $flush_para(); if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                                $lvl = strlen($m[1]); $txt = $m[2];
                                $txt = htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
                                $html .= '<h' . $lvl . '>' . $txt . '</h' . $lvl . '>';
                                continue;
                            }

                            // List item
                            if (preg_match('/^\s*[-*]\s+(.*)$/', $trim, $m)) {
                                if (!$in_ul) { $flush_para(); $html .= '<ul>'; $in_ul = true; }
                                $item = $m[1];
                                $item = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
                                // Inline code
                                $item = preg_replace_callback('/`([^`]+)`/', function($mm){ return '<code>' . htmlspecialchars($mm[1], ENT_QUOTES, 'UTF-8') . '</code>'; }, $item);
                                // Links [text](url)
                                $item = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $item);
                                $html .= '<li>' . $item . '</li>';
                                continue;
                            }

                            // Paragraph text: accumulate; convert simple inline markdown later
                            $t = htmlspecialchars($trim, ENT_QUOTES, 'UTF-8');
                            $t = preg_replace_callback('/`([^`]+)`/', function($mm){ return '<code>' . htmlspecialchars($mm[1], ENT_QUOTES, 'UTF-8') . '</code>'; }, $t);
                            $t = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $t);
                            $para .= ($para === '' ? '' : ' ') . $t;
                        }
                        if ($in_code) { $html .= '</code></pre>'; $in_code = false; }
                        if ($in_ul) { $html .= '</ul>'; $in_ul = false; }
                        $flush_para();
                        return $html;
                    }
                }
                $help_html = tpw_members_simple_markdown_to_html( $markdown );
            ?>
            <style>
                .tpw-help-grid{display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start}
                @media (max-width: 880px){ .tpw-help-grid{grid-template-columns:1fr} }
                .tpw-help-topics{list-style:none;margin:0;padding:0}
                .tpw-help-topics li{margin:0 0 6px}
                .tpw-help-topics a{display:block;padding:6px 8px;border-radius:4px;text-decoration:none;border:1px solid transparent}
                .tpw-help-topics a.is-active{background:#f0f6ff;border-color:#b3d4ff}
                .tpw-help-card{background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px}
                .tpw-help-muted{color:#555}
                .tpw-help-content h1,.tpw-help-content h2{margin-top:0}
                .tpw-help-content pre{background:#f6f8fa;border:1px solid #e1e4e8;border-radius:6px;padding:12px;overflow:auto}
            </style>
            <div class="tpw-help-grid">
                <div>
                    <h3 class="tpw-help-muted" style="margin-top:0">Topics</h3>
                    <ul class="tpw-help-topics">
                        <?php foreach ($topics as $slug => $conf):
                            $href = esc_url( add_query_arg( [ 'action' => 'settings', 'tab' => 'help', 'help_topic' => $slug ], get_permalink() ) );
                            $active = $slug === $selected_slug ? ' is-active' : '';
                        ?>
                            <li><a class="<?php echo $active; ?>" href="<?php echo $href; ?>"><?php echo esc_html( $conf['label'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="tpw-help-muted" style="margin-top:12px;">
                        Developers: open the <a href="<?php echo esc_url( TPW_CORE_URL . 'docs/developer-guide.md' ); ?>" target="_blank" rel="noopener">Developer Guide</a>.
                    </p>
                </div>
                <div class="tpw-help-card tpw-help-content">
                    <?php echo $help_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( $current_tab !== 'help' ) : ?>
            <p><button type="submit" class="button button-primary">Save Settings</button></p>
            <?php if ( $tpw_settings_saved ) : ?><p><strong>Settings saved.</strong></p><?php endif; ?>
        <?php endif; ?>
    </form>
    </div>
</div>