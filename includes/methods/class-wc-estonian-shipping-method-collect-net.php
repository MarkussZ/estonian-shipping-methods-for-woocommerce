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
		$this->id                 = 'collect_net';
		$this->method_title       = __( 'Collect.net', 'wc-estonian-shipping-methods' );

		$this->country            = 'EE';
		$this->terminals_template = 'collect-net';

		// Construct parent
		parent::__construct();

		// Add/merge form fields
		$this->add_form_fields();
	}

	/**
	 * Add some more fields
	 */
	public function add_form_fields() {
		$this->form_fields = array_merge(
			$this->form_fields,
			array(
				'collect_username' => array(
					'title' => __( 'Collect.net username', 'wc-estonian-shipping-methods' ),
					'type'  => 'text'
				),
				'collect_password' => array(
					'title' => __( 'Collect.net password', 'wc-estonian-shipping-methods' ),
					'type'  => 'password'
				)
			)
		);
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
	function create_session() {
		// Session already created?
		if( $this->session_created() ) {
			return true;
		}

		// Submit session request to API
		$response = wp_remote_post(
			$this->get_api_endpoint( 'session' ),
			array(
				'headers' => array(
					'Content-Type' => 'application/json'
				),
				'body'    => json_encode(
					array(
						'email'    => $this->get_option( 'collect_username' ),
						'password' => $this->get_option( 'collect_password' )
					)
				)
			)
		);

		// If status code is 200, session was created
		$this->session_created = wp_remote_retrieve_response_code( $response ) == 200;

		// Fetch returned session data
		if( $this->session_created() && isset( $response['headers']['set-cookie'] ) ) {
			// Set cookies for later API requests
			foreach( $response['headers']['set-cookie'] as $cookie ) {
				$this->session_cookies[] = new WP_Http_Cookie( $cookie );
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
			return null;
		}

		// Fetch pudos
		$response = wp_remote_get(
			$this->get_api_endpoint( 'pudos', [ 'private' => 'any' ] ),
			array(
				'cookies' => $this->session_cookies
			)
		);

		// 200 status code is our favourite
		if( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}
		else {
			return array();
		}
	}
}