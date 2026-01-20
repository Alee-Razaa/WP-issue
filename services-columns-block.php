<?php
/**
 * Services Columns Gutenberg Block
 * 
 * 3-column section with background images, overlay, text, and icon boxes.
 * Used for YOGA, PILATES, TREATMENTS sections.
 * 
 * @package HomeWellness
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Services Columns Block
 */
function hw_register_services_columns_block() {
    // Register block script
    wp_register_script(
        'hw-services-columns-block-editor',
        get_template_directory_uri() . '/assets/js/services-columns-block-editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor'),
        filemtime(get_template_directory() . '/assets/js/services-columns-block-editor.js'),
        true
    );

    // Register frontend styles
    wp_register_style(
        'hw-services-columns-block-style',
        get_template_directory_uri() . '/assets/css/services-columns-block.css',
        array(),
        filemtime(get_template_directory() . '/assets/css/services-columns-block.css')
    );

    // Default image paths
    $theme_uri = get_template_directory_uri();
    $defaults = array(
        'column1' => array(
            'background' => $theme_uri . '/public/images/yoga.webp',
            'icon' => $theme_uri . '/public/images/yoga-box.png',
            'label' => 'YOGA',
            'url' => '/yoga/'
        ),
        'column2' => array(
            'background' => $theme_uri . '/public/images/pilates.webp',
            'icon' => $theme_uri . '/public/images/move-box.png',
            'label' => 'PILATES',
            'url' => '/pilates/'
        ),
        'column3' => array(
            'background' => $theme_uri . '/public/images/treatments.webp',
            'icon' => $theme_uri . '/public/images/calm-box.png',
            'label' => 'TREATMENTS',
            'url' => '/treatments/'
        )
    );

    register_block_type('homewellness/services-columns', array(
        'editor_script' => 'hw-services-columns-block-editor',
        'style'         => 'hw-services-columns-block-style',
        'render_callback' => 'hw_render_services_columns_block',
        'attributes' => array(
            // Spacing
            'marginTop' => array(
                'type' => 'number',
                'default' => 109
            ),
            'marginBottom' => array(
                'type' => 'number',
                'default' => 95
            ),
            'columnGap' => array(
                'type' => 'number',
                'default' => 20
            ),
            'columnHeight' => array(
                'type' => 'number',
                'default' => 545
            ),
            // Overlay
            'overlayColor' => array(
                'type' => 'string',
                'default' => 'rgba(0, 0, 0, 0.50)'
            ),
            'overlayHoverColor' => array(
                'type' => 'string',
                'default' => 'rgba(0, 0, 0, 0.35)'
            ),
            // Animation
            'enableAnimation' => array(
                'type' => 'boolean',
                'default' => true
            ),
            // Show/Hide Icons
            'showIcons' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'animationDuration' => array(
                'type' => 'number',
                'default' => 0.6
            ),
            // Column 1
            'column1Background' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column1Icon' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column1Label' => array(
                'type' => 'string',
                'default' => 'YOGA'
            ),
            'column1Url' => array(
                'type' => 'string',
                'default' => '/yoga/'
            ),
            // Column 2
            'column2Background' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column2Icon' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column2Label' => array(
                'type' => 'string',
                'default' => 'PILATES'
            ),
            'column2Url' => array(
                'type' => 'string',
                'default' => '/pilates/'
            ),
            // Column 3
            'column3Background' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column3Icon' => array(
                'type' => 'string',
                'default' => ''
            ),
            'column3Label' => array(
                'type' => 'string',
                'default' => 'TREATMENTS'
            ),
            'column3Url' => array(
                'type' => 'string',
                'default' => '/treatments/'
            ),
            // Typography
            'labelFontSize' => array(
                'type' => 'number',
                'default' => 24
            ),
            'labelFontWeight' => array(
                'type' => 'number',
                'default' => 500
            ),
            'labelLetterSpacing' => array(
                'type' => 'number',
                'default' => 2
            )
        )
    ));

    // Pass defaults to editor
    wp_localize_script('hw-services-columns-block-editor', 'hwServicesDefaults', $defaults);
}
add_action('init', 'hw_register_services_columns_block');

/**
 * Render the Services Columns Block
 */
function hw_render_services_columns_block($attributes) {
    $theme_uri = get_template_directory_uri();
    
    // Get attributes with defaults
    $marginTop = isset($attributes['marginTop']) ? intval($attributes['marginTop']) : 109;
    $marginBottom = isset($attributes['marginBottom']) ? intval($attributes['marginBottom']) : 95;
    $columnGap = isset($attributes['columnGap']) ? intval($attributes['columnGap']) : 20;
    $columnHeight = isset($attributes['columnHeight']) ? intval($attributes['columnHeight']) : 545;
    $overlayColor = isset($attributes['overlayColor']) ? $attributes['overlayColor'] : 'rgba(0, 0, 0, 0.50)';
    $overlayHoverColor = isset($attributes['overlayHoverColor']) ? $attributes['overlayHoverColor'] : 'rgba(0, 0, 0, 0.35)';
    $enableAnimation = isset($attributes['enableAnimation']) ? $attributes['enableAnimation'] : true;
    $animationDuration = isset($attributes['animationDuration']) ? floatval($attributes['animationDuration']) : 0.6;
    $showIcons = isset($attributes['showIcons']) ? $attributes['showIcons'] : true;
    $labelFontSize = isset($attributes['labelFontSize']) ? intval($attributes['labelFontSize']) : 24;
    $labelFontWeight = isset($attributes['labelFontWeight']) ? intval($attributes['labelFontWeight']) : 500;
    $labelLetterSpacing = isset($attributes['labelLetterSpacing']) ? intval($attributes['labelLetterSpacing']) : 2;
    
    // Column data with defaults
    $columns = array(
        array(
            'background' => !empty($attributes['column1Background']) ? $attributes['column1Background'] : $theme_uri . '/public/images/yoga.webp',
            'icon' => !empty($attributes['column1Icon']) ? $attributes['column1Icon'] : $theme_uri . '/public/images/yoga-box.png',
            'label' => isset($attributes['column1Label']) ? $attributes['column1Label'] : 'YOGA',
            'url' => isset($attributes['column1Url']) ? $attributes['column1Url'] : '/yoga/'
        ),
        array(
            'background' => !empty($attributes['column2Background']) ? $attributes['column2Background'] : $theme_uri . '/public/images/pilates.webp',
            'icon' => !empty($attributes['column2Icon']) ? $attributes['column2Icon'] : $theme_uri . '/public/images/move-box.png',
            'label' => isset($attributes['column2Label']) ? $attributes['column2Label'] : 'PILATES',
            'url' => isset($attributes['column2Url']) ? $attributes['column2Url'] : '/pilates/'
        ),
        array(
            'background' => !empty($attributes['column3Background']) ? $attributes['column3Background'] : $theme_uri . '/public/images/treatments.webp',
            'icon' => !empty($attributes['column3Icon']) ? $attributes['column3Icon'] : $theme_uri . '/public/images/calm-box.png',
            'label' => isset($attributes['column3Label']) ? $attributes['column3Label'] : 'TREATMENTS',
            'url' => isset($attributes['column3Url']) ? $attributes['column3Url'] : '/treatments/'
        )
    );

    // Generate unique ID for this block instance
    $block_id = 'hw-services-' . uniqid();
    
    // Animation class
    $animation_class = $enableAnimation ? 'hw-animate' : '';

    ob_start();
    ?>
    <style>
        #<?php echo esc_attr($block_id); ?> {
            margin-top: <?php echo $marginTop; ?>px;
            margin-bottom: <?php echo $marginBottom; ?>px;
            gap: <?php echo $columnGap; ?>px;
        }
        #<?php echo esc_attr($block_id); ?> .hw-service-column {
            height: <?php echo $columnHeight; ?>px;
        }
        #<?php echo esc_attr($block_id); ?> .hw-service-overlay {
            background: <?php echo esc_attr($overlayColor); ?>;
            transition: background <?php echo $animationDuration; ?>s ease;
        }
        #<?php echo esc_attr($block_id); ?> .hw-service-column:hover .hw-service-overlay {
            background: <?php echo esc_attr($overlayHoverColor); ?>;
        }
        #<?php echo esc_attr($block_id); ?> .hw-service-label {
            font-size: <?php echo $labelFontSize; ?>px;
            font-weight: <?php echo $labelFontWeight; ?>;
            letter-spacing: <?php echo $labelLetterSpacing; ?>px;
        }
        <?php if ($enableAnimation) : ?>
        #<?php echo esc_attr($block_id); ?>.hw-animate .hw-service-column {
            opacity: 0;
            transform: translateY(30px);
            animation: hwServiceFadeIn <?php echo $animationDuration; ?>s ease forwards;
        }
        #<?php echo esc_attr($block_id); ?>.hw-animate .hw-service-column:nth-child(1) {
            animation-delay: 0.1s;
        }
        #<?php echo esc_attr($block_id); ?>.hw-animate .hw-service-column:nth-child(2) {
            animation-delay: 0.25s;
        }
        #<?php echo esc_attr($block_id); ?>.hw-animate .hw-service-column:nth-child(3) {
            animation-delay: 0.4s;
        }
        <?php endif; ?>
        @media (max-width: 768px) {
            #<?php echo esc_attr($block_id); ?> {
                margin-top: <?php echo max(40, $marginTop / 2); ?>px;
                margin-bottom: <?php echo max(40, $marginBottom / 2); ?>px;
            }
            #<?php echo esc_attr($block_id); ?> .hw-service-column {
                height: <?php echo max(300, $columnHeight * 0.65); ?>px;
            }
        }
    </style>
    
    <section id="<?php echo esc_attr($block_id); ?>" class="hw-services-columns <?php echo esc_attr($animation_class); ?>">
        <?php foreach ($columns as $index => $column) : ?>
        <a href="<?php echo esc_url($column['url']); ?>" class="hw-service-column">
            <div class="hw-service-background" style="background-image: url('<?php echo esc_url($column['background']); ?>');"></div>
            <div class="hw-service-overlay"></div>
            <div class="hw-service-content">
                <span class="hw-service-label"><?php echo esc_html($column['label']); ?></span>
            </div>
<?php if ($showIcons) : ?>
            <div class="hw-service-icon">
                <img src="<?php echo esc_url($column['icon']); ?>" alt="<?php echo esc_attr($column['label']); ?>" />
            </div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </section>

    <script>
    (function() {
        // Intersection Observer for animation trigger
        if (typeof IntersectionObserver !== 'undefined') {
            var block = document.getElementById('<?php echo esc_js($block_id); ?>');
            if (block && block.classList.contains('hw-animate')) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('hw-in-view');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.2 });
                observer.observe(block);
            }
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

