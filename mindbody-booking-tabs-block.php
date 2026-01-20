<?php
/**
 * Mindbody Booking Tabs Gutenberg Block
 * 
 * Provides a tabbed interface for booking different service types
 * without page reload.
 * 
 * @package Home_Wellness
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Booking Tabs block
 */
function hw_register_booking_tabs_block() {
    // Register block script
    wp_register_script(
        'hw-booking-tabs-block',
        get_template_directory_uri() . '/assets/js/booking-tabs-block.js',
        array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
        filemtime( get_template_directory() . '/assets/js/booking-tabs-block.js' ),
        true
    );
    
    // Register block
    register_block_type( 'homewellness/booking-tabs', array(
        'editor_script'   => 'hw-booking-tabs-block',
        'render_callback' => 'hw_render_booking_tabs_block',
        'attributes'      => array(
            'activeTab' => array(
                'type'    => 'string',
                'default' => 'treatments',
            ),
        ),
    ) );
}
add_action( 'init', 'hw_register_booking_tabs_block' );

/**
 * Render the Booking Tabs block on frontend
 */
function hw_render_booking_tabs_block( $attributes ) {
    $active_tab = $attributes['activeTab'] ?? 'treatments';
    
    // Define tabs - Workshops links to external page, others have widgets
    $tabs = array(
        'mat-classes' => array(
            'label'       => 'MAT CLASSES',
            'widget_type' => 'Schedules',
            'widget_id'   => 'ac267460bcb',
        ),
        'reformer-pilates' => array(
            'label'       => 'REFORMER PILATES',
            'widget_type' => 'Schedules',
            'widget_id'   => 'ac267460bcb',
        ),
        'equipment-pilates' => array(
            'label'       => 'EQUIPMENT PILATES',
            'widget_type' => 'Schedules',
            'widget_id'   => 'ac267460bcb',
        ),
        'treatments' => array(
            'label'       => 'TREATMENTS',
            'shortcode'   => '[hw_mindbody_appointments title="BOOK YOUR APPOINTMENT" days="3"]',
        ),
        'workshops' => array(
            'label'       => 'WORKSHOPS',
            'external_url' => home_url( '/book-workshop-and-courses/' ),
        ),
    );
    
    ob_start();
    ?>
    <div class="hw-booking-tabs-container" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
        <style>
            .hw-booking-tabs-container {
                width: 100%;
                max-width: 100%;
                padding-top: 30px;
                box-sizing: border-box;
                overflow-x: hidden;
            }
            .hw-booking-tabs-nav {
                display: flex;
                flex-wrap: nowrap;
                gap: 15px;
                margin-bottom: 30px;
                justify-content: stretch;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .hw-booking-tab-btn,
            .hw-booking-tab-link {
                flex: 1;
                padding: 15px 20px;
                border: 1px solid #e0e0e0;
                background: #fff;
                color: #333;
                font-size: 0.85rem;
                font-weight: 500;
                letter-spacing: 0.05em;
                cursor: pointer;
                transition: all 0.3s ease;
                text-transform: uppercase;
                font-family: inherit;
                text-align: center;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .hw-booking-tab-btn:hover,
            .hw-booking-tab-link:hover {
                background: #f5f5f5;
            }
            .hw-booking-tab-btn.active {
                background: hsla(218.54, 79.19%, 66.08%, 1);
                color: #fff;
                border-color: hsla(218.54, 79.19%, 66.08%, 1);
            }
            .hw-booking-tab-content {
                display: none;
                min-height: 400px;
            }
            .hw-booking-tab-content.active {
                display: block;
            }
            .hw-booking-phone-notice {
                text-align: left;
                font-size: 14px;
                color: #666;
                margin: 0 0 30px 0;
                font-family: inherit;
            }
            .hw-booking-phone-notice a {
                color: hsla(218.54, 79.19%, 66.08%, 1);
                text-decoration: none;
                font-weight: 500;
            }
            .hw-booking-phone-notice a:hover {
                text-decoration: underline;
            }
            .hw-widget-iframe {
                width: 100%;
                min-height: 700px;
                border: none;
                background: #fff;
                border-radius: 4px;
            }
            
            /* Custom Scrollbar for iframe container - WebKit */
            .hw-booking-tab-content::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }
            .hw-booking-tab-content::-webkit-scrollbar-track {
                background: #f5f5f5;
                border-radius: 10px;
            }
            .hw-booking-tab-content::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, #6B93D6 0%, #4A7BC7 100%);
                border-radius: 10px;
                border: 2px solid #f5f5f5;
            }
            .hw-booking-tab-content::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(180deg, #5A82C5 0%, #3A6BB7 100%);
            }
            
            /* Custom Scrollbar - Firefox */
            .hw-booking-tab-content {
                scrollbar-width: thin;
                scrollbar-color: #6B93D6 #f5f5f5;
            }
            .hw-tab-loading {
                text-align: center;
                padding: 60px 20px;
                color: #666;
            }
            .hw-tab-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid hsla(218.54, 79.19%, 66.08%, 1);
                border-radius: 50%;
                margin: 0 auto 20px;
                animation: hwSpin 1s linear infinite;
            }
            @keyframes hwSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @media (max-width: 768px) {
                .hw-booking-tabs-container {
                    padding-left: 0;
                    padding-right: 0;
                    width: 100%;
                    max-width: 100%;
                }
                .hw-booking-tabs-nav {
                    flex-direction: column;
                    gap: 10px;
                    width: 100%;
                    max-width: 100%;
                }
                .hw-booking-tab-btn,
                .hw-booking-tab-link {
                    width: 100%;
                    max-width: 100%;
                    text-align: center;
                    box-sizing: border-box;
                    flex: none;
                }
                .hw-booking-phone-notice {
                    font-size: 13px;
                    padding: 0;
                }
                .hw-booking-tab-content {
                    width: 100%;
                    max-width: 100%;
                    overflow-x: hidden;
                }
                .hw-widget-iframe {
                    width: 100%;
                    max-width: 100%;
                }
            }
        </style>
        
        <!-- Tab Navigation -->
        <div class="hw-booking-tabs-nav">
            <?php foreach ( $tabs as $tab_id => $tab ) : ?>
                <?php if ( isset( $tab['external_url'] ) ) : ?>
                    <!-- Workshops links to external page -->
                    <a href="<?php echo esc_url( $tab['external_url'] ); ?>" class="hw-booking-tab-link">
                        <?php echo esc_html( $tab['label'] ); ?>
                    </a>
                <?php else : ?>
                    <button type="button" 
                            class="hw-booking-tab-btn <?php echo $tab_id === $active_tab ? 'active' : ''; ?>" 
                            data-tab="<?php echo esc_attr( $tab_id ); ?>"
                            data-widget-type="<?php echo esc_attr( $tab['widget_type'] ?? '' ); ?>"
                            data-widget-id="<?php echo esc_attr( $tab['widget_id'] ?? '' ); ?>">
                        <?php echo esc_html( $tab['label'] ); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Phone Booking Notice -->
        <p class="hw-booking-phone-notice">If you would prefer to book your treatment by phone, please call us on <a href="tel:02045533344">020 4553 3344</a> and we will be happy to help.</p>
        
        <!-- Tab Content Panels (excluding workshops which links externally) -->
        <?php foreach ( $tabs as $tab_id => $tab ) : ?>
            <?php if ( ! isset( $tab['external_url'] ) ) : ?>
                <div class="hw-booking-tab-content <?php echo $tab_id === $active_tab ? 'active' : ''; ?>" 
                     data-tab-content="<?php echo esc_attr( $tab_id ); ?>">
                    <?php if ( $tab_id === $active_tab ) : ?>
                        <?php if ( isset( $tab['shortcode'] ) ) : ?>
                            <?php echo do_shortcode( $tab['shortcode'] ); ?>
                        <?php else : ?>
                            <!-- Use iframe for Mindbody widgets to ensure proper isolation -->
                            <iframe class="hw-widget-iframe" 
                                    src="<?php echo esc_url( home_url( '/book-yoga/?embed=1' ) ); ?>" 
                                    data-widget-type="<?php echo esc_attr( $tab['widget_type'] ); ?>"
                                    data-widget-id="<?php echo esc_attr( $tab['widget_id'] ); ?>"></iframe>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <script>
        (function() {
            const container = document.querySelector('.hw-booking-tabs-container');
            if (!container) return;
            
            const buttons = container.querySelectorAll('.hw-booking-tab-btn');
            const contents = container.querySelectorAll('.hw-booking-tab-content');
            
            // URL mapping for each tab's iframe source (with embed=1 to hide header/footer/banner)
            const tabUrls = {
                'mat-classes': '<?php echo esc_url( home_url( '/book-yoga/?embed=1' ) ); ?>',
                'reformer-pilates': '<?php echo esc_url( home_url( '/book-reformer/?embed=1' ) ); ?>',
                'equipment-pilates': '<?php echo esc_url( home_url( '/book-equipment-studio/?embed=1' ) ); ?>'
            };
            
            // Store treatments content
            const treatmentsPanel = container.querySelector('[data-tab-content="treatments"]');
            const treatmentsHTML = treatmentsPanel ? treatmentsPanel.innerHTML : '';
            let treatmentsLoaded = true;
            
            buttons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const tabId = this.dataset.tab;
                    
                    // Update active button
                    buttons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update active content
                    contents.forEach(c => c.classList.remove('active'));
                    const targetContent = container.querySelector(`[data-tab-content="${tabId}"]`);
                    
                    if (targetContent) {
                        targetContent.classList.add('active');
                        
                        // Check if content is empty (needs loading)
                        if (targetContent.innerHTML.trim() === '' || targetContent.innerHTML.includes('hw-tab-loading')) {
                            if (tabId === 'treatments') {
                                // Restore treatments shortcode content
                                if (treatmentsHTML) {
                                    targetContent.innerHTML = treatmentsHTML;
                                }
                            } else if (tabUrls[tabId]) {
                                // Load iframe for schedule widgets
                                targetContent.innerHTML = `<iframe class="hw-widget-iframe" src="${tabUrls[tabId]}" frameborder="0"></iframe>`;
                            }
                        }
                    }
                });
            });
            
            // Pre-populate empty tabs with loading state
            contents.forEach(content => {
                if (content.innerHTML.trim() === '') {
                    content.innerHTML = '<div class="hw-tab-loading"><div class="hw-tab-spinner"></div><p>Click to load...</p></div>';
                }
            });
        })();
        </script>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode wrapper for the booking tabs
 */
function hw_booking_tabs_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'active' => 'treatments',
    ), $atts, 'hw_booking_tabs' );
    
    return hw_render_booking_tabs_block( array( 'activeTab' => $atts['active'] ) );
}
add_shortcode( 'hw_booking_tabs', 'hw_booking_tabs_shortcode' );

