<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Collect.net packrobot machines shipping method
 *
 * @class     WC_Estonian_Shipping_Method_Collect_Net
 * @extends   WC_Estonian_Shipping_Method_Terminals
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
class WC_Estonian_Shipping_Method_Collect_Net extends WC_Estonian_Shipping_Method_Terminals {

	/**
	 * Just a indicator whether session with API has initialized
	 *
	 * @var boolean
	 */
	private $session_created = false;

	/**
	 * Collection of session cookies to be used with API requests
	 *
	 * @var array
	 */
	private $session_cookies = array();

	/**
	 * API url
	 *
	 * @var string
	 */
	private $api_url = 'https://app.collect.net/api/';


	/**
	 * Class constructor
	 */
	function __construct() {
		// Identify method
		$this->id                     = 'collect_net';
		$this->method_title           = __( 'Collect.net', 'wc-estonian-shipping-methods' );

		// Construct parent
		parent::__construct();

		$this->country                = 'EE';
		$this->terminals_template     = 'collect-net';
		$this->auth_error_notice      = $this->id . '_session_error';

		// Translations
		$this->i18n_selected_terminal = __( 'Chosen packrobot', 'wc-estonian-shipping-methods' );

		// Add/merge form fields
		$this->add_extra_form_fields();

		// Send order to environment
		add_action( 'woocommerce_order_status_changed',      array( $this, 'maybe_create_ticket' ), 10, 3 );

		// Checkout phone numbe validation
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_customer_phone_number' ), 10, 1 );
	}

	/**
	 * Add some more fields
	 */
	public function add_extra_form_fields() {
		$this->form_fields = array_merge(
			$this->form_fields,
			array(
				'submit_trigger'   => array(
					'title'          => __( 'Order status', 'wc-estonian-shipping-methods' ),
					'type'           => 'select',
					'default'        => 'processing',
					'desc_tip'       => __( 'Which order status will submit the data from WooCommerce to Collect.net application', 'wc-estonian-shipping-methods' ),
					'options'        => array(
						'processing' => __( 'Processing', 'wc-estonian-shipping-methods' ),
						'completed'  => __( 'Completed', 'wc-estonian-shipping-methods' ),
					)
				),
				'collect_username' => array(
					'title'          => __( 'Collect.net username', 'wc-estonian-shipping-methods' ),
					'type'           => 'text'
				),
				'collect_password' => array(
					'title'          => __( 'Collect.net password', 'wc-estonian-shipping-methods' ),
					'type'           => 'password'
				)
			)
		);

		// If session creation succeeds, add role selection
		if( is_admin() && ! is_ajax() && $this->create_session() ) {
			// Fetch roles from api
			$roles = $this->fetch_user_roles();

			$this->form_fields['collect_role_id'] = array(
				'title'          => __( 'User Role', 'wc-estonian-shipping-methods' ),
				'type'           => 'select',
				'default'        => '',
				'desc_tip'       => __( 'Which user role will be used to create tickets for Collect.net', 'wc-estonian-shipping-methods' ),
				'options'        => $roles,
			);
		}
	}

	/**
	 * Validates user submitted terminal
	 *
	 * @param  array $posted Checkout data
	 *
	 * @return void
	 */
	function validate_user_selected_terminal( $posted ) {
		// Chcek if our field was submitted
		if( isset( $_POST[ $this->field_name ] ) && $_POST[ $this->field_name ] == '' ) {
			// Be sure shipping method was posted
			if( isset( $posted['shipping_method'] ) && is_array( $posted['shipping_method'] ) ) {
				// Check if it was regular parcel terminal
				if( in_array( $this->id, $posted['shipping_method'] ) ) {
					// Add checkout error
					wc_add_notice( __( 'Please select a parcel terminal', 'wc-estonian-shipping-methods' ), 'error' );
				}
			}
		}
	}

	/**
	 * Validates user submitted phone number. Collect.net requires country prefix.
	 *
	 * @param  array $posted Checkout data
	 *
	 * @return void
	 */
	function validate_customer_phone_number( $posted ) {
		// Chcek if our field was submitted
		if( isset( $_POST['billing_phone'] ) && $phone_number = $_POST['billing_phone'] ) {
			// Be sure shipping method was posted
			if( isset( $posted['shipping_method'] ) && is_array( $posted['shipping_method'] ) ) {
				// Check if it was regular parcel terminal
				if( in_array( $this->id, $posted['shipping_method'] ) ) {
					// Remove spaces
					$phone_number        = str_replace( ' ' , '', $phone_number );
					$have_country_prefix = substr( $phone_number, 0, 1 ) == '+';
					$is_phone_valid      = apply_filters( 'wc_shipping_' . $this->id . '_is_phone_valid', $have_country_prefix, $phone_number, $posted );

					// If phone is not valid, add error
					if( ! $is_phone_valid ) {
						// Add checkout error
						wc_add_notice( __( 'Please add country prefix to the phone number (eg. +372).', 'wc-estonian-shipping-methods' ), 'error' );
					}
				}
			}
		}
	}

	/**
	 * Fetches locations and stores them to cache.
	 *
	 * @return array Terminals
	 */
	public function get_terminals() {
		// Fetch terminals from cache
		$terminals_cache = $this->get_terminals_cache();

		if( $terminals_cache !== null ) {
			return $terminals_cache;
		}

		// Create a new session
		$this->create_session();

		// Fetch PUDOs
		$terminals  = $this->fetch_pudos();
		$locations  = array();

		// Only continue if we have array of terminals
		if( ! ( is_array( $terminals ) && ! empty( $terminals ) ) ) {
			return array();
		}

		// Properly format the PUDOs
		foreach( $terminals as $key => $location ) {
			// We only want active packrobots
			if( $location->active == 1 ) {
				$locations[] = (object) array(
					'place_id' => $location->id,
					'name'     => $location->name,
					'address'  => $location->address->address,
					'city'     => $location->address->city,
				);
			}
		}

		// Save cache
		$this->save_terminals_cache( $locations );

		return $locations;
	}

	/**
	 * Submit package to the Collect.net application
	 *
	 * @param  mixed $order Order ID or object
	 *
	 * @return void
	 */
	public function maybe_create_ticket( $order, $old_status, $new_status ) {
		// Fetch order if we have only ID
		if( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Only when order status suits us
		if( $this->get_option( 'submit_trigger', 'processing' ) != $new_status ) {
			return false;
		}

		// We only want orders with Collect.net shipping method
		if( $order->has_shipping_method( $this->id ) ) {
			// Packrobot data
			$terminal_id = $this->get_order_terminal( wc_esm_get_order_id( $order ) );

			// Prepare ticket data
			$ticket      = array(
				'id'                       => $order->get_order_number(),
				'uuid'                     => $this->generate_ticket_uuid(),
				'hold_days'                => 0,
				'return_ticket_valid_days' => 0,
				'validity_period'          => 0,
				'disability'               => false,
				'description'              => sprintf( '%s #%s', get_bloginfo( 'name', 'display' ), $order->get_order_number() ),
				'receiver'                 => array(
					'name'    => esc_html( wc_esm_get_customer_shipping_name( $order ) ),
					'phone'   => esc_html( wc_esm_get_order_billing_phone( $order ) )
				),
				'pudo_point'               => array(
					'id'      => $terminal_id
				)
			);

			// Hook filters
			$ticket  = apply_filters( 'wc_shipping_'. $this->id .'_ticket_data', $ticket, wc_esm_get_order_id( $order ) );

			// Submit ticket to API
			$request = $this->request( 'POST', $this->get_api_endpoint( 'tickets' ), $ticket );

			// Check if ticket creation succeeded
			if( $request['success'] === true ) {
				// Action hooks
				do_action( 'wc_shipping_' . $this->id . '_ticket_created', $request['data'], wc_esm_get_order_id( $order ) );

				// Add ticket ID to order notes
				$order->add_order_note( sprintf( __( '%s: Ticket created with ID %d.', 'wc-estonian-shipping-methods' ), $this->get_title(), $request['data']->id ) );

				// Add ticket ID to order meta
				update_post_meta( wc_esm_get_order_id( $order ), $this->id . '_ticket_id', $request['data']->id );
				update_post_meta( wc_esm_get_order_id( $order ), $this->id . '_ticket_uuid', $ticket['uuid'] );
			}
			else {
				// Add ticket ID to order notes
				$order->add_order_note( sprintf( __( '%s: Ticket creation failed: %s', 'wc-estonian-shipping-methods' ), $this->get_title(), $request['data']->message ) );

				// Debug data
				$this->debug( $request );
			}
		}
	}

	/**
	 * Show session creation notice on admin page, if not already shown
	 *
	 * @return void
	 */
	public function show_failed_credentials_notice() {
		if( ! WC_Admin_Notices::has_notice( $this->auth_error_notice ) ) {
			WC_Admin_Notices::add_custom_notice( $this->auth_error_notice, sprintf( __( '%s: Could not create a session with entered credentials. %s to review your settings.', 'wc-estonian-shipping-methods' ), '<strong>' . $this->get_title() . '</strong>', '<a href="'.  admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . strtolower( $this->id ) ) .'">'. __( 'Click here', 'wc-estonian-shipping-methods' ) .'</a>' ) );
		}
	}

	/**
	 * Remove session creation notice
	 *
	 * @return void
	 */
	public function remove_failed_credentials_notice() {
		if( WC_Admin_Notices::has_notice( $this->auth_error_notice ) ) {
			WC_Admin_Notices::remove_notice( $this->auth_error_notice );
		}
	}

	/**
	 * Process submitted changed
	 *
	 * @see WC_Settings_API::process_admin_options
	 */
	public function process_admin_options() {
		// Only if this method is enabled
		if( $this->enabled == 'no' ) {
			return false;
		}

		// Show error notice if session couldn't be created
		if( ! $this->create_session() ) {
			$this->show_failed_credentials_notice();
		}
		else {
			$this->remove_failed_credentials_notice();
		}

		return parent::process_admin_options();
	}


	/**
	 * Do not allow this method if username and password are not set or session could not be created
	 *
	 * @see WC_Shipping_Method::is_available
	 */
	public function is_available( $package = array() ) {
		if( ! $this->get_option( 'collect_username' ) || ! $this->get_option( 'collect_password' ) || ( ! $this->session_created() && ! $this->create_session() ) ) {
			$this->show_failed_credentials_notice();

			return false;
		}

		return parent::is_available( $package );
	}

	/**
	 * Prepare API url for request
	 *
	 * @param  string $endpoint API endpoint
	 * @param  array  $query    Extra query
	 *
	 * @return string           API url with endpoint and query parameters
	 */
	function get_api_endpoint( $endpoint = '', $query = array() ) {
		return trailingslashit( $this->api_url ) . trailingslashit( $endpoint ) . ( ! empty( $query ) ? '?' . http_build_query( $query ) : '' );
	}

	/**
	 * Check if session was created
	 *
	 * @return boolean True if created
	 */
	function session_created() {
		return $this->session_created === true;
	}

	/**
	 * Create a session with Collect.net API
	 *
	 * @todo   Error message on admin page  if login failed
	 *
	 * @return boolean True if succeeded
	 */
	public function create_session() {
		// Session already created?
		if( $this->session_created() ) {
			return true;
		}

		// Submit session request to API
		$credentials = array(
			'email'    => $this->get_option( 'collect_username' ),
			'password' => $this->get_option( 'collect_password' )
		);
		$request     = $this->request( 'POST', $this->get_api_endpoint( 'session' ), $credentials );

		// If status code is 200, session was created
		$this->session_created = $request['success'] === true;

		// Collect cookies
		if( $this->session_created() ) {
			$this->session_cookies = wp_remote_retrieve_cookies( $request['response'] );

			// If role ID is set, then alter the cookie value to the role ID
			if( $collect_role_id = $this->get_option( 'collect_role_id', false ) ) {
				foreach( $this->session_cookies as $cookie ) {
					if( $cookie->name == 'role_client_id' ) {
						$cookie->value = $collect_role_id;

						break;
					}
				}
			}
		}

		// Result
		return $this->session_created();
	}

	/**
	 * Fetch public PUDO points
	 *
	 * @return array PUDOs
	 */
	public function fetch_pudos() {
		// We need session to proceed
		if( ! $this->session_created() ) {
			return array();
		}

		// Fetch pudos
		$request  = $this->request( 'GET', $this->get_api_endpoint( 'pudos', array( 'private' => 'any' ) ) );

		// Check if request succeeded
		if( $request['success'] === true ) {
			return $request['data'];
		}
		else {
			return array();
		}
	}

	/**
	 * Fetch authenticated user roles
	 *
	 * @return array Roles
	 */
	public function fetch_user_roles() {
		// We need session to proceed
		if( ! $this->session_created() ) {
			return array();
		}

		// Fetch pudos
		$request  = $this->request( 'GET', $this->get_api_endpoint( 'roles' ) );

		// Check if request succeeded
		if( $request['success'] === true ) {
			$roles = [];

			// Prepare roles list
			foreach( $request['data'] as $role ) {
				$roles[ $role->client->id ] = $role->client->name;
			}

			return $roles;
		}
		else {
			return array();
		}
	}

	/**
	 * Generate UUID for the ticket
	 *
	 * @url https://gist.github.com/dahnielson/508447
	 *
	 * @return string RFC 4122 compliant UUID
	 */
	private function generate_ticket_uuid()	{
		$uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);

		return apply_filters( 'wc_shipping_' . $this->id . '_ticket_uuid', $uuid );
	}

	/**
	 * Send request to the API
	 *
	 * @param  string $method Request method (POST or GET)
	 * @param  string $url    Request URL (API url)
	 * @param  array  $body   Contents to be sent. Will be JSON encoded.
	 *
	 * @return array          Response
	 */
	private function request( $method = 'GET', $url, $body = array() ) {
		// Only allow certain methods
		if( ! in_array( $method, array( 'GET', 'POST' ) ) ) {
			return false;
		}

		// Prepare data
		$data = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/json'
			)
		);

		// Add body if needed
		if( is_array( $body ) && ! empty( $body ) ) {
			$data['body'] = json_encode( $body );
		}

		// Maybe pass on cookies aswell
		if( $this->session_created() && ! empty( $this->session_cookies ) ) {
			$data['cookies'] = $this->session_cookies;
		}

		// Submit
		$response = wp_remote_request( $url, $data );

		return array(
			'success'  => wp_remote_retrieve_response_code( $response ) == 200,
			'response' => $response,
			'data'     => json_decode( wp_remote_retrieve_body( $response ) )
		);
	}
}