<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Omniva shipping method
 *
 * @class     WC_Estonian_Shipping_Method_DPD_Shops
 * @extends   WC_Estonian_Shipping_Method_Terminals
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
abstract class WC_Estonian_Shipping_Method_DPD_Shops extends WC_Estonian_Shipping_Method_Terminals {

	public $terminals_url = 'ftp://ftp.dpd.ee/parcelshop/psexport_latest.csv';

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->terminals_template = 'dpd';

		// Construct parent
		parent::__construct();
	}

	public function get_terminals( $filter_country = false, $filter_type = 0 ) {
		$filter_country = $filter_country ? $filter_country : $this->get_shipping_country();
		$locations      = array();

		if( ( $handle = fopen( $this->terminals_url, "r" ) ) !== FALSE ) {
			while( ( $data = fgetcsv( $handle, 1000, "|" ) ) !== FALSE ) {
				$shop_location_id = $data[22];
				$shop_country     = substr( $shop_location_id, 0, 2 );

				if( $filter_country != $shop_country ) {
					continue;
				}

				$locations[]      = (object) array(
					'place_id'   => $shop_location_id,
					'zipcode'    => $data[4],
					'name'       => utf8_encode( $data[2] ),
					'address'    => utf8_encode( $data[3] ),
					'city'       => utf8_encode( $data[5] )
				);
			}
		}

		return $locations;
	}

	function is_available( $package = array() ) {
		return parent::is_available( $package ) && ( ! isset( $this->country ) || ( isset( $this->country ) && isset( $package['destination'] ) && isset( $package['destination']['country'] ) && $package['destination']['country'] == $this->country ) );
	}

	/**
	 * Translates place ID to place name
	 *
	 * @param  integer $place_id Place ID
	 * @return string            Place name
	 */
	function get_terminal_name( $place_id ) {
		$terminals = $this->get_terminals();

		foreach( $terminals as $terminal ) {
			if( $terminal->place_id == $place_id ) {
				return $this->get_formatted_terminal_name( $terminal );

				break;
			}
		}
	}

	/**
	 * Get selected terminal ID from order meta
	 * @param  integer $order_id Order ID
	 * @return integer           Selected terminal ID
	 */
	function get_order_terminal( $order_id ) {
		return get_post_meta( $order_id, $this->field_name, true );
	}
}