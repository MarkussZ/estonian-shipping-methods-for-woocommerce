<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Omniva parcel machines shipping method
 *
 * @class     WC_Estonian_Shipping_Method_Omniva_Parcel_Machines_LT
 * @extends   WC_Estonian_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
class WC_Estonian_Shipping_Method_Omniva_Parcel_Machines_LT extends WC_Estonian_Shipping_Method_Omniva {

	/**
	 * Class constructor
	 */
	function __construct() {
		$this->id           = 'omniva_parcel_machines_lt';
		$this->method_title = __( 'Omniva Lithuania', 'wc-estonian-shipping-methods' );

		$this->country      = 'LT';

		// Add/merge form fields
		parent::__construct();
	}

	public function get_terminals( $filter_country = false, $filter_type = 0 ) {
		// Fetch terminals from cache
		$terminals_cache = $this->get_terminals_cache();

		if( $terminals_cache !== null ) {
			return $terminals_cache;
		}

		$terminals_json  = file_get_contents( $this->terminals_url );
		$terminals_json  = json_decode( $terminals_json );

		$filter_country  = $filter_country ? $filter_country : $this->get_shipping_country();
		$locations       = array();

		foreach( $terminals_json as $key => $location ) {
			if( $location->A0_NAME == $filter_country && $location->TYPE == $filter_type ) {
				$locations[] = (object) array(
					'place_id'   => $location->ZIP,
					'zipcode'    => $location->ZIP,
					'name'       => $location->NAME,
					'address'    => $location->A2_NAME,
					'city'       => $location->A1_NAME,
				);
			}
		}

		// Save cache
		$this->save_terminals_cache( $locations );

		return $locations;
	}

	function is_available( $package = array() ) {
		return parent::is_available( $package ) && ( ! isset( $this->country ) || ( isset( $this->country ) && isset( $package['destination'] ) && isset( $package['destination']['country'] ) && $package['destination']['country'] == $this->country ) );
	}
}