<?php
/**
 * Mindbody API Class
 * 
 * Provides a comprehensive OOP interface for Mindbody API v6
 * 
 * @package Home_Wellness
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HW_Mindbody_API
 * 
 * Handles all Mindbody API interactions
 */
class HW_Mindbody_API {
    
    /**
     * API Key
     * @var string
     */
    private $api_key;
    
    /**
     * Site ID
     * @var string
     */
    private $site_id;
    
    /**
     * API Secret
     * @var string
     */
    private $api_secret;
    
    /**
     * Source Name
     * @var string
     */
    private $source_name;
    
    /**
     * Base URL for Mindbody API v6
     * @var string
     */
    private $base_url = 'https://api.mindbodyonline.com/public/v6';
    
    /**
     * Singleton instance
     * @var HW_Mindbody_API
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key     = get_option( 'mindbody_api_key', '' );
        $this->site_id     = get_option( 'mindbody_site_id', '' );
        $this->api_secret  = get_option( 'mindbody_api_secret', '' );
        $this->source_name = get_option( 'mindbody_source_name', '' );
    }
    
    /**
     * Get API credentials
     * 
     * @return array
     */
    public function get_credentials() {
        return array(
            'api_key'     => $this->api_key,
            'site_id'     => $this->site_id,
            'api_secret'  => $this->api_secret,
            'source_name' => $this->source_name,
        );
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->api_key ) && ! empty( $this->site_id );
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param string $method   HTTP method (GET, POST, PUT, etc.)
     * @param array  $data     Request data
     * @return array|WP_Error
     */
    public function make_request( $endpoint, $method = 'GET', $data = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'missing_credentials', 'Mindbody API credentials are not configured.' );
        }
        
        $url = $this->base_url . $endpoint;
        
        // Build headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Api-Key'      => $this->api_key,
            'SiteId'       => $this->site_id,
            'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        );
        
        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        );
        
        // Add body for POST/PUT requests
        if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $data );
        }
        
        // Add query params for GET requests
        if ( ! empty( $data ) && 'GET' === $method ) {
            $query_parts = array();
            foreach ( $data as $key => $value ) {
                if ( is_array( $value ) ) {
                    // Mindbody API expects repeated params: SessionTypeIds=1&SessionTypeIds=2
                    foreach ( $value as $v ) {
                        $query_parts[] = rawurlencode( $key ) . '=' . rawurlencode( $v );
                    }
                } else {
                    $query_parts[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
                }
            }
            $query_string = implode( '&', $query_parts );
            $url = $url . ( strpos( $url, '?' ) !== false ? '&' : '?' ) . $query_string;
        }
        
        // Make request
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HW Mindbody API Request Error: ' . $response->get_error_message() );
            }
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );
        
        // Handle non-200 responses
        if ( $code < 200 || $code >= 300 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'HW Mindbody API Error Response: ' . $code . ' - ' . $body );
            }
            // Include response body in error for better debugging
            $error_message = 'Mindbody API returned error code: ' . $code;
            $error_data = json_decode( $body, true );
            if ( $error_data && isset( $error_data['Error']['Message'] ) ) {
                $error_message .= ' - ' . $error_data['Error']['Message'];
            } elseif ( $error_data && isset( $error_data['Message'] ) ) {
                $error_message .= ' - ' . $error_data['Message'];
            }
            return new WP_Error( 'api_error', $error_message, array( 'status' => $code, 'body' => $body ) );
        }
        
        $decoded = json_decode( $body, true );
        return $decoded;
    }
    
    /**
     * Get classes
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_classes( $args = array() ) {
        $defaults = array(
            'StartDateTime' => gmdate( 'Y-m-d\TH:i:s' ),
            'EndDateTime'   => gmdate( 'Y-m-d\TH:i:s', strtotime( '+7 days' ) ),
            'Limit'         => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/class/classes', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['Classes'] ) ? $response['Classes'] : array();
    }
    
    /**
     * Get services (for treatments/appointments)
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_services( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/sale/services', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['Services'] ) ? $response['Services'] : array();
    }
    
    /**
     * Get session types (appointment types)
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_session_types( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/appointment/sessiontypes', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            // Fallback to services
            return $this->get_services( $args );
        }
        
        return isset( $response['SessionTypes'] ) ? $response['SessionTypes'] : array();
    }
    
    /**
     * Get staff members
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_staff( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/staff/staff', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // API returns 'StaffMembers' not 'Staff'
        if ( isset( $response['StaffMembers'] ) ) {
            return $response['StaffMembers'];
        }
        
        // Fallback for older API versions
        return isset( $response['Staff'] ) ? $response['Staff'] : array();
    }
    
    /**
     * Get appointment instructors (therapists)
     * 
     * @return array|WP_Error
     */
    public function get_appointment_instructors() {
        $params = array(
            'Limit'                 => 1000,
            'AppointmentInstructor' => 'true',
            'Active'                => 'true',
        );
        
        $response = $this->make_request( '/staff/staff', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // API returns 'StaffMembers' not 'Staff'
        if ( isset( $response['StaffMembers'] ) ) {
            return $response['StaffMembers'];
        }
        
        return isset( $response['Staff'] ) ? $response['Staff'] : array();
    }
    
    /**
     * Get staff details by ID or name
     * 
     * @param int|null    $staff_id   Staff ID
     * @param string|null $staff_name Staff name
     * @return array|null|WP_Error
     */
    public function get_staff_details( $staff_id = null, $staff_name = null ) {
        $params = array( 'Limit' => 1000 );
        
        if ( $staff_id ) {
            $params['StaffIds'] = array( intval( $staff_id ) );
        }
        
        $response = $this->make_request( '/staff/staff', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        // API returns 'StaffMembers' not 'Staff'
        $staff_list = array();
        if ( isset( $response['StaffMembers'] ) ) {
            $staff_list = $response['StaffMembers'];
        } elseif ( isset( $response['Staff'] ) ) {
            $staff_list = $response['Staff'];
        }
        
        // If searching by staff_id, return first result
        if ( $staff_id && ! empty( $staff_list ) ) {
            return $staff_list[0];
        }
        
        // Search by name
        if ( $staff_name ) {
            // First try the current response
            foreach ( $staff_list as $staff ) {
                $full_name = trim( ( $staff['FirstName'] ?? '' ) . ' ' . ( $staff['LastName'] ?? '' ) );
                if ( strtolower( $full_name ) === strtolower( $staff_name ) ) {
                    return $staff;
                }
            }
            
            // Partial match
            foreach ( $staff_list as $staff ) {
                $full_name = trim( ( $staff['FirstName'] ?? '' ) . ' ' . ( $staff['LastName'] ?? '' ) );
                if ( stripos( $full_name, $staff_name ) !== false || stripos( $staff_name, $full_name ) !== false ) {
                    return $staff;
                }
            }
            
            // Try first name only
            foreach ( $staff_list as $staff ) {
                $first_name = $staff['FirstName'] ?? '';
                if ( stripos( $staff_name, $first_name ) !== false && strlen( $first_name ) > 2 ) {
                    return $staff;
                }
            }
        }
        
        return ! empty( $staff_list ) ? $staff_list[0] : null;
    }
    
    /**
     * Get locations
     * 
     * @return array|WP_Error
     */
    public function get_locations() {
        $response = $this->make_request( '/site/locations', 'GET' );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['Locations'] ) ? $response['Locations'] : array();
    }
    
    /**
     * Get bookable items (available appointment slots)
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_bookable_items( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/appointment/bookableitems', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['BookableItems'] ) ? $response['BookableItems'] : array();
    }
    
    /**
     * Get staff appointments (scheduled appointments for staff members)
     * 
     * This endpoint returns actual booked appointments for staff,
     * showing when they are scheduled to work.
     * 
     * @see https://developers.mindbodyonline.com/ui/documentation/public-api#/php/api-endpoints/appointment/get-staff-appointments
     * 
     * @param array $args Query arguments including StaffIds, StartDateTime, EndDateTime
     * @return array|WP_Error
     */
    public function get_staff_appointments( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/appointment/staffappointments', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['Appointments'] ) ? $response['Appointments'] : array();
    }
    
    /**
     * Get active session times
     * 
     * @param array $args Query arguments
     * @return array|WP_Error
     */
    public function get_active_session_times( $args = array() ) {
        $defaults = array(
            'Limit' => 500,
        );
        
        $params   = wp_parse_args( $args, $defaults );
        $response = $this->make_request( '/appointment/activesessiontimes', 'GET', $params );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['ActiveSessionTimes'] ) ? $response['ActiveSessionTimes'] : array();
    }
    
    /**
     * Add client to appointment
     * 
     * @param array $data Appointment data
     * @return array|WP_Error
     */
    public function book_appointment( $data ) {
        $response = $this->make_request( '/appointment/addappointment', 'POST', $data );
        return $response;
    }
    
    /**
     * Add client to class
     * 
     * @param int $client_id Client ID
     * @param int $class_id  Class ID
     * @return array|WP_Error
     */
    public function add_client_to_class( $client_id, $class_id ) {
        $data = array(
            'ClientId'  => $client_id,
            'ClassId'   => $class_id,
            'Test'      => false,
            'SendEmail' => true,
        );
        
        $response = $this->make_request( '/class/addclienttoclass', 'POST', $data );
        return $response;
    }
    
    /**
     * Get class descriptions
     * 
     * @return array|WP_Error
     */
    public function get_class_descriptions() {
        $response = $this->make_request( '/class/classdescriptions', 'GET', array( 'Limit' => 100 ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return isset( $response['ClassDescriptions'] ) ? $response['ClassDescriptions'] : array();
    }
}

/**
 * Get Mindbody API instance
 * 
 * @return HW_Mindbody_API
 */
function hw_mindbody_api() {
    return HW_Mindbody_API::get_instance();
}

