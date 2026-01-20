<?php
/**
 * Mindbody Settings Page
 * 
 * Provides an admin settings page for Mindbody API configuration
 * 
 * @package Home_Wellness
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Mindbody settings page to admin menu
 */
function hw_mindbody_add_settings_page() {
    add_menu_page(
        __( 'Mindbody Settings', 'homewellness' ),
        __( 'Mindbody', 'homewellness' ),
        'manage_options',
        'hw-mindbody-settings',
        'hw_mindbody_settings_page_render',
        'dashicons-calendar-alt',
        30
    );
}
add_action( 'admin_menu', 'hw_mindbody_add_settings_page' );

/**
 * Register settings
 */
function hw_mindbody_register_enhanced_settings() {
    // API Credentials (these may already exist, so we register them again safely)
    register_setting( 'hw_mindbody_settings', 'mindbody_api_key' );
    register_setting( 'hw_mindbody_settings', 'mindbody_site_id' );
    register_setting( 'hw_mindbody_settings', 'mindbody_api_secret' );
    register_setting( 'hw_mindbody_settings', 'mindbody_source_name' );
    
    // Page Settings
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_book_page_id' );
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_default_location' );
    
    // Display Options
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_show_mat_classes' );
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_show_reformer_pilates' );
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_show_equipment_pilates' );
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_show_treatments' );
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_show_workshops' );
    
    // Treatment Categories
    register_setting( 'hw_mindbody_settings', 'hw_mindbody_treatment_categories' );
}
add_action( 'admin_init', 'hw_mindbody_register_enhanced_settings' );


/**
 * Get treatment categories as array
 * 
 * @return array
 */
function hw_mindbody_get_treatment_categories() {
    $default_categories = "Acupuncture & Eastern Med\nEnergy & Healing Therapies\nFace & Skin Treatments\nFertility, Pre & Postnatal\nMassage & Bodywork\nMind & Emotional Health\nNatural Medicine/ Nutrition\nOsteopathy & Physiotherapy";
    
    $categories_text = get_option( 'hw_mindbody_treatment_categories', $default_categories );
    $categories      = array_filter( array_map( 'trim', explode( "\n", $categories_text ) ) );
    
    return $categories;
}

/**
 * Render settings page
 */
function hw_mindbody_settings_page_render() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Get current tab
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'api';
    
    // Handle form submission
    if ( isset( $_POST['hw_mindbody_settings_submit'] ) && check_admin_referer( 'hw_mindbody_settings' ) ) {
        // API Credentials
        if ( isset( $_POST['mindbody_api_key'] ) ) {
            update_option( 'mindbody_api_key', sanitize_text_field( wp_unslash( $_POST['mindbody_api_key'] ) ) );
        }
        if ( isset( $_POST['mindbody_site_id'] ) ) {
            update_option( 'mindbody_site_id', sanitize_text_field( wp_unslash( $_POST['mindbody_site_id'] ) ) );
        }
        if ( isset( $_POST['mindbody_api_secret'] ) ) {
            update_option( 'mindbody_api_secret', sanitize_text_field( wp_unslash( $_POST['mindbody_api_secret'] ) ) );
        }
        if ( isset( $_POST['mindbody_source_name'] ) ) {
            update_option( 'mindbody_source_name', sanitize_text_field( wp_unslash( $_POST['mindbody_source_name'] ) ) );
        }
        
        // Page Settings
        if ( isset( $_POST['hw_mindbody_book_page_id'] ) ) {
            update_option( 'hw_mindbody_book_page_id', intval( $_POST['hw_mindbody_book_page_id'] ) );
        }
        if ( isset( $_POST['hw_mindbody_default_location'] ) ) {
            update_option( 'hw_mindbody_default_location', sanitize_text_field( wp_unslash( $_POST['hw_mindbody_default_location'] ) ) );
        }
        
        // Display Options
        update_option( 'hw_mindbody_show_mat_classes', isset( $_POST['hw_mindbody_show_mat_classes'] ) ? 1 : 0 );
        update_option( 'hw_mindbody_show_reformer_pilates', isset( $_POST['hw_mindbody_show_reformer_pilates'] ) ? 1 : 0 );
        update_option( 'hw_mindbody_show_equipment_pilates', isset( $_POST['hw_mindbody_show_equipment_pilates'] ) ? 1 : 0 );
        update_option( 'hw_mindbody_show_treatments', isset( $_POST['hw_mindbody_show_treatments'] ) ? 1 : 0 );
        update_option( 'hw_mindbody_show_workshops', isset( $_POST['hw_mindbody_show_workshops'] ) ? 1 : 0 );
        
        // Treatment Categories
        if ( isset( $_POST['hw_mindbody_treatment_categories'] ) ) {
            update_option( 'hw_mindbody_treatment_categories', sanitize_textarea_field( wp_unslash( $_POST['hw_mindbody_treatment_categories'] ) ) );
        }
        
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved!', 'homewellness' ) . '</p></div>';
    }
    
    // Get saved values
    $api_key          = get_option( 'mindbody_api_key', '' );
    $site_id          = get_option( 'mindbody_site_id', '' );
    $api_secret       = get_option( 'mindbody_api_secret', '' );
    $source_name      = get_option( 'mindbody_source_name', '' );
    $book_page_id     = get_option( 'hw_mindbody_book_page_id', 0 );
    $default_location = get_option( 'hw_mindbody_default_location', 'Primrose Hill' );
    
    // Display options
    $show_mat_classes      = get_option( 'hw_mindbody_show_mat_classes', 1 );
    $show_reformer_pilates = get_option( 'hw_mindbody_show_reformer_pilates', 1 );
    $show_equipment_pilates = get_option( 'hw_mindbody_show_equipment_pilates', 1 );
    $show_treatments       = get_option( 'hw_mindbody_show_treatments', 1 );
    $show_workshops        = get_option( 'hw_mindbody_show_workshops', 1 );
    
    // Treatment categories
    $default_categories   = "Acupuncture & Eastern Med\nEnergy & Healing Therapies\nFace & Skin Treatments\nFertility, Pre & Postnatal\nMassage & Bodywork\nMind & Emotional Health\nNatural Medicine/ Nutrition\nOsteopathy & Physiotherapy";
    $treatment_categories = get_option( 'hw_mindbody_treatment_categories', $default_categories );
    
    // Define tabs
    $tabs = array(
        'api'          => __( 'API Credentials', 'homewellness' ),
        'pages'        => __( 'Page Settings', 'homewellness' ),
        'display'      => __( 'Display Options', 'homewellness' ),
        'categories'   => __( 'Treatment Categories', 'homewellness' ),
        'shortcodes'   => __( 'Shortcodes', 'homewellness' ),
    );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hw-mindbody-settings&tab=' . $tab_id ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $tab_name ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <div class="tab-content" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none;">
            
            <?php if ( 'api' === $current_tab ) : ?>
            <!-- API Credentials Tab -->
            <h2><?php esc_html_e( 'API Credentials', 'homewellness' ); ?></h2>
            <p><?php esc_html_e( 'Enter your Mindbody API credentials from the Mindbody Developer Portal.', 'homewellness' ); ?></p>
            
            <div style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 12px; margin: 20px 0;">
                <strong>📋 <?php esc_html_e( 'Where to find these values:', 'homewellness' ); ?></strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li><strong><?php esc_html_e( 'API Key:', 'homewellness' ); ?></strong> <?php esc_html_e( 'Copy from "Key" column in the "API Keys" table', 'homewellness' ); ?></li>
                    <li><strong><?php esc_html_e( 'Site ID:', 'homewellness' ); ?></strong> <?php esc_html_e( 'Use "-99" for sandbox testing, or your actual Studio ID for production', 'homewellness' ); ?></li>
                    <li><strong><?php esc_html_e( 'API Secret:', 'homewellness' ); ?></strong> <?php esc_html_e( 'Copy from "Source Password" in the "Public API Source Credentials" table', 'homewellness' ); ?></li>
                </ul>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'hw_mindbody_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mindbody_api_key"><?php esc_html_e( 'API Key', 'homewellness' ); ?></label></th>
                        <td>
                            <input type="text" id="mindbody_api_key" name="mindbody_api_key" 
                                   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mindbody_site_id"><?php esc_html_e( 'Site ID', 'homewellness' ); ?></label></th>
                        <td>
                            <input type="text" id="mindbody_site_id" name="mindbody_site_id" 
                                   value="<?php echo esc_attr( $site_id ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Use "-99" for sandbox testing.', 'homewellness' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mindbody_api_secret"><?php esc_html_e( 'API Secret', 'homewellness' ); ?></label></th>
                        <td>
                            <input type="password" id="mindbody_api_secret" name="mindbody_api_secret" 
                                   value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mindbody_source_name"><?php esc_html_e( 'Source Name', 'homewellness' ); ?></label></th>
                        <td>
                            <input type="text" id="mindbody_source_name" name="mindbody_source_name" 
                                   value="<?php echo esc_attr( $source_name ); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="hw_mindbody_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'homewellness' ); ?>" />
                    <button type="button" id="hw-test-connection" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e( 'Test Connection', 'homewellness' ); ?>
                    </button>
                </p>
            </form>
            
            <!-- Test Connection Results -->
            <div id="hw-connection-result" style="display: none; margin-top: 20px; padding: 15px; border-radius: 4px;"></div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var testBtn = document.getElementById('hw-test-connection');
                var resultDiv = document.getElementById('hw-connection-result');
                
                if (testBtn) {
                    testBtn.addEventListener('click', function() {
                        testBtn.disabled = true;
                        testBtn.textContent = '<?php echo esc_js( __( 'Testing...', 'homewellness' ) ); ?>';
                        resultDiv.style.display = 'none';
                        
                        fetch('<?php echo esc_url( rest_url( 'hw-mindbody/v1/test-connection' ) ); ?>', {
                            method: 'GET',
                            headers: {
                                'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
                            }
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            resultDiv.style.display = 'block';
                            
                            if (data.success) {
                                resultDiv.style.background = '#d4edda';
                                resultDiv.style.borderLeft = '4px solid #28a745';
                                resultDiv.innerHTML = '<strong>✅ ' + data.message + '</strong><br><br>' +
                                    '<strong>Site ID:</strong> ' + data.site_id + '<br>' +
                                    '<strong>Locations:</strong> ' + data.data.locations + ' (' + data.location_names.join(', ') + ')<br>' +
                                    '<strong>Session Types:</strong> ' + data.data.session_types + '<br>' +
                                    '<strong>Services:</strong> ' + data.data.services + '<br>' +
                                    '<strong>Staff:</strong> ' + data.data.staff;
                            } else {
                                resultDiv.style.background = '#f8d7da';
                                resultDiv.style.borderLeft = '4px solid #dc3545';
                                resultDiv.innerHTML = '<strong>❌ ' + data.message + '</strong>';
                            }
                        })
                        .catch(function(error) {
                            resultDiv.style.display = 'block';
                            resultDiv.style.background = '#f8d7da';
                            resultDiv.style.borderLeft = '4px solid #dc3545';
                            resultDiv.innerHTML = '<strong>❌ Error:</strong> ' + error.message;
                        })
                        .finally(function() {
                            testBtn.disabled = false;
                            testBtn.textContent = '<?php echo esc_js( __( 'Test Connection', 'homewellness' ) ); ?>';
                        });
                    });
                }
            });
            </script>
            
            <?php elseif ( 'pages' === $current_tab ) : ?>
            <!-- Page Settings Tab -->
            <h2><?php esc_html_e( 'Page Settings', 'homewellness' ); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'hw_mindbody_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="hw_mindbody_book_page_id"><?php esc_html_e( 'Booking Page', 'homewellness' ); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages( array(
                                'name'              => 'hw_mindbody_book_page_id',
                                'id'                => 'hw_mindbody_book_page_id',
                                'selected'          => $book_page_id,
                                'show_option_none'  => __( '— Select Page —', 'homewellness' ),
                                'option_none_value' => 0,
                            ) );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hw_mindbody_default_location"><?php esc_html_e( 'Default Location', 'homewellness' ); ?></label></th>
                        <td>
                            <input type="text" id="hw_mindbody_default_location" name="hw_mindbody_default_location" 
                                   value="<?php echo esc_attr( $default_location ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'The default location name shown for treatments (e.g., "Primrose Hill").', 'homewellness' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="hw_mindbody_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'homewellness' ); ?>" />
                </p>
            </form>
            
            <?php elseif ( 'display' === $current_tab ) : ?>
            <!-- Display Options Tab -->
            <h2><?php esc_html_e( 'Display Options', 'homewellness' ); ?></h2>
            <p><?php esc_html_e( 'Control which service types are visible on booking pages.', 'homewellness' ); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'hw_mindbody_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Visible Service Types', 'homewellness' ); ?></th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="hw_mindbody_show_mat_classes" value="1" <?php checked( $show_mat_classes, 1 ); ?> />
                                    <?php esc_html_e( 'Mat Classes', 'homewellness' ); ?>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="hw_mindbody_show_reformer_pilates" value="1" <?php checked( $show_reformer_pilates, 1 ); ?> />
                                    <?php esc_html_e( 'Reformer Pilates', 'homewellness' ); ?>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="hw_mindbody_show_equipment_pilates" value="1" <?php checked( $show_equipment_pilates, 1 ); ?> />
                                    <?php esc_html_e( 'Equipment Pilates', 'homewellness' ); ?>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="hw_mindbody_show_treatments" value="1" <?php checked( $show_treatments, 1 ); ?> />
                                    <strong><?php esc_html_e( 'Treatments / Appointments', 'homewellness' ); ?></strong>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="hw_mindbody_show_workshops" value="1" <?php checked( $show_workshops, 1 ); ?> />
                                    <?php esc_html_e( 'Workshops', 'homewellness' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="hw_mindbody_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'homewellness' ); ?>" />
                </p>
            </form>
            
            <?php elseif ( 'categories' === $current_tab ) : ?>
            <!-- Treatment Categories Tab -->
            <h2><?php esc_html_e( 'Treatment Categories', 'homewellness' ); ?></h2>
            <p><?php esc_html_e( 'Define the appointment service type categories shown in the Treatment Type filter dropdown.', 'homewellness' ); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'hw_mindbody_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="hw_mindbody_treatment_categories"><?php esc_html_e( 'Categories', 'homewellness' ); ?></label></th>
                        <td>
                            <textarea id="hw_mindbody_treatment_categories" name="hw_mindbody_treatment_categories" 
                                      rows="12" class="large-text code"><?php echo esc_textarea( $treatment_categories ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Enter one category per line.', 'homewellness' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="hw_mindbody_settings_submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'homewellness' ); ?>" />
                </p>
            </form>
            
            <?php elseif ( 'shortcodes' === $current_tab ) : ?>
            <!-- Shortcodes Tab -->
            <h2><?php esc_html_e( 'Available Shortcodes', 'homewellness' ); ?></h2>
            <p><?php esc_html_e( 'Use these shortcodes in your pages or Gutenberg blocks.', 'homewellness' ); ?></p>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3><?php esc_html_e( 'Appointments Booking Interface', 'homewellness' ); ?></h3>
                <p><?php esc_html_e( 'Displays the full appointments booking interface with filters.', 'homewellness' ); ?></p>
                <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[hw_mindbody_appointments]</code>
                
                <h4><?php esc_html_e( 'Attributes:', 'homewellness' ); ?></h4>
                <ul>
                    <li><code>title</code> - <?php esc_html_e( 'Custom title (default: "BOOK YOUR APPOINTMENT")', 'homewellness' ); ?></li>
                    <li><code>show_filters</code> - <?php esc_html_e( 'Show/hide filters (default: "yes")', 'homewellness' ); ?></li>
                    <li><code>days</code> - <?php esc_html_e( 'Number of days to show (default: 7)', 'homewellness' ); ?></li>
                </ul>
                
                <h4><?php esc_html_e( 'Example:', 'homewellness' ); ?></h4>
                <code style="display: block; padding: 10px; background: #f5f5f5;">[hw_mindbody_appointments title="Book Your Treatment" days="14"]</code>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3><?php esc_html_e( 'Therapist List', 'homewellness' ); ?></h3>
                <p><?php esc_html_e( 'Displays a list of available therapists.', 'homewellness' ); ?></p>
                <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[hw_mindbody_therapists]</code>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3><?php esc_html_e( 'Class Schedule', 'homewellness' ); ?></h3>
                <p><?php esc_html_e( 'Displays class schedules from Mindbody.', 'homewellness' ); ?></p>
                <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[hw_mindbody_schedule]</code>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
    <?php
}

