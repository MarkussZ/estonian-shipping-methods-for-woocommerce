<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Abstract class for all of our shipping methods
 *
 * @class     WC_Estonian_Shipping_Method
 * @extends   WC_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
abstract class WC_Estonian_Shipping_Method extends WC_Shipping_Method {
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {

		// Get the settings
		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->enabled     = $this->get_option( 'enabled', 'no' );
		$this->price       = $this->get_option( 'shipping_price', 0 );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Actions
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Set settings fields
	 *
	 * @return void
	 */
	function init_form_fields() {
		// Set fields
		$this->form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable method', 'wc-estonian-shipping-methods' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'label'       => __( 'Enable this shipping method', 'wc-estonian-shipping-methods' )
			),
			'title'           => array(
				'title'       => __( 'Title', 'wc-estonian-shipping-methods' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which user sees during checkout.', 'wc-estonian-shipping-methods' ),
				'default'     => $this->get_title(),
				'desc_tip'    => TRUE
			)
		);
	}

	/**
	 * Check if shipping is available
	 *
	 * @param array $package
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( 'no' == $this->enabled ) {
			return false;
		}
	}

	/**
	 * Calculate shipping price
	 *
	 * @return array
	 */
	public function calculate_shipping() {
		$args = array(
			'id'    => $this->id,
			'label' => $this->title,
			'cost'  => $this->price,
			'taxes' => false
		);

		$this->add_rate( $args );
	}
}
