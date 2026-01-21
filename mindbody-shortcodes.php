<?php
/**
 * Mindbody Shortcodes
 * 
 * Provides reusable shortcodes for Mindbody booking interfaces
 * 
 * @package Home_Wellness
 * @since 1.1.0
 * @updated 1.2.0 - Fixed therapist display, duration grouping, date range
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
    (function() {
        'use strict';
        
        document.addEventListener('DOMContentLoaded', function() {
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
            let therapistPhotos = {}; // Store therapist photo URLs
            let selectedServices = new Set();
            let selectedCategories = new Set();
            let filterDebounceTimer = null;
            
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
            
            // CRITICAL: Format date as YYYY-MM-DD using LOCAL timezone (NOT toISOString which uses UTC!)
            function formatDateLocal(d) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }
            
            // CATALOG MODEL: No default date range needed
            const today = new Date();
            today.setHours(12, 0, 0, 0);
            
            // Set min date for calendar picker (for preferred date selection)
            const todayStr = formatDateLocal(today);
            if (filterStartDate) {
                filterStartDate.min = todayStr;
            }
            if (filterEndDate) {
                filterEndDate.min = todayStr;
            }
            
            // =====================================================
            // DUAL MONTH CALENDAR PICKER (v1.4.0)
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
            
            function updateDateDisplay() {
                // Format for display: DD-MM-YYYY
                const formatDateDisplay = (d) => {
                    const dd = String(d.getDate()).padStart(2, '0');
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const yyyy = d.getFullYear();
                    return dd + '-' + mm + '-' + yyyy;
                };
                if (dateDisplayText) {
                    dateDisplayText.textContent = formatDateDisplay(calendarStartDate) + '  ›  ' + formatDateDisplay(calendarEndDate);
                }
                // CRITICAL: Use local date format for input values (NOT toISOString which uses UTC!)
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
            
            function handleCalendarDayClick(e) {
                const dayEl = e.target.closest('.hw-mbo-calendar-day');
                if (!dayEl || dayEl.classList.contains('disabled') || dayEl.classList.contains('empty')) return;
                
                const dateStr = dayEl.dataset.date;
                if (!dateStr) return;
                
                const clickedDate = new Date(dateStr + 'T00:00:00');
                
                // CATALOG MODEL: Date selection is for "preferred date" only, not for fetching
                calendarStartDate = clickedDate;
                calendarEndDate = clickedDate;
                
                updateDateDisplay();
                renderCalendars();
                
                // Close popup (NO AUTO-FETCH - catalog doesn't need it)
                setTimeout(() => {
                    if (calendarPopup) calendarPopup.classList.remove('open');
                    if (dateDisplayTrigger) dateDisplayTrigger.classList.remove('active');
                }, 300);
            }
            
            // Initialize calendar
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
                    // Don't go before current month
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
            
            // Close calendar when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.hw-mbo-filter-dates') && calendarPopup) {
                    calendarPopup.classList.remove('open');
                    if (dateDisplayTrigger) dateDisplayTrigger.classList.remove('active');
                }
            });
            
            init();
            
            async function init() {
                console.log('=== INITIALIZING CATALOG-BASED BOOKING SYSTEM ===');
                
                await Promise.all([
                    loadAllServices(),
                    loadTherapists()
                ]);
                
                renderCategoryOptions();
                await loadAvailability();
                setupEventListeners();
            }
            
            async function loadAllServices() {
                try {
                    console.log('\n=== FETCHING SERVICE CATALOG ===');
                    
                    // Fetch service catalog (NO date range - this is catalog-based)
                    const url = baseUrl + 'treatment-services';
                    console.log('API URL:', url);
                    
                    const response = await fetch(url);
                    console.log('Response Status:', response.status, response.statusText);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('❌ API REQUEST FAILED');
                        console.error('Status:', response.status);
                        console.error('Response:', errorText);
                        throw new Error('API returned status ' + response.status);
                    }
                    
                    const data = await response.json();
                    console.log('✓ API Response received:', data);
                    
                    // Check for error in response
                    if (data.error) {
                        console.error('❌ API ERROR:', data.error);
                        console.error('Message:', data.message || 'No details provided');
                        allServices = [];
                        if (errorState) {
                            errorState.textContent = data.error + ': ' + (data.message || '');
                            errorState.style.display = 'block';
                        }
                        return;
                    }
                    
                    // CATALOG MODEL - services are organized by category
                    if (data.services_by_category && typeof data.services_by_category === 'object') {
                        // Flatten services from categories
                        allServices = [];
                        for (const category in data.services_by_category) {
                            const categoryServices = data.services_by_category[category];
                            allServices = allServices.concat(categoryServices);
                        }
                        
                        // Build therapist photos lookup
                        allServices.forEach(service => {
                            if (service.TherapistName && service.TherapistPhoto) {
                                therapistPhotos[service.TherapistName] = service.TherapistPhoto;
                            }
                        });
                        
                        console.log('\n=== ✓ SERVICE CATALOG LOADED ===');
                        console.log('Booking Model:', data.model || 'catalog');
                        console.log('Total Services:', allServices.length);
                        console.log('Categories:', data.categories ? data.categories.join(', ') : 'N/A');
                        console.log('Therapists:', data.therapists ? data.therapists.length : 0);
                        console.log('Note:', data.note || 'Catalog-based booking');
                        
                        if (allServices.length > 0) {
                            console.log('Sample Service:', allServices[0]);
                        } else {
                            console.warn('⚠️ No services in catalog');
                        }
                    } else {
                        console.error('❌ UNEXPECTED API STRUCTURE');
                        console.error('Expected data.services_by_category object, got:', typeof data.services_by_category);
                        console.error('Full response:', data);
                        allServices = [];
                    }
                } catch (e) {
                    console.error('\n❌ FATAL ERROR in loadAllServices:');
                    console.error('Error:', e.message);
                    console.error('Stack:', e.stack);
                    allServices = [];
                    if (errorState) {
                        errorState.textContent = 'Failed to connect to booking system. Please try again later.';
                        errorState.style.display = 'block';
                    }
                }
            }
            
            // FIX #4: Properly load therapists from API
            async function loadTherapists() {
                try {
                    const response = await fetch(baseUrl + 'staff-appointments');
                    if (response.ok) {
                        const data = await response.json();
                        
                        // Handle both array and object responses
                        let staffList = Array.isArray(data) ? data : (data.Staff || data.data || []);
                        
                        // Filter for staff with session types (therapists)
                        therapists = staffList.filter(t => {
                            const hasSessionTypes = t.SessionTypes && t.SessionTypes.length > 0;
                            const hasName = t.Name || t.FirstName;
                            return hasName;
                        });
                        
                        // If no staff from API, extract unique therapists from service names
                        if (therapists.length === 0) {
                            therapists = extractTherapistsFromServices();
                        }
                        
                        console.log('Loaded ' + therapists.length + ' therapists');
                        renderTherapistOptions();
                    } else {
                        // Fallback: extract from services
                        therapists = extractTherapistsFromServices();
                        renderTherapistOptions();
                    }
                } catch (e) {
                    console.error('Failed to load therapists:', e);
                    therapists = extractTherapistsFromServices();
                    renderTherapistOptions();
                }
            }
            
            // Extract unique therapist names from slots
            function extractTherapistsFromServices() {
                const therapistSet = new Set();
                const therapistList = [];
                
                allServices.forEach(slot => {
                    const name = slot.TherapistName || '';
                    if (name && !therapistSet.has(name)) {
                        therapistSet.add(name);
                        therapistList.push({
                            Name: name,
                            FirstName: name.split(' ')[0],
                            LastName: name.split(' ').slice(1).join(' '),
                            Id: 'extracted-' + therapistSet.size,
                            ImageUrl: slot.TherapistPhoto || ''
                        });
                    }
                });
                
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
                    
                    // Filter slots by category
                    const categorySlots = allServices.filter(slot => {
                        const slotCategory = (slot.Category || '').toLowerCase().trim();
                        return slotCategory.includes(catLower) || catLower.includes(slotCategory);
                    });
                    
                    // Get unique services (by SessionTypeId) in this category
                    const serviceMap = {};
                    categorySlots.forEach(slot => {
                        const sid = slot.SessionTypeId;
                        if (!serviceMap[sid]) {
                            serviceMap[sid] = {
                                Id: sid,
                                Name: slot.Name,
                                Price: slot.Price
                            };
                        }
                    });
                    const categoryServices = Object.values(serviceMap);
                    
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
                        const serviceId = 'hw-service-' + service.Id;
                        const price = service.Price || 0;
                        
                        html += '<div class="hw-mbo-sub-service-item">';
                        html += '<input type="checkbox" class="hw-mbo-service-checkbox" value="' + service.Id + '" id="' + serviceId + '" ' + (selectedServices.has(String(service.Id)) ? 'checked' : '') + ' />';
                        html += '<label for="' + serviceId + '">' + escapeHtml(service.Name) + '</label>';
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
                        } else {
                            selectedCategories.delete(category);
                        }
                        updateTreatmentTriggerText();
                        debouncedLoadAvailability();
                    });
                });
                
                document.querySelectorAll('.hw-mbo-service-checkbox').forEach(cb => {
                    cb.addEventListener('change', (e) => {
                        const serviceId = e.target.value;
                        if (e.target.checked) {
                            selectedServices.add(serviceId);
                        } else {
                            selectedServices.delete(serviceId);
                        }
                        updateTreatmentTriggerText();
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
            
            function debouncedLoadAvailability() {
                clearTimeout(filterDebounceTimer);
                filterDebounceTimer = setTimeout(() => {
                    loadAvailability();
                }, 300);
            }
            
            async function loadAvailability() {
                if (loadingState) loadingState.style.display = 'block';
                if (errorState) errorState.style.display = 'none';
                if (scheduleContent) scheduleContent.innerHTML = '';
                
                try {
                    await loadServicesSchedule();
                } catch (e) {
                    console.error('Failed to load availability:', e);
                    if (errorState) {
                        errorState.textContent = 'Unable to load treatments. Please try again later.';
                        errorState.style.display = 'block';
                    }
                } finally {
                    if (loadingState) loadingState.style.display = 'none';
                }
            }
            
            
            // CATALOG FILTERING - Filter by category, therapist, service name
            async function loadServicesSchedule() {
                let services = [...allServices];
                
                console.log('=== FILTERING SERVICE CATALOG ===');
                console.log('Initial services: ' + services.length);
                
                // Filter by selected categories
                if (selectedCategories.size > 0) {
                    services = services.filter(service => {
                        const serviceCategory = service.Category || '';
                        for (const cat of selectedCategories) {
                            if (serviceCategory.toLowerCase().includes(cat.toLowerCase()) ||
                                cat.toLowerCase().includes(serviceCategory.toLowerCase())) {
                                return true;
                            }
                        }
                        return false;
                    });
                    console.log('After category filter: ' + services.length);
                }
                
                // Filter by selected specific services
                if (selectedServices.size > 0) {
                    const selectedIds = Array.from(selectedServices);
                    services = services.filter(service => selectedIds.includes(String(service.ServiceId)));
                    console.log('After service filter: ' + services.length);
                }
                
                // Filter by therapist name
                const selectedTherapistName = filterTherapist ? filterTherapist.value : '';
                if (selectedTherapistName) {
                    services = services.filter(service => {
                        const therapist = (service.TherapistName || '').toLowerCase();
                        return therapist.includes(selectedTherapistName.toLowerCase());
                    });
                    console.log('After therapist filter: ' + services.length);
                }
                
                console.log('Final filtered services: ' + services.length);
                
                renderSlotsSchedule(services);
            }
            
            // CATALOG RENDERING - Display services as cards, not time slots
            function renderSlotsSchedule(services) {
                console.log('=== RENDER SERVICE CATALOG ===');
                console.log('Data received:', services);
                console.log('Total services to render:', services.length);
                
                if (services.length > 0) {
                    console.log('Sample service structure:', services[0]);
                    console.log('Keys in first service:', Object.keys(services[0]));
                }
                
                if (!scheduleContent) {
                    console.error('ERROR: scheduleContent element not found!');
                    return;
                }
                
                if (services.length === 0) {
                    console.warn('No services to display - showing empty message');
                    scheduleContent.innerHTML = '<div class="hw-mbo-no-results"><h3>No Services Available</h3><p>Try adjusting your filters or contact us to book an appointment.</p></div>';
                    return;
                }
                
                // Group services by category
                const servicesByCategory = {};
                services.forEach(service => {
                    const category = service.Category || 'Other Services';
                    if (!servicesByCategory[category]) {
                        servicesByCategory[category] = [];
                    }
                    servicesByCategory[category].push(service);
                });
                
                let html = '';
                
                // Render each category
                for (const category in servicesByCategory) {
                    const categoryServices = servicesByCategory[category];
                    
                    html += '<div class="hw-mbo-category-section-display">';
                    html += '<h3 class="hw-mbo-category-title">' + escapeHtml(category) + '</h3>';
                    html += '<div class="hw-mbo-services-grid">';
                    
                    categoryServices.forEach(service => {
                        const serviceId = service.ServiceId || service.Id;
                        const serviceName = service.Name || 'Service';
                        const price = parseFloat(service.Price) || 0;
                        const duration = service.Duration || 60;
                        const therapistName = service.TherapistName || '';
                        const therapistPhoto = service.TherapistPhoto || therapistPhotos[therapistName] || '';
                        const description = service.Description || '';
                        
                        html += '<div class="hw-mbo-service-card">';
                        
                        // Service header
                        html += '<div class="hw-mbo-service-header">';
                        html += '<h4 class="hw-mbo-service-name">' + escapeHtml(serviceName) + '</h4>';
                        if (price > 0) {
                            html += '<span class="hw-mbo-service-price">£' + price.toFixed(0) + '</span>';
                        }
                        html += '</div>';
                        
                        // Service details
                        html += '<div class="hw-mbo-service-details">';
                        
                        if (duration > 0) {
                            html += '<div class="hw-mbo-service-duration"><strong>Duration:</strong> ' + duration + ' minutes</div>';
                        }
                        
                        if (therapistName) {
                            html += '<div class="hw-mbo-service-therapist">';
                            html += '<strong>Therapist:</strong> ';
                            if (therapistPhoto) {
                                html += '<span class="hw-mbo-therapist-inline">';
                                html += '<img src="' + escapeHtml(therapistPhoto) + '" alt="' + escapeHtml(therapistName) + '" class="hw-mbo-therapist-photo-small" />';
                                html += escapeHtml(therapistName);
                                html += '</span>';
                            } else {
                                html += escapeHtml(therapistName);
                            }
                            html += '</div>';
                        }
                        
                        if (description) {
                            html += '<div class="hw-mbo-service-description">' + escapeHtml(description.substring(0, 150)) + (description.length > 150 ? '...' : '') + '</div>';
                        }
                        
                        html += '</div>';
                        
                        // Book Now button
                        html += '<div class="hw-mbo-service-action">';
                        const bookingData = JSON.stringify({
                            serviceId: serviceId,
                            serviceName: serviceName,
                            therapistName: therapistName,
                            price: price,
                            duration: duration
                        });
                        html += '<button class="hw-mbo-book-btn hw-mbo-request-booking" data-booking="' + escapeHtml(bookingData) + '">Request Booking</button>';
                        html += '</div>';
                        
                        html += '</div>'; // End service card
                    });
                    
                    html += '</div>'; // End services grid
                    html += '</div>'; // End category section
                }
                
                scheduleContent.innerHTML = html;
                attachBookingListeners();
            }
            
            // Attach event listeners to Book Now buttons
            function attachBookingListeners() {
                document.querySelectorAll('.hw-mbo-book-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const bookingData = JSON.parse(e.target.dataset.booking || '{}');
                        handleBookNow(bookingData);
                    });
                });
            }
            
            // Handle Book Now button click - CATALOG MODEL (Request-based)
            function handleBookNow(booking) {
                if (!booking.serviceId) {
                    alert('Service information is incomplete. Please try again.');
                    return;
                }
                
                // Open modal to collect preferred date
                if (modalTitle) modalTitle.textContent = 'Request Booking';
                if (modalBody) {
                    modalBody.innerHTML = 
                        '<div class="hw-mbo-modal-details">' +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Service:</div><div class="hw-mbo-modal-value">' + escapeHtml(booking.serviceName) + '</div></div>' +
                        (booking.therapistName ? '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Therapist:</div><div class="hw-mbo-modal-value">' + escapeHtml(booking.therapistName) + '</div></div>' : '') +
                        (booking.duration ? '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Duration:</div><div class="hw-mbo-modal-value">' + booking.duration + ' minutes</div></div>' : '') +
                        '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Price:</div><div class="hw-mbo-modal-value hw-mbo-price">£' + parseFloat(booking.price).toFixed(2) + '</div></div>' +
                        '<div class="hw-mbo-modal-row">' +
                        '<div class="hw-mbo-modal-label">Preferred Date (Optional):</div>' +
                        '<div class="hw-mbo-modal-value"><input type="date" id="hw-preferred-date" min="' + formatDateLocal(new Date()) + '" /></div>' +
                        '</div>' +
                        '<div class="hw-mbo-modal-note">Note: This will redirect you to Mindbody to complete your booking. You can select a specific time there.</div>' +
                        '</div>' +
                        '<div class="hw-mbo-modal-action">' +
                        '<button class="hw-mbo-book-btn hw-mbo-confirm-booking-btn" id="hw-confirm-booking-catalog">Continue to Mindbody</button>' +
                        '</div>';
                }
                if (detailModal) detailModal.classList.add('open');
                
                // Attach handler
                const confirmBtn = document.getElementById('hw-confirm-booking-catalog');
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', () => {
                        const preferredDateInput = document.getElementById('hw-preferred-date');
                        const preferredDate = preferredDateInput ? preferredDateInput.value : '';
                        
                        // Generate Mindbody booking URL with ServiceId
                        let mbUrl = 'https://clients.mindbodyonline.com/ASP/home_schedule.asp' +
                            '?studioid=' + encodeURIComponent(defaultLocation || '') +
                            '&stype=-7' +  // Service booking
                            '&sTG=23' +    // Treatment category group
                            '&sView=day' +
                            '&sLoc=0';
                        
                        // Add service ID if available
                        if (booking.serviceId) {
                            mbUrl += '&sSer=' + encodeURIComponent(booking.serviceId);
                        }
                        
                        // Add preferred date if selected
                        if (preferredDate) {
                            const dateObj = new Date(preferredDate);
                            mbUrl += '&sDate=' + (dateObj.getMonth() + 1) + '/' + dateObj.getDate() + '/' + dateObj.getFullYear();
                        }
                        
                        console.log('Redirecting to Mindbody:', mbUrl);
                        console.log('Booking Details:', booking);
                        
                        // Open in new window
                        window.open(mbUrl, '_blank', 'noopener,noreferrer');
                        
                        // Close modal
                        if (detailModal) detailModal.classList.remove('open');
                    });
                }
            }
            
            function setupEventListeners() {
                if (!modalBody) return;
                
                const staffName = staff.Name || ((staff.FirstName || '') + ' ' + (staff.LastName || '')).trim();
                const imageUrl = staff.ImageUrl || staff.ImageURL || staff.Photo || '';
                const initials = staffName.split(' ').map(n => n.charAt(0).toUpperCase()).join('').substring(0, 2);
                
                let html = '<div class="hw-mbo-staff-modal">';
                
                // Staff image or initials avatar
                html += '<div class="hw-mbo-staff-image-section">';
                if (imageUrl) {
                    html += '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(staffName) + '" class="hw-mbo-staff-photo" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';" />';
                    html += '<div class="hw-mbo-staff-initials" style="display: none;">' + escapeHtml(initials) + '</div>';
                } else {
                    html += '<div class="hw-mbo-staff-initials">' + escapeHtml(initials) + '</div>';
                }
                html += '</div>';
                
                // Staff details
                html += '<div class="hw-mbo-modal-details">';
                
                if (staffName) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Name:</div><div class="hw-mbo-modal-value">' + escapeHtml(staffName) + '</div></div>';
                }
                
                html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Role:</div><div class="hw-mbo-modal-value">' + escapeHtml(staff.Role || 'Therapist') + '</div></div>';
                html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Location:</div><div class="hw-mbo-modal-value">' + escapeHtml(defaultLocation) + '</div></div>';
                
                // Email if available
                if (staff.Email) {
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Email:</div><div class="hw-mbo-modal-value"><a href="mailto:' + escapeHtml(staff.Email) + '" style="color: var(--hw-blue);">' + escapeHtml(staff.Email) + '</a></div></div>';
                }
                
                // Phone if available
                if (staff.MobilePhone || staff.HomePhone) {
                    const phone = staff.MobilePhone || staff.HomePhone;
                    html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Phone:</div><div class="hw-mbo-modal-value"><a href="tel:' + escapeHtml(phone) + '" style="color: var(--hw-blue);">' + escapeHtml(phone) + '</a></div></div>';
                }
                
                // Session types / Services offered
                if (staff.SessionTypes && staff.SessionTypes.length > 0) {
                    const services = staff.SessionTypes.map(s => s.Name || s).filter(Boolean);
                    if (services.length > 0) {
                        html += '<div class="hw-mbo-modal-row"><div class="hw-mbo-modal-label">Services:</div><div class="hw-mbo-modal-value">' + services.map(s => escapeHtml(s)).join(', ') + '</div></div>';
                    }
                }
                
                html += '</div>';
                
                // Bio section
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
            
            // FIX #3: Book Now integration with WooCommerce
            function handleBookNow(e) {
                const serviceData = JSON.parse(e.target.dataset.service.replace(/&#39;/g, "'"));
                const row = e.target.closest('tr');
                const timeSelect = row ? row.querySelector('.hw-mbo-time-select') : null;
                const selectedTime = timeSelect ? timeSelect.value : '10:00';
                
                // Get current date from the day section
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
                
                // Attach confirm booking handler
                const confirmBtn = document.getElementById('hw-confirm-booking');
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', handleConfirmBooking);
                }
            }
            
            // Handle confirm booking - add to WooCommerce cart
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
                        // Redirect to cart/checkout
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
                
                if (filterStartDate) filterStartDate.addEventListener('change', debouncedLoadAvailability);
                if (filterEndDate) filterEndDate.addEventListener('change', debouncedLoadAvailability);
                if (filterTime) filterTime.addEventListener('change', debouncedLoadAvailability);
                if (filterTherapist) filterTherapist.addEventListener('change', debouncedLoadAvailability);
                
                if (searchButton) searchButton.addEventListener('click', loadAvailability);
                
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
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }
    })();
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
