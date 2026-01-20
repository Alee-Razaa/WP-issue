<?php
/**
 * Mindbody REST API Endpoints
 * 
 * Provides REST API endpoints for Mindbody data
 * 
 * @package Home_Wellness
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register REST API routes
 */
function hw_mindbody_register_rest_routes() {
    $namespace = 'hw-mindbody/v1';
    
    // Test connection
    register_rest_route( $namespace, '/test-connection', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_test_connection',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
    
    // Service diagnostics (detailed breakdown)
    register_rest_route( $namespace, '/service-diagnostics', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_service_diagnostics',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
    
    // Debug staff data (see raw API response)
    register_rest_route( $namespace, '/debug-staff', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_debug_staff',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
    
    // Get services (all)
    register_rest_route( $namespace, '/services', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_services',
        'permission_callback' => '__return_true',
    ) );
    
    // Get filtered treatment services (only 8 target categories)
    register_rest_route( $namespace, '/treatment-services', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_treatment_services',
        'permission_callback' => '__return_true',
    ) );
    
    // Get staff/therapists
    register_rest_route( $namespace, '/staff-appointments', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_staff_appointments',
        'permission_callback' => '__return_true',
    ) );
    
    // Get staff details
    register_rest_route( $namespace, '/staff-details', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_staff_details',
        'permission_callback' => '__return_true',
    ) );
    
    // Get service details
    register_rest_route( $namespace, '/service-details', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_service_details',
        'permission_callback' => '__return_true',
    ) );
    
    // Get classes
    register_rest_route( $namespace, '/classes', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_classes',
        'permission_callback' => '__return_true',
    ) );
    
    // Get session types
    register_rest_route( $namespace, '/session-types', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_session_types',
        'permission_callback' => '__return_true',
    ) );
    
    // Get locations
    register_rest_route( $namespace, '/locations', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_locations',
        'permission_callback' => '__return_true',
    ) );
    
    // Get therapist availability - for testing/verification
    register_rest_route( $namespace, '/therapist-availability', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_get_therapist_availability',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
    
    // Comprehensive debug - test ALL availability endpoints
    register_rest_route( $namespace, '/debug-availability', array(
        'methods'             => 'GET',
        'callback'            => 'hw_mindbody_rest_debug_availability',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
}
add_action( 'rest_api_init', 'hw_mindbody_register_rest_routes' );

/**
 * REST: Comprehensive debug - test ALL Mindbody API endpoints for availability
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_debug_availability( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'API not configured' ), 200 );
    }
    
    $results = array(
        'timestamp' => gmdate( 'Y-m-d H:i:s' ),
        'tests'     => array(),
    );
    
    $start_date = gmdate( 'Y-m-d' );
    $end_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
    
    // TEST 1: Get Staff with Availabilities field
    $staff_test = array( 'name' => 'GET /staff/staff (check Availabilities field)', 'status' => 'pending' );
    $staff = $api->get_staff( array( 'Limit' => 10 ) );
    if ( is_wp_error( $staff ) ) {
        $staff_test['status'] = 'error';
        $staff_test['error'] = $staff->get_error_message();
    } else {
        $staff_test['status'] = 'success';
        $staff_test['count'] = count( $staff );
        $staff_test['sample'] = array();
        foreach ( array_slice( $staff, 0, 3 ) as $s ) {
            $staff_test['sample'][] = array(
                'Id'             => $s['Id'] ?? null,
                'Name'           => trim( ( $s['FirstName'] ?? '' ) . ' ' . ( $s['LastName'] ?? '' ) ),
                'ImageUrl'       => ! empty( $s['ImageUrl'] ) ? 'YES' : 'NO',
                'Availabilities' => $s['Availabilities'] ?? 'NOT PRESENT',
                'all_keys'       => array_keys( $s ),
            );
        }
    }
    $results['tests'][] = $staff_test;
    
    // TEST 2: Staff Appointments
    $appt_test = array( 'name' => 'GET /appointment/staffappointments', 'status' => 'pending' );
    $appt_response = $api->get_staff_appointments( array(
        'StartDate' => $start_date,
        'EndDate'   => $end_date,
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $appt_response ) ) {
        $appt_test['status'] = 'error';
        $appt_test['error'] = $appt_response->get_error_message();
    } else {
        $appt_test['status'] = 'success';
        $appt_test['count'] = count( $appt_response );
        if ( count( $appt_response ) > 0 ) {
            $appt_test['sample'] = array_slice( $appt_response, 0, 2 );
        }
    }
    $results['tests'][] = $appt_test;
    
    // TEST 3: Session Types - find BOOKABLE APPOINTMENT types specifically
    $session_test = array( 'name' => 'GET /appointment/sessiontypes', 'status' => 'pending' );
    $session_types = $api->get_session_types( array( 'Limit' => 500 ) );
    $session_type_ids = array();
    $appointment_type_ids = array();
    $bookable_appointment_ids = array();
    if ( is_wp_error( $session_types ) ) {
        $session_test['status'] = 'error';
        $session_test['error'] = $session_types->get_error_message();
    } else {
        $session_test['status'] = 'success';
        $session_test['count'] = count( $session_types );
        
        // Analyze session types - look for BOOKABLE appointment types
        $types_breakdown = array();
        $bookable_samples = array();
        foreach ( $session_types as $st ) {
            $id = $st['Id'] ?? null;
            $type = $st['Type'] ?? $st['ScheduleType'] ?? 'Unknown';
            $name = $st['Name'] ?? '';
            $online_bookable = $st['OnlineBookable'] ?? $st['AllowOnlineBooking'] ?? false;
            $default_length = $st['DefaultTimeLength'] ?? 0;
            
            if ( $id ) {
                $session_type_ids[] = $id;
                
                // Check if this is an appointment type (Type = "Appointment", not "Series" or "Class")
                $type_lower = strtolower( $type );
                $is_appointment = ( $type_lower === 'appointment' || 
                                   strpos( $type_lower, 'appointment' ) !== false );
                
                if ( $is_appointment ) {
                    $appointment_type_ids[] = $id;
                }
                
                // Check if bookable online AND is appointment type
                if ( $online_bookable && $is_appointment ) {
                    $bookable_appointment_ids[] = $id;
                    if ( count( $bookable_samples ) < 5 ) {
                        $bookable_samples[] = array(
                            'Id' => $id,
                            'Name' => $name,
                            'Type' => $type,
                            'OnlineBookable' => $online_bookable,
                            'DefaultTimeLength' => $default_length,
                        );
                    }
                }
                
                // Also collect ANY bookable type (even if not strictly "Appointment")
                if ( $online_bookable && $default_length > 0 ) {
                    if ( ! in_array( $id, $bookable_appointment_ids ) ) {
                        $bookable_appointment_ids[] = $id;
                    }
                }
            }
            
            // Track type breakdown
            if ( ! isset( $types_breakdown[ $type ] ) ) {
                $types_breakdown[ $type ] = 0;
            }
            $types_breakdown[ $type ]++;
        }
        
        $session_test['types_breakdown'] = $types_breakdown;
        $session_test['bookable_appointment_ids'] = array_slice( $bookable_appointment_ids, 0, 20 );
        $session_test['bookable_count'] = count( $bookable_appointment_ids );
        $session_test['appointment_type_ids'] = array_slice( $appointment_type_ids, 0, 20 );
        $session_test['bookable_samples'] = $bookable_samples;
        $session_test['sample'] = array_slice( $session_types, 0, 3 );
    }
    $results['tests'][] = $session_test;
    
    // Priority: bookable appointments > any appointments > all session types
    $ids_to_use = array();
    if ( ! empty( $bookable_appointment_ids ) ) {
        $ids_to_use = $bookable_appointment_ids;
    } elseif ( ! empty( $appointment_type_ids ) ) {
        $ids_to_use = $appointment_type_ids;
    } else {
        $ids_to_use = $session_type_ids;
    }
    
    // TEST 4: Bookable Items (with session type IDs)
    $bookable_test = array( 'name' => 'GET /appointment/bookableitems', 'status' => 'pending' );
    if ( ! empty( $ids_to_use ) ) {
        // Try with first 5 session type IDs (preferring appointment types)
        $bookable_params = array(
            'SessionTypeIds' => array_slice( $ids_to_use, 0, 5 ),
            'StartDate'      => $start_date,
            'EndDate'        => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
            'Limit'          => 100,
        );
        $bookable_test['params'] = $bookable_params;
        $bookable_response = $api->get_bookable_items( $bookable_params );
        if ( is_wp_error( $bookable_response ) ) {
            $bookable_test['status'] = 'error';
            $bookable_test['error'] = $bookable_response->get_error_message();
            
            // Try with just ONE session type ID
            $bookable_test['retry_with_single'] = true;
            $single_params = array(
                'SessionTypeIds' => array( $ids_to_use[0] ),
                'StartDate'      => $start_date,
                'EndDate'        => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
            );
            $bookable_test['single_params'] = $single_params;
            $single_response = $api->get_bookable_items( $single_params );
            if ( is_wp_error( $single_response ) ) {
                $bookable_test['single_error'] = $single_response->get_error_message();
            } else {
                $bookable_test['single_count'] = count( $single_response );
                $bookable_test['single_sample'] = array_slice( $single_response, 0, 2 );
            }
        } else {
            $bookable_test['status'] = 'success';
            $bookable_test['count'] = count( $bookable_response );
            if ( count( $bookable_response ) > 0 ) {
                $bookable_test['sample'] = array_slice( $bookable_response, 0, 2 );
            }
        }
    } else {
        $bookable_test['status'] = 'skipped';
        $bookable_test['reason'] = 'No session type IDs available';
    }
    $bookable_test['using_appointment_types'] = ! empty( $appointment_type_ids );
    $bookable_test['ids_used'] = array_slice( $ids_to_use, 0, 5 );
    $results['tests'][] = $bookable_test;
    
    // TEST 5: Active Session Times
    $active_test = array( 'name' => 'GET /appointment/activesessiontimes', 'status' => 'pending' );
    $active_response = $api->get_active_session_times( array(
        'StartDate'  => $start_date,
        'EndDate'    => $end_date,
        'Limit'      => 100,
    ) );
    if ( is_wp_error( $active_response ) ) {
        $active_test['status'] = 'error';
        $active_test['error'] = $active_response->get_error_message();
    } else {
        $active_test['status'] = 'success';
        $active_test['count'] = count( $active_response );
        if ( count( $active_response ) > 0 ) {
            $active_test['sample'] = array_slice( $active_response, 0, 2 );
        }
    }
    $results['tests'][] = $active_test;
    
    // TEST 6: Appointments (general - using raw request)
    $gen_appt_test = array( 'name' => 'GET /appointment/appointments', 'status' => 'pending' );
    $gen_appt_response = $api->make_request( '/appointment/appointments', 'GET', array(
        'StartDate' => $start_date,
        'EndDate'   => $end_date,
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $gen_appt_response ) ) {
        $gen_appt_test['status'] = 'error';
        $gen_appt_test['error'] = $gen_appt_response->get_error_message();
    } else {
        $gen_appt_test['status'] = 'success';
        $gen_appt_test['raw_keys'] = array_keys( $gen_appt_response );
        $appointments = $gen_appt_response['Appointments'] ?? array();
        $gen_appt_test['count'] = count( $appointments );
        if ( count( $appointments ) > 0 ) {
            $gen_appt_test['sample'] = array_slice( $appointments, 0, 2 );
        }
    }
    $results['tests'][] = $gen_appt_test;
    
    // TEST 7: Schedule Items
    $schedule_test = array( 'name' => 'GET /appointment/scheduleitems', 'status' => 'pending' );
    $schedule_response = $api->make_request( '/appointment/scheduleitems', 'GET', array(
        'StartDate' => $start_date,
        'EndDate'   => $end_date,
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $schedule_response ) ) {
        $schedule_test['status'] = 'error';
        $schedule_test['error'] = $schedule_response->get_error_message();
    } else {
        $schedule_test['status'] = 'success';
        $schedule_test['raw_keys'] = array_keys( $schedule_response );
        $items = $schedule_response['StaffMembers'] ?? $schedule_response['ScheduleItems'] ?? array();
        $schedule_test['count'] = count( $items );
        if ( count( $items ) > 0 ) {
            $schedule_test['sample'] = array_slice( $items, 0, 2 );
        }
    }
    $results['tests'][] = $schedule_test;
    
    // TEST 8: Site Session Types (appointment types)
    $site_session_test = array( 'name' => 'GET /site/sessiontypes', 'status' => 'pending' );
    $site_session_response = $api->make_request( '/site/sessiontypes', 'GET', array(
        'Limit' => 100,
    ) );
    if ( is_wp_error( $site_session_response ) ) {
        $site_session_test['status'] = 'error';
        $site_session_test['error'] = $site_session_response->get_error_message();
    } else {
        $site_session_test['status'] = 'success';
        $site_session_test['raw_keys'] = array_keys( $site_session_response );
        $items = $site_session_response['SessionTypes'] ?? $site_session_response;
        $site_session_test['count'] = is_array( $items ) ? count( $items ) : 0;
        $site_session_test['sample'] = is_array( $items ) ? array_slice( $items, 0, 5 ) : $site_session_response;
    }
    $results['tests'][] = $site_session_test;
    
    // TEST 9: Appointment Types
    $appt_types_test = array( 'name' => 'GET /appointment/appointmenttypes', 'status' => 'pending' );
    $appt_types_response = $api->make_request( '/appointment/appointmenttypes', 'GET', array(
        'Limit' => 100,
    ) );
    if ( is_wp_error( $appt_types_response ) ) {
        $appt_types_test['status'] = 'error';
        $appt_types_test['error'] = $appt_types_response->get_error_message();
    } else {
        $appt_types_test['status'] = 'success';
        $appt_types_test['raw_keys'] = array_keys( $appt_types_response );
        $appt_types_test['raw_response'] = $appt_types_response;
    }
    $results['tests'][] = $appt_types_test;
    
    // TEST 10: Staff Schedule
    $staff_schedule_test = array( 'name' => 'GET /staff/staffschedule', 'status' => 'pending' );
    $staff_schedule_response = $api->make_request( '/staff/staffschedule', 'GET', array(
        'StartDate' => $start_date,
        'EndDate'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $staff_schedule_response ) ) {
        $staff_schedule_test['status'] = 'error';
        $staff_schedule_test['error'] = $staff_schedule_response->get_error_message();
    } else {
        $staff_schedule_test['status'] = 'success';
        $staff_schedule_test['raw_keys'] = array_keys( $staff_schedule_response );
        $staff_schedule_test['raw_response'] = $staff_schedule_response;
    }
    $results['tests'][] = $staff_schedule_test;
    
    // TEST 11: Staff Centric Schedule
    $staff_centric_test = array( 'name' => 'GET /staff/staffcentricschedule', 'status' => 'pending' );
    $staff_centric_response = $api->make_request( '/staff/staffcentricschedule', 'GET', array(
        'StartDate' => $start_date,
        'EndDate'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $staff_centric_response ) ) {
        $staff_centric_test['status'] = 'error';
        $staff_centric_test['error'] = $staff_centric_response->get_error_message();
    } else {
        $staff_centric_test['status'] = 'success';
        $staff_centric_test['raw_keys'] = array_keys( $staff_centric_response );
        $staff_centric_test['raw_response'] = $staff_centric_response;
    }
    $results['tests'][] = $staff_centric_test;
    
    // TEST 12: Availability direct endpoint
    $avail_test = array( 'name' => 'GET /appointment/availability', 'status' => 'pending' );
    $avail_response = $api->make_request( '/appointment/availability', 'GET', array(
        'StartDate' => $start_date,
        'EndDate'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
        'Limit'     => 100,
    ) );
    if ( is_wp_error( $avail_response ) ) {
        $avail_test['status'] = 'error';
        $avail_test['error'] = $avail_response->get_error_message();
    } else {
        $avail_test['status'] = 'success';
        $avail_test['raw_keys'] = array_keys( $avail_response );
        $avail_test['raw_response'] = $avail_response;
    }
    $results['tests'][] = $avail_test;
    
    // Summary
    $results['summary'] = array(
        'date_range'       => $start_date . ' to ' . $end_date,
        'working_endpoints' => array(),
        'failed_endpoints'  => array(),
        'endpoints_with_data' => array(),
    );
    
    foreach ( $results['tests'] as $test ) {
        if ( $test['status'] === 'success' ) {
            if ( ( $test['count'] ?? 0 ) > 0 ) {
                $results['summary']['endpoints_with_data'][] = $test['name'] . ' (' . $test['count'] . ' items)';
            }
            $results['summary']['working_endpoints'][] = $test['name'];
        } elseif ( $test['status'] === 'error' ) {
            $results['summary']['failed_endpoints'][] = $test['name'] . ': ' . ( $test['error'] ?? 'unknown' );
        }
    }
    
    return new WP_REST_Response( $results, 200 );
}

/**
 * REST: Get comprehensive service diagnostics
 * 
 * Returns detailed breakdown of all services from Mindbody
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_service_diagnostics( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'API not configured',
        ), 200 );
    }
    
    // Target treatment categories (the 8 categories user wants)
    $target_categories = array(
        'Acupuncture & Eastern Med',
        'Energy & Healing Therapies',
        'Face & Skin Treatments',
        'Fertility, Pre & Postnatal',
        'Massage & Bodywork',
        'Mind & Emotional Health',
        'Natural Medicine/ Nutrition',
        'Osteopathy & Physiotherapy',
    );
    
    // Get all services
    $services = $api->get_services( array( 'Limit' => 1000 ) );
    
    if ( is_wp_error( $services ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'Failed to fetch services: ' . $services->get_error_message(),
        ), 200 );
    }
    
    // Get staff with photos
    $staff = $api->get_staff( array( 'Limit' => 1000 ) );
    $staff_with_photos = array();
    $staff_without_photos = array();
    
    if ( ! is_wp_error( $staff ) ) {
        foreach ( $staff as $s ) {
            $name = trim( ( $s['FirstName'] ?? '' ) . ' ' . ( $s['LastName'] ?? '' ) );
            $has_photo = ! empty( $s['ImageUrl'] ) || ! empty( $s['ImageURL'] ) || ! empty( $s['Photo'] );
            if ( $has_photo ) {
                $staff_with_photos[ $name ] = $s['ImageUrl'] ?? $s['ImageURL'] ?? $s['Photo'] ?? '';
            } else {
                $staff_without_photos[] = $name;
            }
        }
    }
    
    // Analyze services
    $total_services = count( $services );
    $all_categories = array();
    $target_category_services = array();
    $therapists_in_target = array();
    $filtered_service_count = 0;
    $filtered_services = array();
    
    foreach ( $target_categories as $cat ) {
        $target_category_services[ $cat ] = array(
            'count'    => 0,
            'services' => array(),
        );
    }
    
    foreach ( $services as $service ) {
        $name = $service['Name'] ?? 'Unknown';
        $price = floatval( $service['Price'] ?? $service['OnlinePrice'] ?? 0 );
        
        // Get category
        $category = 'Uncategorized';
        if ( isset( $service['ServiceCategory']['Name'] ) ) {
            $category = $service['ServiceCategory']['Name'];
        } elseif ( isset( $service['Program'] ) ) {
            $category = $service['Program'];
        }
        
        // Track all categories
        if ( ! isset( $all_categories[ $category ] ) ) {
            $all_categories[ $category ] = 0;
        }
        $all_categories[ $category ]++;
        
        // Check if this service belongs to target categories
        $matched_category = null;
        foreach ( $target_categories as $target_cat ) {
            // Flexible matching - check if category contains target or vice versa
            if ( stripos( $category, $target_cat ) !== false || 
                 stripos( $target_cat, $category ) !== false ||
                 strtolower( trim( $category ) ) === strtolower( trim( $target_cat ) ) ) {
                $matched_category = $target_cat;
                break;
            }
        }
        
        if ( $matched_category ) {
            $filtered_service_count++;
            $target_category_services[ $matched_category ]['count']++;
            
            // Extract therapist
            $therapist_name = 'Unknown';
            if ( preg_match( '/\s-\s([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*(?:-|$)/', $name, $matches ) ) {
                $therapist_name = trim( $matches[1] );
                if ( preg_match( '/^\d+\s*min$/i', $therapist_name ) ) {
                    $therapist_name = 'Unknown';
                }
            }
            
            if ( $therapist_name !== 'Unknown' ) {
                if ( ! isset( $therapists_in_target[ $therapist_name ] ) ) {
                    $therapists_in_target[ $therapist_name ] = array(
                        'services'  => 0,
                        'has_photo' => isset( $staff_with_photos[ $therapist_name ] ),
                        'photo_url' => $staff_with_photos[ $therapist_name ] ?? '',
                    );
                }
                $therapists_in_target[ $therapist_name ]['services']++;
            }
            
            // Add to filtered services list (limit 100)
            if ( count( $filtered_services ) < 100 ) {
                $filtered_services[] = array(
                    'id'        => $service['Id'] ?? null,
                    'name'      => $name,
                    'price'     => $price,
                    'category'  => $matched_category,
                    'therapist' => $therapist_name,
                );
            }
            
            // Add sample to category
            if ( count( $target_category_services[ $matched_category ]['services'] ) < 5 ) {
                $target_category_services[ $matched_category ]['services'][] = array(
                    'name'  => $name,
                    'price' => $price,
                );
            }
        }
    }
    
    // Sort
    arsort( $all_categories );
    arsort( $therapists_in_target );
    
    // Staff photo summary
    $therapists_with_photos = 0;
    $therapists_without_photos = 0;
    foreach ( $therapists_in_target as $t => $info ) {
        if ( $info['has_photo'] ) {
            $therapists_with_photos++;
        } else {
            $therapists_without_photos++;
        }
    }
    
    return new WP_REST_Response( array(
        'success'                    => true,
        'summary'                    => array(
            'total_all_services'         => $total_services,
            'total_target_categories'    => $filtered_service_count,
            'unique_therapists_target'   => count( $therapists_in_target ),
            'therapists_with_photos'     => $therapists_with_photos,
            'therapists_without_photos'  => $therapists_without_photos,
            'total_staff_in_mindbody'    => count( $staff_with_photos ) + count( $staff_without_photos ),
            'staff_with_photos'          => count( $staff_with_photos ),
        ),
        'target_categories'          => $target_categories,
        'target_category_breakdown'  => $target_category_services,
        'all_categories_in_mindbody' => $all_categories,
        'therapists_in_target'       => $therapists_in_target,
        'filtered_services_sample'   => $filtered_services,
    ), 200 );
}

/**
 * REST: Debug staff data - see raw API response
 * 
 * This helps identify why photos may not be showing
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_debug_staff( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'API not configured',
        ), 200 );
    }
    
    $credentials = $api->get_credentials();
    
    // Make direct API call to see raw response
    $url = 'https://api.mindbodyonline.com/public/v6/staff/staff';
    
    $headers = array(
        'Content-Type' => 'application/json',
        'Api-Key'      => $credentials['api_key'],
        'SiteId'       => $credentials['site_id'],
    );
    
    $response = wp_remote_get( $url . '?Limit=100', array(
        'headers' => $headers,
        'timeout' => 30,
    ) );
    
    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'API Error: ' . $response->get_error_message(),
        ), 200 );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    // Analyze fields available - API uses 'StaffMembers' not 'Staff'
    $staff_list = array();
    if ( isset( $data['StaffMembers'] ) ) {
        $staff_list = $data['StaffMembers'];
    } elseif ( isset( $data['Staff'] ) ) {
        $staff_list = $data['Staff'];
    }
    $total_staff = count( $staff_list );
    
    $field_analysis = array();
    $sample_staff = array();
    $photo_field_candidates = array( 'ImageUrl', 'ImageURL', 'Photo', 'PhotoUrl', 'ProfilePicture', 'Image', 'StaffImage' );
    $found_photo_fields = array();
    
    foreach ( $staff_list as $index => $staff ) {
        // Check all possible photo field names
        foreach ( $photo_field_candidates as $field ) {
            if ( isset( $staff[ $field ] ) && ! empty( $staff[ $field ] ) ) {
                if ( ! isset( $found_photo_fields[ $field ] ) ) {
                    $found_photo_fields[ $field ] = 0;
                }
                $found_photo_fields[ $field ]++;
            }
        }
        
        // Collect all field names from first staff member
        if ( $index === 0 ) {
            $field_analysis = array_keys( $staff );
        }
        
        // Get sample data for first 5 staff with all their fields
        if ( $index < 5 ) {
            $sample_staff[] = $staff;
        }
    }
    
    // Look for Amanda Tizard specifically
    $amanda_data = null;
    foreach ( $staff_list as $staff ) {
        $name = trim( ( $staff['FirstName'] ?? '' ) . ' ' . ( $staff['LastName'] ?? '' ) );
        if ( stripos( $name, 'Amanda' ) !== false || stripos( $name, 'Tizard' ) !== false ) {
            $amanda_data = $staff;
            break;
        }
    }
    
    return new WP_REST_Response( array(
        'success'               => true,
        'total_staff'           => $total_staff,
        'available_fields'      => $field_analysis,
        'photo_field_candidates' => $photo_field_candidates,
        'found_photo_fields'    => $found_photo_fields,
        'amanda_tizard_data'    => $amanda_data,
        'sample_staff_raw'      => $sample_staff,
        'raw_response_keys'     => array_keys( $data ),
    ), 200 );
}

/**
 * REST: Test Mindbody API connection
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_test_connection( $request ) {
    $api = hw_mindbody_api();
    
    $credentials = $api->get_credentials();
    
    // Check if credentials are configured
    if ( empty( $credentials['api_key'] ) || empty( $credentials['site_id'] ) ) {
        return new WP_REST_Response( array(
            'success'     => false,
            'message'     => 'API credentials are not configured. Please enter your API Key and Site ID.',
            'credentials' => array(
                'api_key_set'  => ! empty( $credentials['api_key'] ),
                'site_id_set'  => ! empty( $credentials['site_id'] ),
            ),
        ), 200 );
    }
    
    // Try to get locations (simple endpoint to verify connection)
    $locations = $api->get_locations();
    
    if ( is_wp_error( $locations ) ) {
        return new WP_REST_Response( array(
            'success'     => false,
            'message'     => 'API connection failed: ' . $locations->get_error_message(),
            'error_code'  => $locations->get_error_code(),
            'credentials' => array(
                'api_key_set'  => ! empty( $credentials['api_key'] ),
                'site_id'      => $credentials['site_id'],
            ),
        ), 200 );
    }
    
    // Try to get session types for treatments
    $session_types = $api->get_session_types();
    $session_types_count = is_wp_error( $session_types ) ? 0 : count( $session_types );
    
    // Try to get services
    $services = $api->get_services();
    $services_count = is_wp_error( $services ) ? 0 : count( $services );
    
    // Try to get staff
    $staff = $api->get_staff();
    $staff_count = is_wp_error( $staff ) ? 0 : count( $staff );
    
    return new WP_REST_Response( array(
        'success'       => true,
        'message'       => 'Successfully connected to Mindbody API!',
        'site_id'       => $credentials['site_id'],
        'data'          => array(
            'locations'     => count( $locations ),
            'session_types' => $session_types_count,
            'services'      => $services_count,
            'staff'         => $staff_count,
        ),
        'location_names' => array_map( function( $loc ) {
            return $loc['Name'] ?? 'Unknown';
        }, $locations ),
    ), 200 );
}

/**
 * REST: Get treatment services with live bookable time slots
 * 
 * NEW ARCHITECTURE:
 * - Calls Mindbody GET /appointment/bookableitems for LIVE availability
 * - Returns Date, Time, TherapistName, StartDateTime for each slot
 * - Supports dynamic date range filtering from frontend
 * - Filters to 8 target treatment categories only
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_treatment_services( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    // The 8 target treatment categories
    $target_categories = array(
        'Acupuncture & Eastern Med',
        'Energy & Healing Therapies',
        'Face & Skin Treatments',
        'Fertility, Pre & Postnatal',
        'Massage & Bodywork',
        'Mind & Emotional Health',
        'Natural Medicine/ Nutrition',
        'Osteopathy & Physiotherapy',
    );
    
    // Get date range from request (DYNAMIC FETCHING)
    $start_date = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d' );
    $end_date = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '+7 days' ) );
    
    // Validate and format dates (ISO format YYYY-MM-DD)
    $start_date = gmdate( 'Y-m-d', strtotime( $start_date ) );
    $end_date = gmdate( 'Y-m-d', strtotime( $end_date ) );
    
    // STEP 1: Get session types for appointments
    $session_types = $api->get_session_types( array( 'Limit' => 500 ) );
    $session_type_ids = array();
    $session_type_map = array(); // Map ID to session type data
    
    if ( ! is_wp_error( $session_types ) && is_array( $session_types ) ) {
        foreach ( $session_types as $st ) {
            $id = $st['Id'] ?? null;
            $type = strtolower( $st['Type'] ?? '' );
            $name = $st['Name'] ?? '';
            
            if ( $id && ( $type === 'appointment' || strpos( $type, 'appointment' ) !== false ) ) {
                $session_type_ids[] = $id;
                $session_type_map[ $id ] = $st;
            }
        }
    }
    
    // STEP 2: Get all services to extract category mappings
    $all_services = $api->get_services( array( 'Limit' => 1000 ) );
    $service_category_map = array(); // SessionTypeId => Category
    
    if ( ! is_wp_error( $all_services ) && is_array( $all_services ) ) {
        foreach ( $all_services as $service ) {
            $session_id = $service['Id'] ?? null;
            $category = $service['ServiceCategory']['Name'] ?? $service['Program'] ?? '';
            
            if ( $session_id && $category ) {
                // Check if category matches our target categories
                foreach ( $target_categories as $target ) {
                    if ( stripos( $category, $target ) !== false || stripos( $target, $category ) !== false ) {
                        $service_category_map[ $session_id ] = $target;
                        break;
                    }
                }
            }
        }
    }
    
    // STEP 3: Get staff with photos
    $staff = $api->get_staff( array( 'Limit' => 500 ) );
    $staff_map = array(); // StaffId => Staff data
    
    if ( ! is_wp_error( $staff ) && is_array( $staff ) ) {
        foreach ( $staff as $s ) {
            $staff_id = $s['Id'] ?? null;
            if ( $staff_id ) {
                $staff_map[ $staff_id ] = array(
                    'Name' => trim( ( $s['FirstName'] ?? '' ) . ' ' . ( $s['LastName'] ?? '' ) ),
                    'ImageUrl' => $s['ImageUrl'] ?? '',
                    'FirstName' => $s['FirstName'] ?? '',
                    'LastName' => $s['LastName'] ?? '',
                );
            }
        }
    }
    
    // STEP 4: Get bookable items - THE LIVE DATA!
    $bookable_slots = array();
    
    if ( ! empty( $session_type_ids ) ) {
        $bookable_params = array(
            'SessionTypeIds' => array_slice( $session_type_ids, 0, 10 ), // Limit to 10 to avoid API timeout
            'StartDate' => $start_date,
            'EndDate' => $end_date,
            'Limit' => 1000,
        );
        
        $bookable_response = $api->get_bookable_items( $bookable_params );
        
        if ( ! is_wp_error( $bookable_response ) && is_array( $bookable_response ) ) {
            foreach ( $bookable_response as $item ) {
                $session_type_id = $item['SessionType']['Id'] ?? null;
                $staff_id = $item['Staff']['Id'] ?? null;
                $start_datetime = $item['StartDateTime'] ?? null;
                
                if ( ! $session_type_id || ! $start_datetime ) {
                    continue;
                }
                
                // Check if this session type is in our target categories
                $category = $service_category_map[ $session_type_id ] ?? null;
                if ( ! $category ) {
                    continue;
                }
                
                // Extract date and time
                $datetime_obj = new DateTime( $start_datetime );
                $date = $datetime_obj->format( 'Y-m-d' );
                $time = $datetime_obj->format( 'H:i' );
                
                // Get staff info
                $staff_info = $staff_map[ $staff_id ] ?? null;
                $therapist_name = $staff_info ? $staff_info['Name'] : 'Unknown';
                $therapist_photo = $staff_info ? $staff_info['ImageUrl'] : '';
                
                // Get session type info
                $session_info = $session_type_map[ $session_type_id ] ?? array();
                $service_name = $session_info['Name'] ?? 'Appointment';
                $duration = intval( $session_info['DefaultTimeLength'] ?? 60 );
                
                // Build slot object
                $bookable_slots[] = array(
                    'SessionTypeId' => $session_type_id,
                    'StaffId' => $staff_id,
                    'StartDateTime' => $start_datetime,
                    'Date' => $date,
                    'Time' => $time,
                    'TherapistName' => $therapist_name,
                    'TherapistPhoto' => $therapist_photo,
                    'Category' => $category,
                    'Name' => $service_name,
                    'Duration' => $duration,
                    'Price' => floatval( $item['Price'] ?? 0 ),
                );
            }
        }
    }
    
    return new WP_REST_Response( array(
        'success' => true,
        'slots' => $bookable_slots,
        'total_count' => count( $bookable_slots ),
        'date_range' => array(
            'start' => $start_date,
            'end' => $end_date,
        ),
        'target_categories' => $target_categories,
        'data_source' => 'bookable_items',
    ), 200 );
}

/**
 * REST: Get services
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_services( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $services = $api->get_services();
    
    if ( is_wp_error( $services ) ) {
        return $services;
    }
    
    return new WP_REST_Response( $services, 200 );
}

/**
 * REST: Get staff appointments (therapists)
 * 
 * Enhanced to properly fetch all therapists from Mindbody API
 * and extract therapists from service names as fallback
 * 
 * @since 1.1.0
 * @updated 1.2.0 - Added service name extraction fallback
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_staff_appointments( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $staff = array();
    
    // Method 1: Get appointment instructors
    $instructors = $api->get_appointment_instructors();
    if ( ! is_wp_error( $instructors ) && is_array( $instructors ) ) {
        $staff = $instructors;
    }
    
    // Method 2: Fallback to regular staff
    if ( empty( $staff ) ) {
        $all_staff = $api->get_staff();
        if ( ! is_wp_error( $all_staff ) && is_array( $all_staff ) ) {
            $staff = $all_staff;
        }
    }
    
    // Method 3: Extract therapists from service names
    if ( empty( $staff ) ) {
        $services = $api->get_services();
        if ( ! is_wp_error( $services ) && is_array( $services ) ) {
            $therapist_names = array();
            
            foreach ( $services as $service ) {
                $name = $service['Name'] ?? '';
                // Pattern: "Treatment Name - Therapist Name - Duration"
                if ( preg_match( '/\s-\s([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*(?:-|$)/', $name, $matches ) ) {
                    $therapist_name = trim( $matches[1] );
                    // Exclude duration patterns
                    if ( ! preg_match( '/^\d+\s*min$/i', $therapist_name ) ) {
                        $therapist_names[ $therapist_name ] = true;
                    }
                }
            }
            
            foreach ( array_keys( $therapist_names ) as $therapist_name ) {
                $name_parts = explode( ' ', $therapist_name );
                $staff[] = array(
                    'Id'           => 'extracted-' . sanitize_title( $therapist_name ),
                    'Name'         => $therapist_name,
                    'FirstName'    => $name_parts[0] ?? '',
                    'LastName'     => implode( ' ', array_slice( $name_parts, 1 ) ),
                    'Role'         => 'Therapist',
                    'SessionTypes' => array(),
                );
            }
            
            // Sort by name
            usort( $staff, function( $a, $b ) {
                return strcmp( $a['Name'], $b['Name'] );
            } );
        }
    }
    
    // Add session types info if available
    if ( ! empty( $staff ) ) {
        $session_types = $api->get_session_types();
        if ( ! is_wp_error( $session_types ) && is_array( $session_types ) ) {
            foreach ( $staff as &$member ) {
                if ( empty( $member['SessionTypes'] ) ) {
                    $member['SessionTypes'] = $session_types;
                }
            }
        }
    }
    
    return new WP_REST_Response( $staff, 200 );
}

/**
 * REST: Get staff details
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_staff_details( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $staff_id   = $request->get_param( 'staff_id' );
    $staff_name = $request->get_param( 'staff_name' );
    
    if ( empty( $staff_id ) && empty( $staff_name ) ) {
        return new WP_Error( 'missing_params', 'Please provide staff_id or staff_name.', array( 'status' => 400 ) );
    }
    
    $staff = $api->get_staff_details( $staff_id, $staff_name );
    
    if ( is_wp_error( $staff ) ) {
        return $staff;
    }
    
    if ( empty( $staff ) ) {
        return new WP_Error( 'not_found', 'Staff member not found.', array( 'status' => 404 ) );
    }
    
    return new WP_REST_Response( $staff, 200 );
}

/**
 * REST: Get service details
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_service_details( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $service_id = $request->get_param( 'service_id' );
    
    if ( empty( $service_id ) ) {
        return new WP_Error( 'missing_params', 'Please provide service_id.', array( 'status' => 400 ) );
    }
    
    $services = $api->get_services();
    
    if ( is_wp_error( $services ) ) {
        return $services;
    }
    
    // Find service by ID
    foreach ( $services as $service ) {
        $id = $service['Id'] ?? $service['ID'] ?? null;
        if ( strval( $id ) === strval( $service_id ) ) {
            return new WP_REST_Response( $service, 200 );
        }
    }
    
    return new WP_Error( 'not_found', 'Service not found.', array( 'status' => 404 ) );
}

/**
 * REST: Get classes
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_classes( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $start_date = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d' );
    $end_date   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '+7 days' ) );
    
    $classes = $api->get_classes( array(
        'StartDateTime' => $start_date . 'T00:00:00',
        'EndDateTime'   => $end_date . 'T23:59:59',
    ) );
    
    if ( is_wp_error( $classes ) ) {
        return $classes;
    }
    
    return new WP_REST_Response( $classes, 200 );
}

/**
 * REST: Get session types
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_session_types( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $session_types = $api->get_session_types();
    
    if ( is_wp_error( $session_types ) ) {
        return $session_types;
    }
    
    return new WP_REST_Response( $session_types, 200 );
}

/**
 * REST: Get locations
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_locations( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_Error( 'not_configured', 'Mindbody API is not configured.', array( 'status' => 500 ) );
    }
    
    $locations = $api->get_locations();
    
    if ( is_wp_error( $locations ) ) {
        return $locations;
    }
    
    return new WP_REST_Response( $locations, 200 );
}

/**
 * REST: Get therapist availability DIRECTLY from Mindbody API
 * 
 * Uses GET /appointment/staffappointments to find scheduled appointments,
 * then extracts which days of the week each therapist works.
 * 
 * @see https://developers.mindbodyonline.com/ui/documentation/public-api#/php/api-endpoints/appointment/get-staff-appointments
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function hw_mindbody_rest_get_therapist_availability( $request ) {
    $api = hw_mindbody_api();
    
    if ( ! $api->is_configured() ) {
        return new WP_REST_Response( array(
            'success' => false,
            'message' => 'API not configured',
        ), 200 );
    }
    
    $raw_search_name = $request->get_param( 'search' );
    $search_name     = $raw_search_name ? strtolower( trim( $raw_search_name ) ) : '';
    
    // Clean search term
    if ( $search_name ) {
        if ( strpos( $search_name, ':' ) !== false ) {
            $search_name = trim( explode( ':', $search_name )[0] );
        }
        $search_name = preg_replace( '/\s*(mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)[\s,]*.*/i', '', $search_name );
        $search_name = trim( $search_name );
    }
    
    // The 8 target treatment categories
    $target_categories = array(
        'Acupuncture & Eastern Med',
        'Energy & Healing Therapies',
        'Face & Skin Treatments',
        'Fertility, Pre & Postnatal',
        'Massage & Bodywork',
        'Mind & Emotional Health',
        'Natural Medicine/ Nutrition',
        'Osteopathy & Physiotherapy',
    );
    
    $day_names = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
    $debug_info = array();
    
    // STEP 1: Get staff members with their IDs
    $staff = $api->get_staff( array( 'Limit' => 500 ) );
    $staff_by_id = array();
    $staff_photos = array();
    $staff_ids = array();
    
    if ( is_wp_error( $staff ) ) {
        $debug_info[] = 'Staff Error: ' . $staff->get_error_message();
    } elseif ( is_array( $staff ) ) {
        $debug_info[] = 'Staff members found: ' . count( $staff );
        foreach ( $staff as $s ) {
            $staff_id = $s['Id'] ?? null;
            $name = trim( ( $s['FirstName'] ?? '' ) . ' ' . ( $s['LastName'] ?? '' ) );
            if ( $staff_id && $name ) {
                $staff_ids[] = $staff_id;
                $staff_by_id[ $staff_id ] = array(
                    'Id'              => $staff_id,
                    'Name'            => $name,
                    'FirstName'       => $s['FirstName'] ?? '',
                    'LastName'        => $s['LastName'] ?? '',
                    'ImageUrl'        => $s['ImageUrl'] ?? '',
                    'available_days'  => array(),
                    'appointment_count' => 0,
                );
                if ( ! empty( $s['ImageUrl'] ) ) {
                    $staff_photos[ $name ] = $s['ImageUrl'];
                }
            }
        }
    }
    
    // STEP 2: Get services to identify therapists in 8 target categories
    $services = $api->get_services( array( 'Limit' => 1000 ) );
    $therapist_names_in_categories = array();
    $services_count = 0;
    
    if ( ! is_wp_error( $services ) && is_array( $services ) ) {
        foreach ( $services as $service ) {
            $service_name = $service['Name'] ?? '';
            $category = $service['ServiceCategory']['Name'] ?? $service['Program'] ?? '';
            
            // Check if in target categories
            $matched = false;
            foreach ( $target_categories as $target ) {
                if ( stripos( $category, $target ) !== false || stripos( $target, $category ) !== false ) {
                    $matched = true;
                    break;
                }
            }
            
            if ( ! $matched ) {
                continue;
            }
            
            $services_count++;
            
            // Extract therapist name
            if ( preg_match( '/\s-\s([A-Z][a-z]+(?:\s+[A-Z]\.?)?(?:\s+[A-Z][a-z]+)?)\s*(?:-|$|\d)/i', $service_name, $matches ) ) {
                $therapist_name = trim( $matches[1] );
                if ( ! preg_match( '/^\d+\s*(min)?$/i', $therapist_name ) ) {
                    $therapist_names_in_categories[ $therapist_name ] = true;
                }
            }
        }
        $debug_info[] = 'Services in 8 categories: ' . $services_count;
        $debug_info[] = 'Therapists extracted from services: ' . count( $therapist_names_in_categories );
    }
    
    // STEP 3: Get staff appointments - THIS IS THE KEY API CALL
    // Uses GET /appointment/staffappointments to see when staff have appointments scheduled
    $start_date = gmdate( 'Y-m-d' );
    $end_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
    $staff_appointments = array();
    $appointments_api_error = null;
    
    // Query staff appointments
    $appt_params = array(
        'StartDate' => $start_date,
        'EndDate'   => $end_date,
        'Limit'     => 1000,
    );
    
    $debug_info[] = 'Staff Appointments Query: ' . $start_date . ' to ' . $end_date;
    
    $appt_response = $api->get_staff_appointments( $appt_params );
    
    if ( is_wp_error( $appt_response ) ) {
        $appointments_api_error = $appt_response->get_error_message();
        $debug_info[] = 'Staff Appointments Error: ' . $appointments_api_error;
    } else {
        $staff_appointments = $appt_response;
        $debug_info[] = 'Staff Appointments returned: ' . count( $staff_appointments );
    }
    
    // STEP 4: Process appointments to extract working days per staff
    $staff_working_days = array(); // staff_id => array of day names
    
    foreach ( $staff_appointments as $appt ) {
        $staff_id = null;
        $staff_name = null;
        
        // Try to get staff info from appointment
        if ( isset( $appt['Staff']['Id'] ) ) {
            $staff_id = $appt['Staff']['Id'];
            $staff_name = trim( ( $appt['Staff']['FirstName'] ?? '' ) . ' ' . ( $appt['Staff']['LastName'] ?? '' ) );
        } elseif ( isset( $appt['StaffId'] ) ) {
            $staff_id = $appt['StaffId'];
        }
        
        if ( ! $staff_id ) {
            continue;
        }
        
        // Get the appointment start date/time
        $start_time = $appt['StartDateTime'] ?? null;
        if ( ! $start_time ) {
            continue;
        }
        
        $timestamp = strtotime( $start_time );
        if ( ! $timestamp ) {
            continue;
        }
        
        $day_of_week = intval( gmdate( 'w', $timestamp ) );
        $day_name = $day_names[ $day_of_week ];
        
        // Initialize staff working days
        if ( ! isset( $staff_working_days[ $staff_id ] ) ) {
            $staff_working_days[ $staff_id ] = array(
                'days'        => array(),
                'appointments' => 0,
                'name'        => $staff_name,
            );
        }
        
        // Add day if not already present
        if ( ! in_array( $day_name, $staff_working_days[ $staff_id ]['days'], true ) ) {
            $staff_working_days[ $staff_id ]['days'][] = $day_name;
        }
        $staff_working_days[ $staff_id ]['appointments']++;
        
        // Also update staff_by_id if exists
        if ( isset( $staff_by_id[ $staff_id ] ) ) {
            if ( ! in_array( $day_name, $staff_by_id[ $staff_id ]['available_days'], true ) ) {
                $staff_by_id[ $staff_id ]['available_days'][] = $day_name;
            }
            $staff_by_id[ $staff_id ]['appointment_count']++;
        }
    }
    
    $debug_info[] = 'Staff with scheduled appointments: ' . count( $staff_working_days );
    
    // STEP 5: Build therapist list - only those in 8 target categories
    $therapists = array();
    
    foreach ( $therapist_names_in_categories as $name => $dummy ) {
        // Filter by search
        if ( $search_name && stripos( $name, $search_name ) === false ) {
            continue;
        }
        
        // Find matching staff member by name
        $available_days = array();
        $appointment_count = 0;
        $staff_id = null;
        $image_url = $staff_photos[ $name ] ?? '';
        
        // Look for staff by name match
        foreach ( $staff_by_id as $sid => $staff_data ) {
            if ( stripos( $staff_data['Name'], $name ) !== false || 
                 stripos( $name, $staff_data['FirstName'] ) !== false ) {
                $available_days = $staff_data['available_days'];
                $appointment_count = $staff_data['appointment_count'];
                $staff_id = $sid;
                if ( ! $image_url && ! empty( $staff_data['ImageUrl'] ) ) {
                    $image_url = $staff_data['ImageUrl'];
                }
                break;
            }
        }
        
        // Also check staff_working_days by name
        if ( empty( $available_days ) ) {
            foreach ( $staff_working_days as $sid => $work_data ) {
                if ( $work_data['name'] && stripos( $work_data['name'], $name ) !== false ) {
                    $available_days = $work_data['days'];
                    $appointment_count = $work_data['appointments'];
                    break;
                }
            }
        }
        
        // Sort days (Mon-Sun order)
        if ( ! empty( $available_days ) ) {
            $day_order = array_flip( $day_names );
            usort( $available_days, function( $a, $b ) use ( $day_order ) {
                return ( $day_order[ $a ] ?? 99 ) - ( $day_order[ $b ] ?? 99 );
            } );
        }
        
        $therapists[] = array(
            'Name'                => $name,
            'StaffId'             => $staff_id,
            'ImageUrl'            => $image_url,
            'available_days'      => $available_days,
            'availability_source' => ! empty( $available_days ) ? 'mindbody_api' : 'none',
            'appointment_count'   => $appointment_count,
        );
    }
    
    // Sort by name
    usort( $therapists, function( $a, $b ) {
        return strcmp( $a['Name'], $b['Name'] );
    } );
    
    // Summary
    $total_therapists = count( $therapists );
    $therapists_with_availability = count( array_filter( $therapists, function( $t ) {
        return ! empty( $t['available_days'] );
    } ) );
    
    return new WP_REST_Response( array(
        'success'                => true,
        'search_term'            => $search_name,
        'target_categories'      => $target_categories,
        'date_range'             => array(
            'start' => $start_date,
            'end'   => $end_date,
        ),
        'summary'                => array(
            'total_therapists'               => $total_therapists,
            'therapists_with_availability'   => $therapists_with_availability,
            'therapists_without_availability' => $total_therapists - $therapists_with_availability,
            'total_appointments'             => count( $staff_appointments ),
            'services_in_categories'         => $services_count,
        ),
        'therapists'             => $therapists,
        'debug'                  => $debug_info,
        'api_error'              => $appointments_api_error,
        'note'                   => 'Availability from Mindbody GET /appointment/staffappointments API (30-day window). Days shown are when staff have scheduled appointments.',
    ), 200 );
}

