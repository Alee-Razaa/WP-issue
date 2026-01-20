<?php
/**
 * Mindbody Shortcodes - Live-Action Booking Interface
 * 
 * Provides reusable shortcodes for Mindbody booking interfaces
 * with real-time synchronized filtering and smart date selection
 * 
 * @package Home_Wellness
 * @since 1.1.0
 * @updated 2.0.0 - Complete Live-Action refactor with synchronized filtering,
 *                   smart 3-day auto-calculation, alphabetical staff sorting,
 *                   modal-based login, and jQuery No Conflict support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue assets for shortcodes
 * 
 * CSS is loaded when:
 * 1. Shortcode is directly in post content
 * 2. Booking tabs block is used (which dynamically renders shortcode)
 * 3. Page uses book-treatment template
 */
function hw_mindbody_enqueue_shortcode_assets() {
    global $post;
    
    $should_enqueue = false;
    
    if ( is_a( $post, 'WP_Post' ) ) {
        // Check for shortcode directly in content
        if ( has_shortcode( $post->post_content, 'hw_mindbody_appointments' ) ||
             has_shortcode( $post->post_content, 'hw_mindbody_therapists' ) ||
             has_shortcode( $post->post_content, 'hw_mindbody_schedule' ) ) {
            $should_enqueue = true;
        }
        
        // Check for booking tabs block (renders shortcode dynamically)
        if ( has_block( 'homewellness/booking-tabs', $post ) ) {
            $should_enqueue = true;
        }
        
        // Check for booking tabs shortcode
        if ( has_shortcode( $post->post_content, 'hw_booking_tabs' ) ) {
            $should_enqueue = true;
        }
        
        // Check if page uses book-treatment template
        $template = get_page_template_slug( $post->ID );
        if ( strpos( $template, 'booktreatment' ) !== false ) {
            $should_enqueue = true;
        }
    }
    
    if ( $should_enqueue ) {
        wp_enqueue_style(
            'hw-mindbody-appointments',
            get_template_directory_uri() . '/assets/css/mindbody-appointments.css',
            array(),
            filemtime( get_template_directory() . '/assets/css/mindbody-appointments.css' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'hw_mindbody_enqueue_shortcode_assets' );

/**
 * Shortcode: hw_mindbody_appointments
 * 
 * Displays the full appointments booking interface with filters
 * 
 * @since 1.1.0
 * @updated 1.2.0 - Fixed: Duration grouping, therapist display, date range
 * 
 * @param array $atts Shortcode attributes.
 * @return string
 */
function hw_mindbody_appointments_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'title'        => 'BOOK YOUR APPOINTMENT',
        'show_filters' => 'yes',
        'days'         => 7,
    ), $atts, 'hw_mindbody_appointments' );
    
    $default_location     = get_option( 'hw_mindbody_default_location', 'Primrose Hill' );
    $treatment_categories = hw_mindbody_get_treatment_categories();
    $api_url              = esc_url( rest_url( 'hw-mindbody/v1' ) );
    
    // Get WooCommerce cart URL for checkout integration
    $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart' );
    $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout' );
    
    ob_start();
    ?>
    <div class="hw-mindbody-appointments-container" 
         data-api-url="<?php echo esc_attr( $api_url ); ?>"
         data-location="<?php echo esc_attr( $default_location ); ?>"
         data-days="<?php echo intval( $atts['days'] ); ?>"
         data-categories='<?php echo wp_json_encode( $treatment_categories ); ?>'
         data-cart-url="<?php echo esc_attr( $cart_url ); ?>"
         data-checkout-url="<?php echo esc_attr( $checkout_url ); ?>"
         data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
         data-nonce="<?php echo esc_attr( wp_create_nonce( 'hw_mindbody_book' ) ); ?>">
        
        <div class="hw-mbo-header">
            <h2 class="hw-mbo-title"><?php echo esc_html( $atts['title'] ); ?></h2>
        </div>
        
        <?php if ( 'yes' === $atts['show_filters'] ) : ?>
        <div class="hw-mbo-filters">
            <div class="hw-mbo-filter-group hw-mbo-filter-treatment">
                <label><?php esc_html_e( 'Treatment Type', 'homewellness' ); ?></label>
                <div class="hw-mbo-multi-select" id="hw-treatment-dropdown">
                    <div class="hw-mbo-multi-select-trigger" id="hw-treatment-trigger">
                        <?php esc_html_e( 'Treatments...', 'homewellness' ); ?>
                    </div>
                    <div class="hw-mbo-multi-select-options" id="hw-treatment-options">
                        <div class="hw-mbo-dropdown-search">
                            <input type="text" id="hw-treatment-search" placeholder="<?php esc_attr_e( 'Search treatments...', 'homewellness' ); ?>" autocomplete="off" />
                        </div>
                        <div class="hw-mbo-categories-container" id="hw-categories-container"></div>
                    </div>
                </div>
            </div>
            
            <div class="hw-mbo-filter-group hw-mbo-filter-dates">
                <label><?php esc_html_e( 'Dates', 'homewellness' ); ?></label>
                <div class="hw-mbo-dates-container">
                    <div class="hw-mbo-date-display" id="hw-date-display-trigger">
                        <span id="hw-date-display-text">Select dates</span>
                        <span class="hw-mbo-date-chevron">▼</span>
                    </div>
                    <input type="hidden" id="hw-filter-start-date" />
                    <input type="hidden" id="hw-filter-end-date" />
                </div>
                <!-- Dual Month Calendar Picker -->
                <div class="hw-mbo-calendar-popup" id="hw-calendar-popup">
                    <div class="hw-mbo-calendar-wrapper">
                        <div class="hw-mbo-calendar-month" id="hw-calendar-month-1">
                            <div class="hw-mbo-calendar-header">
                                <button type="button" class="hw-mbo-calendar-nav hw-mbo-calendar-prev" id="hw-calendar-prev">‹</button>
                                <span class="hw-mbo-calendar-title" id="hw-calendar-title-1"></span>
                            </div>
                            <div class="hw-mbo-calendar-weekdays">
                                <span>su</span><span>mo</span><span>tu</span><span>we</span><span>th</span><span>fr</span><span>sa</span>
                            </div>
                            <div class="hw-mbo-calendar-days" id="hw-calendar-days-1"></div>
                        </div>
                        <div class="hw-mbo-calendar-month" id="hw-calendar-month-2">
                            <div class="hw-mbo-calendar-header">
                                <span class="hw-mbo-calendar-title" id="hw-calendar-title-2"></span>
                                <button type="button" class="hw-mbo-calendar-nav hw-mbo-calendar-next" id="hw-calendar-next">›</button>
                            </div>
                            <div class="hw-mbo-calendar-weekdays">
                                <span>su</span><span>mo</span><span>tu</span><span>we</span><span>th</span><span>fr</span><span>sa</span>
                            </div>
                            <div class="hw-mbo-calendar-days" id="hw-calendar-days-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="hw-mbo-filter-group">
                <label><?php esc_html_e( 'Time', 'homewellness' ); ?></label>
                <select id="hw-filter-time">
                    <option value=""><?php esc_html_e( 'Anytime', 'homewellness' ); ?></option>
                    <option value="06:00"><?php esc_html_e( '6:00 AM onwards', 'homewellness' ); ?></option>
                    <option value="09:00"><?php esc_html_e( '9:00 AM onwards', 'homewellness' ); ?></option>
                    <option value="12:00"><?php esc_html_e( '12:00 PM onwards', 'homewellness' ); ?></option>
                    <option value="15:00"><?php esc_html_e( '3:00 PM onwards', 'homewellness' ); ?></option>
                    <option value="18:00"><?php esc_html_e( '6:00 PM onwards', 'homewellness' ); ?></option>
                </select>
            </div>
            
            <div class="hw-mbo-filter-group">
                <label><?php esc_html_e( 'Therapist', 'homewellness' ); ?></label>
                <select id="hw-filter-therapist">
                    <option value=""><?php esc_html_e( 'Anyone', 'homewellness' ); ?></option>
                </select>
            </div>
            
            <div class="hw-mbo-filter-group hw-mbo-filter-search">
                <button class="hw-mbo-search-button" id="hw-search-button"><?php esc_html_e( 'Search', 'homewellness' ); ?></button>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="hw-mbo-schedule-container" id="hw-schedule-container">
            <div class="hw-mbo-loading" id="hw-loading-state">
                <div class="hw-mbo-spinner"></div>
                <p><?php esc_html_e( 'Loading available appointments...', 'homewellness' ); ?></p>
            </div>
            <div class="hw-mbo-error" id="hw-error-state" style="display: none;"></div>
            <div id="hw-schedule-content"></div>
        </div>
        
        <div class="hw-mbo-modal" id="hw-detail-modal">
            <div class="hw-mbo-modal-content">
                <div class="hw-mbo-modal-header">
                    <h2 id="hw-modal-title"><?php esc_html_e( 'Details', 'homewellness' ); ?></h2>
                    <span class="hw-mbo-modal-close" id="hw-modal-close">&times;</span>
                </div>
                <div class="hw-mbo-modal-body" id="hw-modal-body"></div>
            </div>
        </div>
        
        <div class="hw-mbo-powered-by">
            <span><?php esc_html_e( 'POWERED BY', 'homewellness' ); ?></span>
            <strong>mindbody</strong>
        </div>
    </div>
    
    <script>
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            initHWMindbodyAppointments();
        });
        
        function initHWMindbodyAppointments() {
            const container = document.querySelector('.hw-mindbody-appointments-container');
            if (!container) return;
            
            const apiBaseUrl = container.dataset.apiUrl;
            const baseUrl = apiBaseUrl.endsWith('/') ? apiBaseUrl : apiBaseUrl + '/';
            const defaultLocation = container.dataset.location || 'Primrose Hill';
            const appointmentCategories = JSON.parse(container.dataset.categories || '[]');
            const daysToShow = parseInt(container.dataset.days) || 7;
            const ajaxUrl = container.dataset.ajaxUrl;
            const nonce = container.dataset.nonce;
            const cartUrl = container.dataset.cartUrl;
            
            // State
            let allServices = [];
            let therapists = [];
            let therapistPhotos = {};
            let selectedServices = new Set();
            let selectedCategories = new Set();
            let filterDebounceTimer = null;
            let availabilityData = null; // Store availability data from API
            
            // ============ HELPER FUNCTIONS for Mindbody API inconsistencies ============
            
            /**
             * Safely get ID from various possible field names, cast to string
             */
            function safeGetId(item, fallback = null) {
                if (!item || typeof item !== 'object') return fallback !== null ? String(fallback) : null;
                const id = item.Id ?? item.ID ?? item.id ?? 
                           item.ServiceId ?? item.SessionTypeId ?? 
                           item.StaffId ?? fallback;
                return id !== null && id !== undefined ? String(id) : null;
            }
            
            /**
             * Safely get staff/therapist name from various possible field names
             */
            function safeGetStaffName(item) {
                if (!item || typeof item !== 'object') return '';
                // Direct name fields
                if (item.Name) return String(item.Name).trim();
                if (item.StaffName) return String(item.StaffName).trim();
                if (item.TherapistName) return String(item.TherapistName).trim();
                // FirstName + LastName
                const first = item.FirstName || item.firstName || '';
                const last = item.LastName || item.lastName || '';
                if (first || last) return (first + ' ' + last).trim();
                // Staff object
                if (item.Staff && typeof item.Staff === 'object') {
                    if (item.Staff.Name) return String(item.Staff.Name).trim();
                    const sFirst = item.Staff.FirstName || '';
                    const sLast = item.Staff.LastName || '';
                    if (sFirst || sLast) return (sFirst + ' ' + sLast).trim();
                }
                return '';
            }
            
            /**
             * Safely get service/session name from various possible field names
             */
            function safeGetServiceName(item) {
                if (!item || typeof item !== 'object') return '';
                if (item.Name) return String(item.Name).trim();
                if (item.ServiceName) return String(item.ServiceName).trim();
                if (item.SessionTypeName) return String(item.SessionTypeName).trim();
                if (item.SessionType && item.SessionType.Name) {
                    return String(item.SessionType.Name).trim();
                }
                return '';
            }
            
            /**
             * Compare IDs safely (handles int vs string inconsistencies)
             */
            function idsMatch(id1, id2) {
                if (id1 === null || id1 === undefined || id2 === null || id2 === undefined) {
                    return false;
                }
                return String(id1) === String(id2);
            }
            
            /**
             * Safely get datetime string from various possible field names
             */
            function safeGetDatetime(item) {
                if (!item || typeof item !== 'object') return '';
                return item.StartDateTime || item.startDateTime || 
                       item.DateTime || item.dateTime || 
                       item.Start || item.start || '';
            }
            
            /**
             * Safely get price from various possible field names
             */
            function safeGetPrice(item) {
                if (!item || typeof item !== 'object') return 0;
                const price = item.Price ?? item.price ?? 
                              item.OnlinePrice ?? item.onlinePrice ?? 
                              item.Amount ?? item.amount ?? 0;
                return parseFloat(price) || 0;
            }
            
            /**
             * Safely get duration from various possible field names
             */
            function safeGetDuration(item) {
                if (!item || typeof item !== 'object') return 0;
                const dur = item.Duration ?? item.duration ?? 
                            item.Length ?? item.length ?? 
                            item.Minutes ?? item.minutes ?? 0;
                return parseInt(dur) || 0;
            }
            
            // ============ END HELPER FUNCTIONS ============
            
            // DOM Elements
            const treatmentTrigger = document.getElementById('hw-treatment-trigger');
            const treatmentOptions = document.getElementById('hw-treatment-options');
            const treatmentSearch = document.getElementById('hw-treatment-search');
            const categoriesContainer = document.getElementById('hw-categories-container');
            const filterStartDate = document.getElementById('hw-filter-start-date');
            const filterEndDate = document.getElementById('hw-filter-end-date');
            const filterTime = document.getElementById('hw-filter-time');
            const filterTherapist = document.getElementById('hw-filter-therapist');
            const searchButton = document.getElementById('hw-search-button');
            const loadingState = document.getElementById('hw-loading-state');
            const errorState = document.getElementById('hw-error-state');
            const scheduleContent = document.getElementById('hw-schedule-content');
            const detailModal = document.getElementById('hw-detail-modal');
            const modalTitle = document.getElementById('hw-modal-title');
            const modalBody = document.getElementById('hw-modal-body');
            const modalClose = document.getElementById('hw-modal-close');
            const scheduleContainer = document.getElementById('hw-schedule-container');
            
            /**
             * REQUIREMENT 1: SMART DATE LOGIC
             * Format date as YYYY-MM-DD using LOCAL timezone
             */
            function formatDateLocal(d) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }
            
            /**
             * Format date for display: DD-MM-YYYY
             */
            function formatDateDisplay(d) {
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                return dd + '-' + mm + '-' + yyyy;
            }
            
            // Initialize with default dates (today + 2 days range)
            const today = new Date();
            today.setHours(12, 0, 0, 0);
            const todayStr = formatDateLocal(today);
            const endDateDefault = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 2, 12, 0, 0);  // +2 days default
            
            if (filterStartDate) {
                filterStartDate.value = todayStr;
                filterStartDate.min = todayStr;
            }
            if (filterEndDate) {
                filterEndDate.value = formatDateLocal(endDateDefault);
                filterEndDate.min = todayStr;
            }
            
            // =====================================================
            // DUAL MONTH CALENDAR PICKER WITH SMART 2-DAY LOGIC
            // =====================================================
            const calendarPopup = document.getElementById('hw-calendar-popup');
            const dateDisplayTrigger = document.getElementById('hw-date-display-trigger');
            const dateDisplayText = document.getElementById('hw-date-display-text');
            const calendarPrev = document.getElementById('hw-calendar-prev');
            const calendarNext = document.getElementById('hw-calendar-next');
            const calendarDays1 = document.getElementById('hw-calendar-days-1');
            const calendarDays2 = document.getElementById('hw-calendar-days-2');
            const calendarTitle1 = document.getElementById('hw-calendar-title-1');
            const calendarTitle2 = document.getElementById('hw-calendar-title-2');
            
            let calendarCurrentMonth = today.getMonth();
            let calendarCurrentYear = today.getFullYear();
            let calendarStartDate = new Date(today);
            let calendarEndDate = new Date(endDateDefault);
            let isSelectingStart = true;
            
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                               'July', 'August', 'September', 'October', 'November', 'December'];
            
            /**
             * REQUIREMENT 1: Update date display with smart formatting
             */
            function updateDateDisplay() {
                if (dateDisplayText) {
                    dateDisplayText.innerHTML = 
                        '<span class="hw-mbo-date-box">' + formatDateDisplay(calendarStartDate) + '</span>' +
                        '&nbsp;›&nbsp;' +
                        '<span class="hw-mbo-date-box">' + formatDateDisplay(calendarEndDate) + '</span>';
                }
                if (filterStartDate) filterStartDate.value = formatDateLocal(calendarStartDate);
                if (filterEndDate) filterEndDate.value = formatDateLocal(calendarEndDate);
            }
            
            function renderCalendarMonth(container, titleEl, year, month) {
                if (!container || !titleEl) return;
                
                titleEl.textContent = monthNames[month] + ' ' + year;
                
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const todayDate = new Date();
                todayDate.setHours(0, 0, 0, 0);
                
                let html = '';
                
                // Empty cells for days before the first day
                for (let i = 0; i < firstDay; i++) {
                    html += '<span class="hw-mbo-calendar-day empty"></span>';
                }
                
                // Actual days
                for (let d = 1; d <= daysInMonth; d++) {
                    const date = new Date(year, month, d);
                    date.setHours(0, 0, 0, 0);
                    
                    let classes = ['hw-mbo-calendar-day'];
                    
                    // Today
                    if (date.getTime() === todayDate.getTime()) {
                        classes.push('today');
                    }
                    
                    // Disabled (past dates)
                    if (date < todayDate) {
                        classes.push('disabled');
                    }
                    
                    // Selected start
                    if (calendarStartDate && date.getTime() === calendarStartDate.setHours(0,0,0,0)) {
                        classes.push('selected', 'range-start');
                    }
                    
                    // Selected end
                    if (calendarEndDate && date.getTime() === calendarEndDate.setHours(0,0,0,0)) {
                        classes.push('selected', 'range-end');
                    }
                    
                    // In range
                    if (calendarStartDate && calendarEndDate && date > calendarStartDate && date < calendarEndDate) {
                        classes.push('in-range');
                    }
                    
                    const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    html += '<span class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + d + '</span>';
                }
                
                container.innerHTML = html;
            }
            
            function renderCalendars() {
                renderCalendarMonth(calendarDays1, calendarTitle1, calendarCurrentYear, calendarCurrentMonth);
                
                let nextMonth = calendarCurrentMonth + 1;
                let nextYear = calendarCurrentYear;
                if (nextMonth > 11) {
                    nextMonth = 0;
                    nextYear++;
                }
                renderCalendarMonth(calendarDays2, calendarTitle2, nextYear, nextMonth);
            }
            
            /**
             * REQUIREMENT 1: Smart date selection - click one date, auto-calculate +2 days
             */
            function handleCalendarDayClick(e) {
                const dayEl = e.target.closest('.hw-mbo-calendar-day');
                if (!dayEl || dayEl.classList.contains('disabled') || dayEl.classList.contains('empty')) return;
                
                const dateStr = dayEl.dataset.date;
                if (!dateStr) return;
                
                const clickedDate = new Date(dateStr + 'T00:00:00');
                
                // REQUIREMENT 1: On single click, auto-set end date to +2 days (e.g., 22 → 24)
                calendarStartDate = clickedDate;
                const endDate = new Date(clickedDate);
                endDate.setDate(endDate.getDate() + 2);  // Changed from +3 to +2
                calendarEndDate = endDate;
                isSelectingStart = true;
                
                console.log('[DATE DEBUG] Selected:', formatDateDisplay(calendarStartDate), '→', formatDateDisplay(calendarEndDate));
                
                updateDateDisplay();
                renderCalendars();
                
                // REQUIREMENT 1: Close calendar and immediately fetch availability
                setTimeout(() => {
                    if (calendarPopup) calendarPopup.classList.remove('open');
                    if (dateDisplayTrigger) dateDisplayTrigger.classList.remove('active');
                    // Immediately trigger live fetch
                    loadAvailability();
                }, 300);
            }
            
            // Initialize calendar listeners
            if (calendarDays1) {
                calendarDays1.addEventListener('click', handleCalendarDayClick);
            }
            if (calendarDays2) {
                calendarDays2.addEventListener('click', handleCalendarDayClick);
            }
            
            if (calendarPrev) {
                calendarPrev.addEventListener('click', () => {
                    calendarCurrentMonth--;
                    if (calendarCurrentMonth < 0) {
                        calendarCurrentMonth = 11;
                        calendarCurrentYear--;
                    }
                    const now = new Date();
                    if (calendarCurrentYear < now.getFullYear() || 
                        (calendarCurrentYear === now.getFullYear() && calendarCurrentMonth < now.getMonth())) {
                        calendarCurrentMonth = now.getMonth();
                        calendarCurrentYear = now.getFullYear();
                    }
                    renderCalendars();
                });
            }
            
            if (calendarNext) {
                calendarNext.addEventListener('click', () => {
                    calendarCurrentMonth++;
                    if (calendarCurrentMonth > 11) {
                        calendarCurrentMonth = 0;
                        calendarCurrentYear++;
                    }
                    renderCalendars();
                });
            }
            
            if (dateDisplayTrigger) {
                dateDisplayTrigger.addEventListener('click', () => {
                    const isOpen = calendarPopup && calendarPopup.classList.contains('open');
                    if (isOpen) {
                        calendarPopup.classList.remove('open');
                        dateDisplayTrigger.classList.remove('active');
                    } else {
                        if (calendarPopup) calendarPopup.classList.add('open');
                        dateDisplayTrigger.classList.add('active');
                        renderCalendars();
                    }
                });
            }
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.hw-mbo-filter-dates') && calendarPopup) {
                    calendarPopup.classList.remove('open');
                    if (dateDisplayTrigger) dateDisplayTrigger.classList.remove('active');
                }
            });
            
            updateDateDisplay();
            
            // Initialize
            init();
            
            async function init() {
                console.log('[INIT] Starting initialization...');
                
                // Load therapists list first (smaller request, less likely to timeout)
                await loadTherapists();
                
                // Render category options from static list
                renderCategoryOptions();
                
                // Setup event listeners early so UI is responsive
                setupEventListeners();
                
                // Now load availability (this may be slow on first load)
                console.log('[INIT] Loading initial availability...');
                await loadAvailability();
                
                console.log('[INIT] Initialization complete');
            }
            
            async function loadAllServices() {
                // This function is now deprecated - loadAvailability fetches services directly
                console.log('[DEPRECATED] loadAllServices called - now handled by loadAvailability');
            }
            
            async function loadAllServicesLegacy() {
                try {
                    const response = await fetch(baseUrl + 'treatment-services');
                    if (response.ok) {
                        const data = await response.json();
                        
                        if (data.services && Array.isArray(data.services)) {
                            allServices = data.services;
                            
                            allServices.forEach(service => {
                                if (service.TherapistName && service.TherapistPhoto) {
                                    therapistPhotos[service.TherapistName] = service.TherapistPhoto;
                                }
                            });
                            
                            console.log('=== TREATMENT SERVICES LOADED ===');
                            console.log('Total services (filtered to 8 categories): ' + allServices.length);
                            console.log('Therapist photos available: ' + Object.keys(therapistPhotos).length);
                            
                            if (data.stats) {
                                console.log('--- FILTERING STATS ---');
                                console.log('Total in Mindbody: ' + data.stats.total_in_mindbody);
                                console.log('Not bookable online: ' + data.stats.not_bookable_online);
                                console.log('Wrong category: ' + data.stats.wrong_category);
                                console.log('Duplicates removed: ' + data.stats.duplicates_removed);
                                console.log('No duration: ' + data.stats.no_duration);
                                console.log('Final count: ' + data.stats.final_count);
                                console.log('Categories found:', data.stats.categories_found);
                            }
                        } else {
                            allServices = Array.isArray(data) ? data : [];
                            console.log('Loaded ' + allServices.length + ' services (fallback)');
                        }
                    }
                } catch (e) {
                    console.error('Failed to load services:', e);
                }
            }
            
            /**
             * REQUIREMENT 3: Load therapists and sort alphabetically A-Z
             */
            async function loadTherapists() {
                try {
                    const response = await fetch(baseUrl + 'staff-appointments');
                    if (response.ok) {
                        const data = await response.json();
                        
                        let staffList = Array.isArray(data) ? data : (data.Staff || data.data || []);
                        
                        therapists = staffList.filter(t => {
                            const hasSessionTypes = t.SessionTypes && t.SessionTypes.length > 0;
                            const hasName = t.Name || t.FirstName;
                            return hasName;
                        });
                        
                        if (therapists.length === 0) {
                            therapists = extractTherapistsFromServices();
                        }
                        
                        // REQUIREMENT 3: Sort alphabetically A-Z
                        therapists.sort((a, b) => {
                            const nameA = (a.Name || ((a.FirstName || '') + ' ' + (a.LastName || '')).trim()).toLowerCase();
                            const nameB = (b.Name || ((b.FirstName || '') + ' ' + (b.LastName || '')).trim()).toLowerCase();
                            return nameA.localeCompare(nameB);
                        });
                        
                        console.log('Loaded ' + therapists.length + ' therapists (sorted A-Z)');
                        renderTherapistOptions();
                    } else {
                        therapists = extractTherapistsFromServices();
                        renderTherapistOptions();
                    }
                } catch (e) {
                    console.error('Failed to load therapists:', e);
                    therapists = extractTherapistsFromServices();
                    renderTherapistOptions();
                }
            }
            
            function extractTherapistsFromServices() {
                const therapistSet = new Set();
                const therapistList = [];
                
                allServices.forEach(service => {
                    const serviceName = service.Name || '';
                    const match = serviceName.match(/\s-\s([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*(?:-|$)/);
                    if (match) {
                        const name = match[1].trim();
                        if (!therapistSet.has(name) && !name.match(/^\d+\s*min$/i)) {
                            therapistSet.add(name);
                            therapistList.push({
                                Name: name,
                                FirstName: name.split(' ')[0],
                                LastName: name.split(' ').slice(1).join(' '),
                                Id: 'extracted-' + therapistSet.size
                            });
                        }
                    }
                });
                
                // REQUIREMENT 3: Sort alphabetically
                return therapistList.sort((a, b) => a.Name.localeCompare(b.Name));
            }
            
            function renderCategoryOptions(searchTerm = '') {
                if (!categoriesContainer) return;
                
                const searchLower = searchTerm.toLowerCase();
                let html = '';
                let hasResults = false;
                
                appointmentCategories.forEach((category, catIndex) => {
                    const catId = 'hw-cat-' + catIndex;
                    const catLower = category.toLowerCase().trim();
                    
                    const categoryServices = allServices.filter(s => {
                        const serviceCategoryName = (s.ServiceCategory && s.ServiceCategory.Name) 
                            ? s.ServiceCategory.Name.toLowerCase().trim() 
                            : '';
                        const programName = (s.Program || '').toLowerCase().trim();
                        return serviceCategoryName === catLower || programName === catLower;
                    });
                    
                    const matchingServices = searchTerm 
                        ? categoryServices.filter(s => (s.Name || '').toLowerCase().includes(searchLower))
                        : categoryServices;
                    
                    const categoryMatches = catLower.includes(searchLower);
                    const showCategory = !searchTerm || categoryMatches || matchingServices.length > 0;
                    
                    if (!showCategory) return;
                    
                    hasResults = true;
                    const isExpanded = searchTerm && matchingServices.length > 0;
                    
                    html += '<div class="hw-mbo-category-section" data-category="' + escapeHtml(category) + '">';
                    html += '<div class="hw-mbo-category-header ' + (isExpanded ? 'expanded' : '') + '" data-cat-id="' + catId + '">';
                    html += '<input type="checkbox" class="hw-mbo-category-checkbox" value="' + escapeHtml(category) + '" id="' + catId + '" ' + (selectedCategories.has(category) ? 'checked' : '') + ' />';
                    html += '<label for="' + catId + '">' + escapeHtml(category) + '</label>';
                    html += '<span class="hw-mbo-expand-icon">▼</span>';
                    html += '</div>';
                    html += '<div class="hw-mbo-sub-services ' + (isExpanded ? 'open' : '') + '" id="hw-services-' + catId + '">';
                    
                    const servicesToShow = searchTerm ? matchingServices : categoryServices;
                    servicesToShow.forEach(service => {
                        // Use helper for ID (handles int vs string)
                        const svcId = safeGetId(service);
                        const serviceIdAttr = 'hw-service-' + svcId;
                        // Use helper for price
                        const price = safeGetPrice(service);
                        // Use helper for service name
                        const svcName = safeGetServiceName(service);
                        
                        html += '<div class="hw-mbo-sub-service-item">';
                        html += '<input type="checkbox" class="hw-mbo-service-checkbox" value="' + svcId + '" id="' + serviceIdAttr + '" ' + (selectedServices.has(String(svcId)) ? 'checked' : '') + ' />';
                        html += '<label for="' + serviceIdAttr + '">' + escapeHtml(svcName) + '</label>';
                        html += '<span class="hw-mbo-service-price">£' + parseFloat(price).toFixed(0) + '</span>';
                        html += '</div>';
                    });
                    
                    html += '</div></div>';
                });
                
                if (!hasResults) {
                    html = '<div class="hw-mbo-no-results">No treatments found matching "' + escapeHtml(searchTerm) + '"</div>';
                }
                
                categoriesContainer.innerHTML = html;
                attachDropdownListeners();
            }
            
            function attachDropdownListeners() {
                document.querySelectorAll('.hw-mbo-category-header').forEach(header => {
                    header.addEventListener('click', (e) => {
                        if (e.target.type !== 'checkbox') {
                            header.classList.toggle('expanded');
                            const catId = header.dataset.catId;
                            const subServices = document.getElementById('hw-services-' + catId);
                            if (subServices) subServices.classList.toggle('open');
                        }
                    });
                });
                
                document.querySelectorAll('.hw-mbo-category-checkbox').forEach(cb => {
                    cb.addEventListener('change', (e) => {
                        const category = e.target.value;
                        if (e.target.checked) {
                            selectedCategories.add(category);
                            console.log('[FILTER EVENT] Category ADDED:', category);
                        } else {
                            selectedCategories.delete(category);
                            console.log('[FILTER EVENT] Category REMOVED:', category);
                        }
                        console.log('[FILTER EVENT] Selected categories:', Array.from(selectedCategories));
                        updateTreatmentTriggerText();
                        // REQUIREMENT 2: Global live filtering with debounce
                        debouncedLoadAvailability();
                    });
                });
                
                document.querySelectorAll('.hw-mbo-service-checkbox').forEach(cb => {
                    cb.addEventListener('change', (e) => {
                        const serviceId = e.target.value;
                        if (e.target.checked) {
                            selectedServices.add(serviceId);
                            console.log('[FILTER EVENT] Service ADDED:', serviceId);
                        } else {
                            selectedServices.delete(serviceId);
                            console.log('[FILTER EVENT] Service REMOVED:', serviceId);
                        }
                        console.log('[FILTER EVENT] Selected services:', Array.from(selectedServices));
                        updateTreatmentTriggerText();
                        // REQUIREMENT 2: Global live filtering with debounce
                        debouncedLoadAvailability();
                    });
                });
            }
            
            function updateTreatmentTriggerText() {
                if (!treatmentTrigger) return;
                
                const catCount = selectedCategories.size;
                const serviceCount = selectedServices.size;
                
                if (catCount === 0 && serviceCount === 0) {
                    treatmentTrigger.textContent = 'Treatments...';
                } else if (catCount === 1 && serviceCount === 0) {
                    treatmentTrigger.textContent = Array.from(selectedCategories)[0];
                } else {
                    const total = catCount + serviceCount;
                    treatmentTrigger.textContent = total + ' Selected';
                }
            }
            
            /**
             * REQUIREMENT 3: Render therapist dropdown sorted A-Z
             */
            function renderTherapistOptions() {
                if (!filterTherapist) return;
                
                let html = '<option value="">Anyone</option>';
                therapists.forEach(t => {
                    const name = t.Name || ((t.FirstName || '') + ' ' + (t.LastName || '')).trim();
                    if (name) {
                        html += '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + ' | Therapist</option>';
                    }
                });
                filterTherapist.innerHTML = html;
            }
            
            /**
             * REQUIREMENT 2: Global live filtering with 200ms debounce (reduced from 400ms)
             */
            function debouncedLoadAvailability() {
                console.log('[FILTER DEBUG] Debounce triggered - will fetch in 200ms');
                clearTimeout(filterDebounceTimer);
                filterDebounceTimer = setTimeout(() => {
                    console.log('[FILTER DEBUG] Debounce complete - fetching now');
                    loadAvailability();
                }, 200);
            }
            
            /**
             * REQUIREMENT 5: Apply loading overlay during filter changes
             * FIX: Makes real AJAX request to server for fresh data
             */
            async function loadAvailability() {
                console.log('[FETCH DEBUG] ====== loadAvailability START ======');
                
                // REQUIREMENT 5: Loading state with 50% opacity overlay
                if (scheduleContainer) {
                    scheduleContainer.classList.add('hw-mbo-loading-overlay');
                    console.log('[FETCH DEBUG] Loading overlay applied');
                }
                if (loadingState) loadingState.style.display = 'flex';
                if (errorState) errorState.style.display = 'none';
                if (scheduleContent) scheduleContent.innerHTML = '';
                
                try {
                    // Gather all filter values
                    const startDate = filterStartDate ? filterStartDate.value : '';
                    const endDate = filterEndDate ? filterEndDate.value : '';
                    const therapistName = filterTherapist ? filterTherapist.value : '';
                    const timeSlot = filterTime ? filterTime.value : '';
                    const categories = Array.from(selectedCategories).join(',');
                    const services = Array.from(selectedServices).join(',');
                    
                    console.log('[FETCH DEBUG] Filter values:', {
                        startDate: startDate,
                        endDate: endDate,
                        therapist: therapistName,
                        time: timeSlot,
                        categories: categories,
                        services: services,
                        location: defaultLocation
                    });
                    
                    // Build query params for server request
                    const params = new URLSearchParams();
                    if (startDate) params.append('start_date', startDate);
                    if (endDate) params.append('end_date', endDate);
                    if (therapistName) params.append('therapist', therapistName);
                    if (timeSlot) params.append('time', timeSlot);
                    if (categories) params.append('categories', categories);
                    if (services) params.append('services', services);
                    params.append('location', defaultLocation);
                    
                    const fetchUrl = baseUrl + 'treatment-services?' + params.toString();
                    console.log('[FETCH DEBUG] Fetching URL:', fetchUrl);
                    
                    const fetchStart = performance.now();
                    const response = await fetch(fetchUrl);
                    const fetchTime = Math.round(performance.now() - fetchStart);
                    
                    console.log('[FETCH DEBUG] Response status:', response.status, '(took ' + fetchTime + 'ms)');
                    
                    if (!response.ok) {
                        throw new Error('Server returned ' + response.status);
                    }
                    
                    const data = await response.json();
                    console.log('[FETCH DEBUG] Response data:', {
                        hasServices: !!data.services,
                        servicesCount: data.services ? data.services.length : 0,
                        totalCount: data.total_count,
                        stats: data.stats
                    });
                    
                    // Log availability filtering status - NEW FIELDS
                    console.log('[AVAILABILITY DEBUG] Data source:', data.data_source || 'unknown');
                    console.log('[AVAILABILITY DEBUG] Has live data:', data.has_live_data);
                    console.log('[AVAILABILITY DEBUG] Dates returned:', data.dates || []);
                    console.log('[AVAILABILITY DEBUG] Therapists returned:', data.therapists ? data.therapists.length : 0);
                    
                    // Log legacy availability filtering status
                    if (data.stats) {
                        console.log('[AVAILABILITY DEBUG] Availability checked:', data.stats.availability_checked);
                        console.log('[AVAILABILITY DEBUG] Force empty (no availability):', data.stats.force_empty);
                        console.log('[AVAILABILITY DEBUG] Bookable items found:', data.stats.bookable_items_count);
                        console.log('[AVAILABILITY DEBUG] Services filtered out:', data.stats.no_availability || 0);
                        console.log('[AVAILABILITY DEBUG] Dates with availability:', data.stats.dates_with_availability || []);
                    }
                    
                    // Store availability data for rendering (handle property name variations)
                    if (data.availability) {
                        availabilityData = data.availability;
                        // Normalize property names for consistent access
                        if (!availabilityData.slots_by_date && availabilityData.slotsByDate) {
                            availabilityData.slots_by_date = availabilityData.slotsByDate;
                        }
                        if (!availabilityData.staff_available && availabilityData.staffAvailable) {
                            availabilityData.staff_available = availabilityData.staffAvailable;
                        }
                        console.log('[AVAILABILITY DEBUG] Availability data received:', {
                            dates: availabilityData.dates || [],
                            staffCount: availabilityData.staff_available ? availabilityData.staff_available.length : 0,
                            slotsPerDate: Object.keys(availabilityData.slots_by_date || {})
                        });
                    } else {
                        availabilityData = null;
                    }
                    
                    // Check if no availability for therapist
                    if (data.availability_info && data.availability_info.message) {
                        console.log('[AVAILABILITY DEBUG] No availability message:', data.availability_info.message);
                    }
                    
                    // Update allServices with fresh server data BEFORE rendering
                    if (data.services && Array.isArray(data.services)) {
                        allServices = data.services;
                        console.log('[FETCH DEBUG] Updated allServices with ' + allServices.length + ' items');
                        
                        // Update therapist photos from fresh data (use helper for name fallbacks)
                        allServices.forEach(service => {
                            const therapistName = safeGetStaffName(service);
                            const therapistPhoto = service.TherapistPhoto || service.therapistPhoto || 
                                                   service.Photo || service.ImageUrl || '';
                            if (therapistName && therapistPhoto) {
                                therapistPhotos[therapistName] = therapistPhoto;
                            }
                        });
                    } else if (Array.isArray(data)) {
                        allServices = data;
                        console.log('[FETCH DEBUG] Updated allServices (array format) with ' + allServices.length + ' items');
                    }
                    
                    // Now process and render with updated data
                    console.log('[FETCH DEBUG] Calling processAndRenderServices...');
                    await processAndRenderServices();
                    console.log('[FETCH DEBUG] ====== loadAvailability COMPLETE ======');
                    
                } catch (e) {
                    console.error('[FETCH DEBUG] ERROR:', e);
                    if (errorState) {
                        errorState.textContent = 'Unable to load treatments. Please try again later.';
                        errorState.style.display = 'block';
                    }
                } finally {
                    // Always remove loading overlay in finally block
                    if (loadingState) loadingState.style.display = 'none';
                    if (scheduleContainer) {
                        scheduleContainer.classList.remove('hw-mbo-loading-overlay');
                        console.log('[FETCH DEBUG] Loading overlay removed');
                    }
                }
            }
            
            /**
             * REQUIREMENT 2: Process server data and render - called AFTER server fetch
             * FIX: This now only processes data that was already fetched from server
             */
            async function processAndRenderServices() {
                console.log('[RENDER DEBUG] ====== processAndRenderServices START ======');
                console.log('[RENDER DEBUG] allServices count:', allServices.length);
                
                // Work with already-fetched allServices (updated from server)
                let services = [...allServices];
                console.log('[RENDER DEBUG] Initial services count:', services.length);
                
                // Local filtering for categories (server already filters, but double-check)
                if (selectedCategories.size > 0) {
                    console.log('[RENDER DEBUG] Applying local category filter:', Array.from(selectedCategories));
                    services = services.filter(s => {
                        const serviceCat = s.Category || (s.ServiceCategory && s.ServiceCategory.Name) || s.Program || '';
                        for (const cat of selectedCategories) {
                            if (serviceCat.toLowerCase().includes(cat.toLowerCase().split(' ')[0]) ||
                                cat.toLowerCase().includes(serviceCat.toLowerCase().split(' ')[0])) {
                                return true;
                            }
                        }
                        return false;
                    });
                    console.log('[RENDER DEBUG] After category filter:', services.length);
                }
                
                // Local filtering for specific services (use idsMatch helper)
                if (selectedServices.size > 0) {
                    console.log('[RENDER DEBUG] Applying local service filter:', Array.from(selectedServices));
                    const selectedIds = Array.from(selectedServices);
                    services = services.filter(s => {
                        const svcId = safeGetId(s);
                        return selectedIds.some(selId => idsMatch(svcId, selId));
                    });
                    console.log('[RENDER DEBUG] After service filter:', services.length);
                }
                
                // Additional local therapist filter (backup if server didn't filter)
                const selectedTherapistName = filterTherapist ? filterTherapist.value : '';
                if (selectedTherapistName) {
                    console.log('[RENDER DEBUG] Applying local therapist filter:', selectedTherapistName);
                    const therapistLower = selectedTherapistName.toLowerCase().trim();
                    const therapistFirstName = therapistLower.split(' ')[0];
                    
                    services = services.filter(s => {
                        // Use helper for therapist name with fallbacks
                        const svcTherapist = safeGetStaffName(s).toLowerCase();
                        if (svcTherapist && svcTherapist.includes(therapistLower)) {
                            return true;
                        }
                        if (svcTherapist && svcTherapist.includes(therapistFirstName)) {
                            return true;
                        }
                        
                        // Check service name for therapist name pattern
                        const serviceName = safeGetServiceName(s).toLowerCase();
                        if (serviceName.includes(therapistLower)) {
                            return true;
                        }
                        if (serviceName.includes(therapistFirstName)) {
                            return true;
                        }
                        
                        // Extract therapist from service name pattern "Treatment - Therapist Name - Duration"
                        const match = serviceName.match(/\s-\s([a-z]+(?:\s+[a-z]+)*)\s*(?:-|$)/i);
                        if (match) {
                            const extractedName = match[1].toLowerCase();
                            if (extractedName.includes(therapistFirstName) || therapistLower.includes(extractedName)) {
                                return true;
                            }
                        }
                        
                        return false;
                    });
                    console.log('[RENDER DEBUG] After therapist filter:', services.length);
                    
                    // Debug: log what services passed the filter
                    if (services.length > 0) {
                        console.log('[RENDER DEBUG] Sample filtered services:', services.slice(0, 3).map(s => ({
                            name: s.Name,
                            therapist: s.TherapistName
                        })));
                    }
                }
                
                // Time filtering applied during rendering
                const selectedTime = filterTime ? filterTime.value : '';
                console.log('[RENDER DEBUG] Time filter (for rendering):', selectedTime || 'none');
                
                // Group services by therapist + treatment name
                const groupedByTherapistAndTreatment = {};
                
                services.forEach(service => {
                    // Use helper for service name
                    const serviceName = safeGetServiceName(service);
                    
                    // Use helper for therapist name with fallbacks
                    let therapistName = safeGetStaffName(service);
                    if (!therapistName) {
                        const therapistMatch = serviceName.match(/\s-\s([A-Z][a-z]+(?:\s+[A-Z]\.?)?(?:\s+[A-Z][a-z]+)?)\s*(?:-|$|\d|\')/i);
                        if (therapistMatch) {
                            therapistName = therapistMatch[1].trim();
                            if (/^\d+\s*(min|mins)?$/i.test(therapistName)) {
                                therapistName = 'General';
                            }
                        } else {
                            therapistName = 'General';
                        }
                    }
                    
                    // Use helper for duration
                    const duration = safeGetDuration(service);
                    
                    let baseName = serviceName
                        .replace(/\s*-\s*[A-Z][a-z]+(?:\s+[A-Z]\.?)?(?:\s+[A-Z][a-z]+)?\s*(?:-|$)/gi, ' ')
                        .replace(/\s*-?\s*\d+\s*(?:min|mins|minutes|\')\s*/gi, '')
                        .replace(/\s*-\s*\d+\s*$/g, '')
                        .replace(/\s+/g, ' ')
                        .trim();
                    
                    if (!baseName) baseName = serviceName;
                    
                    const groupKey = therapistName + '||' + baseName;
                    
                    if (!groupedByTherapistAndTreatment[groupKey]) {
                        groupedByTherapistAndTreatment[groupKey] = {
                            therapist: therapistName,
                            baseName: baseName,
                            category: service.Category || '',
                            variants: []
                        };
                    }
                    
                    const existingDurations = groupedByTherapistAndTreatment[groupKey].variants.map(v => v.duration);
                    if (!existingDurations.includes(duration) || duration === 0) {
                        if (duration === 0 && existingDurations.includes(0)) {
                            return;
                        }
                        
                        groupedByTherapistAndTreatment[groupKey].variants.push({
                            id: service.Id,
                            duration: duration,
                            price: parseFloat(service.Price) || 0,
                            fullName: serviceName,
                            service: service
                        });
                    }
                });
                
                // Sort variants by duration
                Object.values(groupedByTherapistAndTreatment).forEach(group => {
                    const seen = new Set();
                    group.variants = group.variants.filter(v => {
                        if (seen.has(v.duration)) return false;
                        seen.add(v.duration);
                        return true;
                    });
                    group.variants.sort((a, b) => a.duration - b.duration);
                });
                
                // FIX: Data is now synced with server before rendering
                console.log('Rendering ' + Object.keys(groupedByTherapistAndTreatment).length + ' treatment groups');
                renderServicesSchedule(groupedByTherapistAndTreatment);
            }
            
            function renderServicesSchedule(groupedData) {
                if (!scheduleContent) return;
                
                const groups = Object.values(groupedData);
                
                if (groups.length === 0) {
                    // Check if therapist filter is active
                    const therapistName = filterTherapist ? filterTherapist.value : '';
                    const startDate = filterStartDate ? filterStartDate.value : '';
                    const endDate = filterEndDate ? filterEndDate.value : '';
                    
                    let message = '<div class="hw-mbo-no-results"><h3>No Treatments Available</h3>';
                    
                    if (therapistName) {
                        // Format dates for display
                        let dateRange = '';
                        if (startDate && endDate) {
                            const start = new Date(startDate);
                            const end = new Date(endDate);
                            const options = { weekday: 'long', day: 'numeric', month: 'long' };
                            dateRange = start.toLocaleDateString('en-GB', options) + ' to ' + end.toLocaleDateString('en-GB', options);
                        }
                        
                        message += '<p><strong>' + escapeHtml(therapistName) + '</strong> has no availability';
                        if (dateRange) {
                            message += ' between ' + dateRange;
                        }
                        message += '.</p>';
                        message += '<p>Please try different dates or select another therapist.</p>';
                    } else {
                        message += '<p>Try adjusting your filters to find available treatments.</p>';
                    }
                    
                    message += '</div>';
                    scheduleContent.innerHTML = message;
                    return;
                }
                
                const byTherapist = {};
                groups.forEach(group => {
                    if (!byTherapist[group.therapist]) {
                        byTherapist[group.therapist] = [];
                    }
                    byTherapist[group.therapist].push(group);
                });
                
                const therapistNames = Object.keys(byTherapist).sort();
                
                let html = '';
                
                const now = new Date();
                const todayYear = now.getFullYear();
                const todayMonth = now.getMonth();
                const todayDay = now.getDate();
                const today = new Date(todayYear, todayMonth, todayDay, 12, 0, 0);
                
                function parseLocalDate(dateStr) {
                    if (!dateStr) return null;
                    const parts = dateStr.split('-');
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10) - 1;
                    const day = parseInt(parts[2], 10);
                    return new Date(year, month, day, 12, 0, 0);
                }
                
                let startDate, endDate;
                
                if (filterStartDate && filterStartDate.value) {
                    startDate = parseLocalDate(filterStartDate.value);
                } else {
                    startDate = new Date(todayYear, todayMonth, todayDay, 12, 0, 0);
                }
                
                if (filterEndDate && filterEndDate.value) {
                    endDate = parseLocalDate(filterEndDate.value);
                } else {
                    endDate = new Date(todayYear, todayMonth, todayDay + daysToShow - 1, 12, 0, 0);
                }
                
                const daysDiff = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
                const maxDays = Math.min(daysDiff, daysToShow);
                
                for (let dayOffset = 0; dayOffset < maxDays; dayOffset++) {
                    const currentDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + dayOffset, 12, 0, 0);
                    
                    if (currentDate > endDate) continue;
                    
                    // Format date as YYYY-MM-DD for availability lookup
                    const dateKey = currentDate.getFullYear() + '-' + 
                        String(currentDate.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(currentDate.getDate()).padStart(2, '0');
                    
                    const dayName = currentDate.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }).toUpperCase();
                    
                    // Check if we have availability data for this day
                    let daySlots = [];
                    let availableStaffOnDay = new Set();
                    let availableTimesOnDay = {};
                    
                    if (availabilityData && availabilityData.slots_by_date && availabilityData.slots_by_date[dateKey]) {
                        daySlots = availabilityData.slots_by_date[dateKey];
                        console.log('[RENDER] Day ' + dateKey + ' has ' + daySlots.length + ' slots from API');
                        
                        // Build set of available staff and their times for this day
                        // Use helper functions to handle API property inconsistencies
                        daySlots.forEach(slot => {
                            // Handle various possible property names for staff name
                            const staffName = (slot.staff_name || slot.StaffName || slot.staffName || 
                                              (slot.Staff && slot.Staff.Name) || '').toLowerCase();
                            if (staffName) {
                                availableStaffOnDay.add(staffName);
                                // Track actual times
                                if (!availableTimesOnDay[staffName]) {
                                    availableTimesOnDay[staffName] = [];
                                }
                                // Handle various possible property names for start time
                                const startTime = slot.start_time || slot.StartDateTime || slot.startDateTime || '';
                                if (startTime) {
                                    const timeStr = startTime.includes('T') ? startTime.split('T')[1] : startTime;
                                    if (timeStr) {
                                        availableTimesOnDay[staffName].push(timeStr.substring(0, 5));
                                    }
                                }
                            }
                        });
                        console.log('[RENDER] Available staff on ' + dateKey + ':', Array.from(availableStaffOnDay));
                    }
                    
                    // Filter therapists for this day if we have availability data
                    let therapistsForDay = therapistNames;
                    if (availabilityData && availabilityData.dates && availabilityData.dates.length > 0) {
                        // We have availability data - filter to only show available staff
                        therapistsForDay = therapistNames.filter(name => {
                            const nameLower = name.toLowerCase();
                            const firstName = name.split(' ')[0].toLowerCase();
                            // Check if this therapist is available on this day
                            return availableStaffOnDay.has(nameLower) || 
                                   availableStaffOnDay.has(firstName) ||
                                   Array.from(availableStaffOnDay).some(s => s.includes(firstName) || firstName.includes(s.split(' ')[0]));
                        });
                        console.log('[RENDER] Filtered therapists for ' + dateKey + ': ' + therapistsForDay.length + ' of ' + therapistNames.length);
                    }
                    
                    // Skip this day if no therapists are available
                    if (therapistsForDay.length === 0 && availabilityData && availabilityData.dates && availabilityData.dates.length > 0) {
                        console.log('[RENDER] Skipping day ' + dateKey + ' - no available therapists');
                        continue;
                    }
                    
                    html += '<div class="hw-mbo-day-section">';
                    html += '<div class="hw-mbo-day-header"><h3>' + escapeHtml(dayName) + '</h3></div>';
                    html += '<div class="hw-mbo-table-wrapper">';
                    html += '<table class="hw-mbo-table">';
                    html += '<thead><tr>';
                    html += '<th>Therapist</th>';
                    html += '<th>Treatment</th>';
                    html += '<th>Location</th>';
                    html += '<th>Duration</th>';
                    html += '<th>Start Time</th>';
                    html += '<th>Price/Package</th>';
                    html += '<th>Availability</th>';
                    html += '</tr></thead><tbody>';
                    
                    therapistsForDay.forEach(therapistName => {
                        const treatments = byTherapist[therapistName];
                        if (!treatments) return;
                        
                        treatments.forEach(treatment => {
                            const variants = treatment.variants;
                            const defaultVariant = variants[0];
                            
                            let durationHtml = '';
                            if (variants.length > 1) {
                                durationHtml = '<select class="hw-mbo-duration-select" data-treatment-key="' + escapeHtml(therapistName + '||' + treatment.baseName) + '">';
                                variants.forEach((v, idx) => {
                                    durationHtml += '<option value="' + v.id + '" data-price="' + v.price + '" data-duration="' + v.duration + '"' + (idx === 0 ? ' selected' : '') + '>' + v.duration + ' min</option>';
                                });
                                durationHtml += '</select>';
                            } else {
                                durationHtml = (defaultVariant.duration || '-') + ' min';
                            }
                            
                            // Generate time slots - use actual availability if we have it
                            const allTimeSlots = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
                            let availableTimes = [];
                            
                            // Check if we have actual times from availability API for this therapist
                            const therapistLower = therapistName.toLowerCase();
                            const therapistFirst = therapistName.split(' ')[0].toLowerCase();
                            let actualTimes = null;
                            
                            // Find matching staff in availableTimesOnDay
                            for (const [staffKey, times] of Object.entries(availableTimesOnDay)) {
                                if (staffKey.includes(therapistFirst) || therapistFirst.includes(staffKey.split(' ')[0])) {
                                    actualTimes = times;
                                    break;
                                }
                            }
                            
                            if (actualTimes && actualTimes.length > 0) {
                                // Use actual available times from API
                                availableTimes = [...new Set(actualTimes)].sort();
                                console.log('[RENDER] Using actual times for ' + therapistName + ' on ' + dateKey + ':', availableTimes);
                            } else {
                                // Fall back to filter-based times
                                const selectedTimeFilter = filterTime ? filterTime.value : '';
                                
                                if (selectedTimeFilter) {
                                    // Filter times to only show times >= selected time
                                    const filterHour = parseInt(selectedTimeFilter.split(':')[0], 10);
                                    availableTimes = allTimeSlots.filter(t => {
                                        const slotHour = parseInt(t.split(':')[0], 10);
                                        return slotHour >= filterHour;
                                    });
                                    // Take first 4 available slots after filter
                                    availableTimes = availableTimes.slice(0, 4);
                                } else {
                                    // No filter - show business hours (9am-5pm)
                                    availableTimes = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];
                                }
                            }
                            
                            // Ensure we have at least some times
                            if (availableTimes.length === 0) {
                                availableTimes = ['18:00', '19:00', '20:00'];
                            }
                            
                            const serviceData = {
                                id: defaultVariant.id,
                                name: treatment.baseName,
                                therapist: therapistName,
                                price: defaultVariant.price,
                                duration: defaultVariant.duration,
                                location: defaultLocation,
                                variants: variants
                            };
                            
                            const therapistPhoto = therapistPhotos[therapistName] || '';
                            const photoHtml = therapistPhoto 
                                ? '<img src="' + escapeHtml(therapistPhoto) + '" alt="' + escapeHtml(therapistName) + '" class="hw-mbo-therapist-photo" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline-flex\';" /><span class="hw-mbo-therapist-initials" style="display:none;">' + escapeHtml(therapistName.split(' ').map(n => n.charAt(0)).join('').substring(0,2)) + '</span>'
                                : '<span class="hw-mbo-therapist-initials">' + escapeHtml(therapistName.split(' ').map(n => n.charAt(0)).join('').substring(0,2)) + '</span>';
                            
                            html += '<tr data-treatment-key="' + escapeHtml(therapistName + '||' + treatment.baseName) + '">';
                            html += '<td class="hw-mbo-therapist-cell"><div class="hw-mbo-therapist-info">' + photoHtml + '<a href="#" class="hw-mbo-therapist-name" data-staff-name="' + escapeHtml(therapistName) + '">' + escapeHtml(therapistName) + ' | Therapist</a></div></td>';
                            html += '<td><a href="#" class="hw-mbo-treatment-name" data-session-type-id="' + defaultVariant.id + '" data-session-type-name="' + escapeHtml(treatment.baseName) + '">' + escapeHtml(treatment.baseName) + '</a></td>';
                            html += '<td class="hw-mbo-location">' + escapeHtml(defaultLocation) + '</td>';
                            html += '<td class="hw-mbo-duration-cell">' + durationHtml + '</td>';
                            html += '<td><select class="hw-mbo-time-select">' + availableTimes.map(t => '<option value="' + t + '">' + t + '</option>').join('') + '</select></td>';
                            html += '<td class="hw-mbo-price" data-base-price="' + defaultVariant.price + '">£' + parseFloat(defaultVariant.price).toFixed(0) + '</td>';
                            html += '<td><button class="hw-mbo-book-btn" data-service=\'' + JSON.stringify(serviceData).replace(/'/g, "&#39;") + '\'>Book Now</button></td>';
                            html += '</tr>';
                        });
                    });
                    
                    html += '</tbody></table></div></div>';
                }
                
                scheduleContent.innerHTML = html;
                
                // FIX: Event delegation is now used in setupEventListeners()
                // These individual listeners are kept as backup for non-delegated elements
                document.querySelectorAll('.hw-mbo-therapist-name').forEach(el => {
                    el.addEventListener('click', handleTherapistClick);
                });
                
                document.querySelectorAll('.hw-mbo-treatment-name').forEach(el => {
                    el.addEventListener('click', handleTreatmentClick);
                });
                
                // FIX: Book Now buttons now use event delegation - see setupEventListeners()
                // Individual listeners removed to prevent double-firing
                
                document.querySelectorAll('.hw-mbo-duration-select').forEach(select => {
                    select.addEventListener('change', handleDurationChange);
                });
            }
            
            function handleDurationChange(e) {
                const select = e.target;
                const selectedOption = select.options[select.selectedIndex];
                const newPrice = selectedOption.dataset.price;
                const newDuration = selectedOption.dataset.duration;
                const newServiceId = select.value;
                
                const row = select.closest('tr');
                if (row) {
                    const priceCell = row.querySelector('.hw-mbo-price');
                    if (priceCell) {
                        priceCell.textContent = '£' + parseFloat(newPrice).toFixed(0);
                    }
                    
                    const bookBtn = row.querySelector('.hw-mbo-book-btn');
                    if (bookBtn) {
                        const serviceData = JSON.parse(bookBtn.dataset.service.replace(/&#39;/g, "'"));
                        serviceData.id = newServiceId;
                        serviceData.price = parseFloat(newPrice);
                        serviceData.duration = parseInt(newDuration);
                        bookBtn.dataset.service = JSON.stringify(serviceData).replace(/'/g, "&#39;");
                    }
                }
            }
            
            function handleTherapistClick(e) {
                e.preventDefault();
                const staffName = e.target.dataset.staffName;
                
                if (modalTitle) modalTitle.textContent = 'Therapist: ' + staffName;
                if (modalBody) modalBody.innerHTML = '<div class="hw-mbo-loading"><div class="hw-mbo-spinner"></div><p>Loading...</p></div>';
                if (detailModal) detailModal.classList.add('open');
                
                fetch(baseUrl + 'staff-details?staff_name=' + encodeURIComponent(staffName))
                    .then(response => response.json())
                    .then(data => {
                        renderStaffModal(data);
                    })
                    .catch(() => {
                        if (modalBody) modalBody.innerHTML = '<div class="hw-mbo-error">Failed to load therapist details.</div>';
                    });
            }
            
            function renderStaffModal(staff) {
                if (!modalBody) return;
                
                const staffName = staff.Name || ((staff.FirstName || '') + ' ' + (staff.LastName || '')).trim();
                const imageUrl = staff.ImageUrl || staff.ImageURL || staff.Photo || '';
                const initials = staffName.split(' ').map(n => n.charAt(0).toUpperCase()).join('').substring(0, 2);
                
                let html = '<div class="hw-mbo-staff-modal">';
                
                html += '<div class="hw-mbo-staff-image-section">';
                if (imageUrl) {
                    html += '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(staffName) + '" class="hw-mbo-staff-photo" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';" />';
                    html += '<div class="hw-mbo-staff-initials" style="display: none;">' + escapeHtml(initials) + '</div>';
                } else {
                    html += '<div class="hw-mbo-staff-initials">' + escapeHtml(initials) + '</div>';
                }
                html += '</div>';
                
                html += '<div class="hw-mbo-modal-details">';
                
                if (staffName) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Name:</div><div class="hw-mbo-modal-value">' + escapeHtml(staffName) + '</div></div>';
                }
                
                html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Role:</div><div class="hw-mbo-modal-value">' + escapeHtml(staff.Role || 'Therapist') + '</div></div>';
                html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Location:</div><div class="hw-mbo-modal-value">' + escapeHtml(defaultLocation) + '</div></div>';
                
                if (staff.Email) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Email:</div><div class="hw-mbo-modal-value"><a href="mailto:' + escapeHtml(staff.Email) + '" style="color: var(--hw-blue);">' + escapeHtml(staff.Email) + '</a></div></div>';
                }
                
                if (staff.MobilePhone || staff.HomePhone) {
                    const phone = staff.MobilePhone || staff.HomePhone;
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Phone:</div><div class="hw-mbo-modal-value"><a href="tel:' + escapeHtml(phone) + '" style="color: var(--hw-blue);">' + escapeHtml(phone) + '</a></div></div>';
                }
                
                if (staff.SessionTypes && staff.SessionTypes.length > 0) {
                    const services = staff.SessionTypes.map(s => s.Name || s).filter(Boolean);
                    if (services.length > 0) {
                        html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Services:</div><div class="hw-mbo-modal-value">' + services.map(s => escapeHtml(s)).join(', ') + '</div></div>';
                    }
                }
                
                html += '</div>';
                
                if (staff.Bio) {
                    html += '<div class="hw-mbo-modal-bio"><strong>About:</strong><br>' + staff.Bio + '</div>';
                }
                
                html += '</div>';
                
                modalBody.innerHTML = html;
            }
            
            function handleTreatmentClick(e) {
                e.preventDefault();
                const sessionTypeId = e.target.dataset.sessionTypeId;
                const sessionTypeName = e.target.dataset.sessionTypeName;
                
                if (modalTitle) modalTitle.textContent = 'Treatment: ' + sessionTypeName;
                if (modalBody) modalBody.innerHTML = '<div class="hw-mbo-loading"><div class="hw-mbo-spinner"></div><p>Loading...</p></div>';
                if (detailModal) detailModal.classList.add('open');
                
                fetch(baseUrl + 'service-details?service_id=' + encodeURIComponent(sessionTypeId))
                    .then(response => response.json())
                    .then(data => {
                        renderServiceModal(data);
                    })
                    .catch(() => {
                        if (modalBody) modalBody.innerHTML = '<div class="hw-mbo-error">Failed to load treatment details.</div>';
                    });
            }

            
            function renderServiceModal(service) {
                if (!modalBody) return;
                
                let html = '<div class="hw-mbo-modal-details">';
                
                if (service.Name) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Name:</div><div class="hw-mbo-modal-value">' + escapeHtml(service.Name) + '</div></div>';
                }
                
                html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Location:</div><div class="hw-mbo-modal-value">' + escapeHtml(defaultLocation) + '</div></div>';
                
                if (service.Price || service.OnlinePrice) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Price:</div><div class="hw-mbo-modal-value hw-mbo-price">£' + parseFloat(service.Price || service.OnlinePrice).toFixed(2) + '</div></div>';
                }
                
                html += '</div>';
                
                if (service.Description) {
                    html += '<div class="hw-mbo-modal-bio">' + service.Description + '</div>';
                }
                
                modalBody.innerHTML = html;
            }
            
            /**
             * REQUIREMENT 4: Modal-based login - check if logged in, open auth popup if guest
             */
            function handleBookNow(e) {
                // REQUIREMENT 4: Check if user is logged in
                const isLoggedIn = document.body.classList.contains('logged-in');
                
                if (!isLoggedIn) {
                    // REQUIREMENT 4: Trigger existing login popup without redirecting
                    document.dispatchEvent(new Event('openAuthPopup'));
                    return;
                }
                
                const serviceData = JSON.parse(e.target.dataset.service.replace(/&#39;/g, "'"));
                const row = e.target.closest('tr');
                const timeSelect = row ? row.querySelector('.hw-mbo-time-select') : null;
                const selectedTime = timeSelect ? timeSelect.value : '10:00';
                
                const daySection = e.target.closest('.hw-mbo-day-section');
                const dayHeader = daySection ? daySection.querySelector('.hw-mbo-day-header h3') : null;
                const selectedDate = dayHeader ? dayHeader.textContent : 'Today';
                
                if (modalTitle) modalTitle.textContent = 'Book Appointment';
                if (modalBody) {
                    modalBody.innerHTML = 
                        '<div class="hw-mbo-modal-details">' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Treatment:</div><div class="hw-mbo-modal-value">' + escapeHtml(serviceData.name) + '</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Therapist:</div><div class="hw-mbo-modal-value">' + escapeHtml(serviceData.therapist) + '</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Date:</div><div class="hw-mbo-modal-value">' + escapeHtml(selectedDate) + '</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Time:</div><div class="hw-mbo-modal-value">' + escapeHtml(selectedTime) + '</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Duration:</div><div class="hw-mbo-modal-value">' + (serviceData.duration || '-') + ' min</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Location:</div><div class="hw-mbo-modal-value">' + escapeHtml(serviceData.location) + '</div></div>' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Price:</div><div class="hw-mbo-modal-value hw-mbo-price">£' + parseFloat(serviceData.price).toFixed(2) + '</div></div>' +
                        '</div>' +
                        '<div class="hw-mbo-modal-action">' +
                        '<button class="hw-mbo-book-btn hw-mbo-add-to-cart-btn" id="hw-confirm-booking" data-service-id="' + serviceData.id + '" data-price="' + serviceData.price + '" data-name="' + escapeHtml(serviceData.name) + '" data-therapist="' + escapeHtml(serviceData.therapist) + '" data-time="' + escapeHtml(selectedTime) + '" data-date="' + escapeHtml(selectedDate) + '">Add to Cart & Checkout</button>' +
                        '</div>';
                }
                if (detailModal) detailModal.classList.add('open');
                
                const confirmBtn = document.getElementById('hw-confirm-booking');
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', handleConfirmBooking);
                }
            }
            
            async function handleConfirmBooking(e) {
                const btn = e.target;
                const originalText = btn.textContent;
                btn.textContent = 'Processing...';
                btn.disabled = true;
                
                const formData = new FormData();
                formData.append('action', 'hw_add_mindbody_treatment_to_cart');
                formData.append('nonce', nonce);
                formData.append('service_id', btn.dataset.serviceId);
                formData.append('service_name', btn.dataset.name);
                formData.append('price', btn.dataset.price);
                formData.append('therapist', btn.dataset.therapist);
                formData.append('appointment_time', btn.dataset.time);
                formData.append('appointment_date', btn.dataset.date);
                
                try {
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = cartUrl;
                    } else {
                        alert('Failed to add to cart: ' + (data.data || 'Unknown error'));
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                } catch (error) {
                    console.error('Booking error:', error);
                    alert('An error occurred. Please try again.');
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            }
            
            /**
             * REQUIREMENT 2: Setup event listeners for all filters with live updating
             * FIX: Added event delegation for Book Now buttons to survive re-renders
             */
            function setupEventListeners() {
                if (treatmentTrigger) {
                    treatmentTrigger.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (treatmentOptions) treatmentOptions.classList.toggle('open');
                        treatmentTrigger.classList.toggle('open');
                        if (treatmentOptions && treatmentOptions.classList.contains('open') && treatmentSearch) {
                            treatmentSearch.focus();
                        }
                    });
                }
                
                if (treatmentSearch) {
                    treatmentSearch.addEventListener('input', (e) => {
                        renderCategoryOptions(e.target.value);
                    });
                }
                
                if (treatmentOptions) {
                    treatmentOptions.addEventListener('click', (e) => {
                        e.stopPropagation();
                    });
                }
                
                document.addEventListener('click', () => {
                    if (treatmentOptions) treatmentOptions.classList.remove('open');
                    if (treatmentTrigger) treatmentTrigger.classList.remove('open');
                });
                
                // REQUIREMENT 2: All filters trigger live updates with SERVER refresh
                if (filterStartDate) {
                    filterStartDate.addEventListener('change', function() {
                        console.log('[FILTER EVENT] Start date changed to:', this.value);
                        debouncedLoadAvailability();
                    });
                }
                if (filterEndDate) {
                    filterEndDate.addEventListener('change', function() {
                        console.log('[FILTER EVENT] End date changed to:', this.value);
                        debouncedLoadAvailability();
                    });
                }
                if (filterTime) {
                    filterTime.addEventListener('change', function() {
                        console.log('[FILTER EVENT] Time changed to:', this.value);
                        debouncedLoadAvailability();
                    });
                }
                if (filterTherapist) {
                    filterTherapist.addEventListener('change', function() {
                        console.log('[FILTER EVENT] Therapist changed to:', this.value);
                        debouncedLoadAvailability();
                    });
                }
                
                if (searchButton) {
                    searchButton.addEventListener('click', function() {
                        console.log('[FILTER EVENT] Search button clicked');
                        loadAvailability();
                    });
                }
                
                console.log('[INIT] Event listeners attached to filters');
                
                if (modalClose) {
                    modalClose.addEventListener('click', () => {
                        if (detailModal) detailModal.classList.remove('open');
                    });
                }
                
                if (detailModal) {
                    detailModal.addEventListener('click', (e) => {
                        if (e.target === detailModal) detailModal.classList.remove('open');
                    });
                }
                
                // FIX: Event Delegation for Book Now buttons - survives table re-renders
                // Using jQuery event delegation pattern: $(document).on('click', selector, handler)
                $(document).on('click', '.hw-mbo-book-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[EVENT] Book Now button clicked');
                    handleBookNow({ target: this });
                });
                
                // FIX: Event delegation for duration select changes
                $(document).on('change', '.hw-mbo-duration-select', function(e) {
                    handleDurationChange({ target: this });
                });
                
                // FIX: Event delegation for therapist name clicks
                $(document).on('click', '.hw-mbo-therapist-name', function(e) {
                    e.preventDefault();
                    handleTherapistClick({ target: this, preventDefault: function() {} });
                });
                
                // FIX: Event delegation for treatment name clicks
                $(document).on('click', '.hw-mbo-treatment-name', function(e) {
                    e.preventDefault();
                    handleTreatmentClick({ target: this, preventDefault: function() {} });
                });
                
                console.log('Event delegation initialized for dynamic elements');
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'hw_mindbody_appointments', 'hw_mindbody_appointments_shortcode' );

/**
 * AJAX Handler: Add Mindbody treatment to WooCommerce cart
 */
function hw_add_mindbody_treatment_to_cart() {
    check_ajax_referer( 'hw_mindbody_book', 'nonce' );
    
    $service_id   = sanitize_text_field( wp_unslash( $_POST['service_id'] ?? '' ) );
    $service_name = sanitize_text_field( wp_unslash( $_POST['service_name'] ?? '' ) );
    $price        = floatval( $_POST['price'] ?? 0 );
    $therapist    = sanitize_text_field( wp_unslash( $_POST['therapist'] ?? '' ) );
    $appt_time    = sanitize_text_field( wp_unslash( $_POST['appointment_time'] ?? '' ) );
    $appt_date    = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
    
    if ( empty( $service_id ) || empty( $service_name ) || $price <= 0 ) {
        wp_send_json_error( 'Invalid service data' );
    }
    
    // Check if WooCommerce is active
    if ( ! function_exists( 'WC' ) ) {
        wp_send_json_error( 'WooCommerce is not active' );
    }
    
    // Find or create a WooCommerce product for this service
    $product_id = wc_get_product_id_by_sku( 'mb-' . $service_id );
    
    if ( ! $product_id ) {
        // Create a simple product
        $product = new WC_Product_Simple();
        $product->set_name( $service_name );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_sku( 'mb-' . $service_id );
        $product->set_regular_price( $price );
        $product->set_price( $price );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product_id = $product->save();
        
        // Set category
        wp_set_object_terms( $product_id, 'Treatment', 'product_cat' );
    } else {
        // Update price if changed
        $product = wc_get_product( $product_id );
        if ( $product && $product->get_price() != $price ) {
            $product->set_regular_price( $price );
            $product->set_price( $price );
            $product->save();
        }
    }
    
    // Add to cart with custom data
    $cart_item_data = array(
        'mindbody_service_id' => $service_id,
        'mindbody_therapist'  => $therapist,
        'mindbody_date'       => $appt_date,
        'mindbody_time'       => $appt_time,
    );
    
    $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
    
    if ( $cart_item_key ) {
        wp_send_json_success( array( 'cart_key' => $cart_item_key ) );
    } else {
        wp_send_json_error( 'Failed to add to cart' );
    }
}
add_action( 'wp_ajax_hw_add_mindbody_treatment_to_cart', 'hw_add_mindbody_treatment_to_cart' );
add_action( 'wp_ajax_nopriv_hw_add_mindbody_treatment_to_cart', 'hw_add_mindbody_treatment_to_cart' );

/**
 * Display custom cart item data
 */
function hw_display_mindbody_cart_item_data( $item_data, $cart_item ) {
    if ( isset( $cart_item['mindbody_therapist'] ) && ! empty( $cart_item['mindbody_therapist'] ) ) {
        $item_data[] = array(
            'key'   => 'Therapist',
            'value' => sanitize_text_field( $cart_item['mindbody_therapist'] ),
        );
    }
    if ( isset( $cart_item['mindbody_date'] ) && ! empty( $cart_item['mindbody_date'] ) ) {
        $item_data[] = array(
            'key'   => 'Date',
            'value' => sanitize_text_field( $cart_item['mindbody_date'] ),
        );
    }
    if ( isset( $cart_item['mindbody_time'] ) && ! empty( $cart_item['mindbody_time'] ) ) {
        $item_data[] = array(
            'key'   => 'Time',
            'value' => sanitize_text_field( $cart_item['mindbody_time'] ),
        );
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'hw_display_mindbody_cart_item_data', 10, 2 );

/**
 * Save custom cart item data to order
 */
function hw_save_mindbody_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['mindbody_service_id'] ) ) {
        $item->add_meta_data( '_mindbody_service_id', $values['mindbody_service_id'], true );
    }
    if ( isset( $values['mindbody_therapist'] ) ) {
        $item->add_meta_data( 'Therapist', $values['mindbody_therapist'], true );
    }
    if ( isset( $values['mindbody_date'] ) ) {
        $item->add_meta_data( 'Appointment Date', $values['mindbody_date'], true );
    }
    if ( isset( $values['mindbody_time'] ) ) {
        $item->add_meta_data( 'Appointment Time', $values['mindbody_time'], true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'hw_save_mindbody_order_item_meta', 10, 4 );

/**
 * Shortcode: hw_mindbody_therapists
 */
function hw_mindbody_therapists_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'title' => 'Our Therapists',
        'limit' => 12,
    ), $atts, 'hw_mindbody_therapists' );
    
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return '<p>' . esc_html__( 'Mindbody API is not configured.', 'homewellness' ) . '</p>';
    }
    
    $therapists = $api->get_appointment_instructors();
    
    if ( is_wp_error( $therapists ) ) {
        return '<p>' . esc_html__( 'Unable to load therapists.', 'homewellness' ) . '</p>';
    }
    
    if ( empty( $therapists ) ) {
        return '<p>' . esc_html__( 'No therapists found.', 'homewellness' ) . '</p>';
    }
    
    $therapists = array_slice( $therapists, 0, intval( $atts['limit'] ) );
    
    ob_start();
    ?>
    <div class="hw-mbo-therapists">
        <h2><?php echo esc_html( $atts['title'] ); ?></h2>
        <div class="hw-mbo-therapists-grid">
            <?php foreach ( $therapists as $therapist ) : ?>
                <?php
                $name      = trim( ( $therapist['FirstName'] ?? '' ) . ' ' . ( $therapist['LastName'] ?? '' ) );
                $image_url = $therapist['ImageUrl'] ?? '';
                $bio       = $therapist['Bio'] ?? '';
                ?>
                <div class="hw-mbo-therapist-card">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="hw-mbo-therapist-image" />
                    <?php endif; ?>
                    <h3><?php echo esc_html( $name ); ?></h3>
                    <?php if ( $bio ) : ?>
                        <p><?php echo esc_html( wp_trim_words( $bio, 20, '...' ) ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'hw_mindbody_therapists', 'hw_mindbody_therapists_shortcode' );

/**
 * Shortcode: hw_mindbody_schedule
 */
function hw_mindbody_schedule_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'title' => 'Class Schedule',
        'days'  => 7,
    ), $atts, 'hw_mindbody_schedule' );
    
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return '<p>' . esc_html__( 'Mindbody API is not configured.', 'homewellness' ) . '</p>';
    }
    
    $classes = $api->get_classes( array(
        'StartDateTime' => gmdate( 'Y-m-d\TH:i:s' ),
        'EndDateTime'   => gmdate( 'Y-m-d\TH:i:s', strtotime( '+' . intval( $atts['days'] ) . ' days' ) ),
    ) );
    
    if ( is_wp_error( $classes ) ) {
        return '<p>' . esc_html__( 'Unable to load class schedule.', 'homewellness' ) . '</p>';
    }
    
    if ( empty( $classes ) ) {
        return '<p>' . esc_html__( 'No classes scheduled.', 'homewellness' ) . '</p>';
    }
    
    ob_start();
    ?>
    <div class="hw-mbo-schedule">
        <h2><?php echo esc_html( $atts['title'] ); ?></h2>
        <div class="hw-mbo-schedule-list">
            <?php foreach ( $classes as $class ) : ?>
                <?php
                $class_name  = $class['ClassDescription']['Name'] ?? $class['Name'] ?? 'Class';
                $start_time  = isset( $class['StartDateTime'] ) ? gmdate( 'l, M j g:i A', strtotime( $class['StartDateTime'] ) ) : '';
                $teacher     = trim( ( $class['Staff']['FirstName'] ?? '' ) . ' ' . ( $class['Staff']['LastName'] ?? '' ) );
                $location    = $class['Location']['Name'] ?? '';
                ?>
                <div class="hw-mbo-class-item">
                    <h3><?php echo esc_html( $class_name ); ?></h3>
                    <?php if ( $start_time ) : ?>
                        <p class="hw-mbo-class-time"><?php echo esc_html( $start_time ); ?></p>
                    <?php endif; ?>
                    <?php if ( $teacher ) : ?>
                        <p class="hw-mbo-class-teacher"><?php esc_html_e( 'with', 'homewellness' ); ?> <?php echo esc_html( $teacher ); ?></p>
                    <?php endif; ?>
                    <?php if ( $location ) : ?>
                        <p class="hw-mbo-class-location"><?php echo esc_html( $location ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'hw_mindbody_schedule', 'hw_mindbody_schedule_shortcode' );
