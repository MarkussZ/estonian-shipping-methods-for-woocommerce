<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Omniva parcel machines shipping method
 *
 * @class     WC_Estonian_Shipping_Method_Omniva_Parcel_Machines_LV
 * @extends   WC_Estonian_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
class WC_Estonian_Shipping_Method_Omniva_Parcel_Machines_LV extends WC_Estonian_Shipping_Method_Omniva {

	/**
	 * Class constructor
	 */
	function __construct() {
		$this->id           = 'omniva_parcel_machines_lv';
		$this->method_title = __( 'Omniva Latvia', 'wc-estonian-shipping-methods' );

		$this->country      = 'LV';

		parent::__construct();
	}

	function is_available( $package = array() ) {
		return parent::is_available( $package ) && ( ! isset( $this->country ) || ( isset( $this->country ) && isset( $package['destination'] ) && isset( $package['destination']['country'] ) && $package['destination']['country'] == $this->country ) );
	}
}