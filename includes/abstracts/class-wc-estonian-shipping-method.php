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
		$this->title                = $this->get_option( 'title', $this->method_title );
		$this->enabled              = $this->get_option( 'enabled', 'no' );
		$this->shipping_price       = $this->get_option( 'shipping_price', 0 );
		$this->free_shipping_amount = $this->get_option( 'free_shipping_amount', 0 );
		$this->tax_status           = $this->get_option( 'tax_status', 0 );

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
			'enabled'                  => array(
				'title'                => __( 'Enable method', 'wc-estonian-shipping-methods' ),
				'type'                 => 'checkbox',
				'default'              => 'no',
				'label'                => __( 'Enable this shipping method', 'wc-estonian-shipping-methods' )
			),
			'title'                    => array(
				'title'                => __( 'Title', 'wc-estonian-shipping-methods' ),
				'type'                 => 'text',
				'description'          => __( 'This controls the title which user sees during checkout.', 'wc-estonian-shipping-methods' ),
				'default'              => $this->get_title(),
				'desc_tip'             => TRUE
			),
			'shipping_price'           => array(
				'title'                => __( 'Shipping Price', 'wc-estonian-shipping-methods' ),
				'type'                 => 'price',
				'placeholder'          => wc_format_localized_price( 0 ),
				'description'          => __( 'Without taxes', 'wc-estonian-shipping-methods' ),
				'default'              => '0',
				'desc_tip'             => TRUE
			),
			'free_shipping_amount'     => array(
				'title'                => __( 'Free Shipping Amount', 'wc-estonian-shipping-methods' ),
				'type'                 => 'price',
				'placeholder'          => wc_format_localized_price( 0 ),
				'description'          => __( 'Shipping will be free of charge, if order total is equal or bigger than this value. Zero will disable free shipping.', 'wc-estonian-shipping-methods' ),
				'default'              => '0',
				'desc_tip'             => TRUE
			),
			'tax_status'               => array(
				'title'                => __( 'Tax Status', 'wc-estonian-shipping-methods' ),
				'type'                 => 'select',
				'description'          => '',
				'default'              => 'none',
				'options'              => array(
					'taxable'          => __( 'Taxable', 'wc-estonian-shipping-methods' ),
					'none'             => __( 'None', 'wc-estonian-shipping-methods' )
				)
			),
		);
	}

	/**
	 * Check if shipping is available
	 *
	 * @param array $package
	 * @return bool
	 */
	public function is_available( $package ) {
		return ! ( 'no' == $this->enabled );
	}

	/**
	 * Calculate shipping price
	 *
	 * @return array
	 */
	public function calculate_shipping( $package = array() ) {
		$is_free            = FALSE;
		$free_shipping_from = floatval( $this->free_shipping_amount );

		if( $free_shipping_from > 0 && isset( $package['contents_cost'] ) && floatval( $package['contents_cost'] ) >= $free_shipping_from ) {
			$is_free        = TRUE;
		}

		$args = array(
			'id' 	  => $this->get_rate_id(),
			'label'   => $this->title,
			'cost' 	  => $is_free ? 0 : $this->shipping_price,
		);

		if( $this->tax_status == 'none' ) {
			$args['taxes'] = FALSE;
		}

		$this->add_rate( $args );
	}

	/**
	 * Get order shipping country
	 *
	 * @return string Shipping country code
	 */
	function get_shipping_country() {
		$country     = FALSE;

		if( isset( $this->order_id ) && $this->order_id ) {
			$order   = wc_get_order( $this->order_id );
			$country = wc_esm_get_order_shipping_country( $order );
		}
		elseif( WC()->customer ) {
			$country = WC()->customer->get_shipping_country();
		}

		if( ! $country ) {
			$country = WC()->countries->get_base_country();
		}

		return $country;
	}

	/**
	 * Easier debugging
	 *
	 * @param  mixed $data Data to be saved
	 * @return void
	 */
	function debug( $data ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG === TRUE ) {
			$logger = new WC_Logger();
			$logger->add( $this->id, is_array( $data ) || is_object( $data ) ? print_r( $data, TRUE ) : var_export( $data, true ) );
		}
	}

	/**
	 * Validates user submitted phone number.
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
}
