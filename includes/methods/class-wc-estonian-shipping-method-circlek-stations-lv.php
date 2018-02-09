<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * CircleK stations shipping method
 *
 * @class     WC_Estonian_Shipping_Method_CircleK_Stations_LV
 * @extends   WC_Estonian_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
class WC_Estonian_Shipping_Method_CircleK_Stations_LV extends WC_Estonian_Shipping_Method_Circlek {

	/**
	 * Class constructor
	 */
	function __construct() {
		// Identify method
		$this->id               = 'circlek_stations_lv';
		$this->method_title     = __( 'CircleK stations', 'wc-estonian-shipping-methods' );

		// Construct parent
		parent::__construct();

		$this->country          = 'LV';

		// Set variables which will contain address and which city in locations
		$this->variable_address = 'ADDRESS';
		$this->variable_city    = 'CITY';
	}
}