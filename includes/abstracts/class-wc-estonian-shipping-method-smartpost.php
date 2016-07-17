<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Smartpost shipping method
 *
 * @class     WC_Estonian_Shipping_Method_Smartpost
 * @extends   WC_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
abstract class WC_Estonian_Shipping_Method_Smartpost extends WC_Estonian_Shipping_Method {

	/**
	 * Holds terminals once fetched
	 *
	 * @var mixed
	 */
	public $terminals = FALSE;

	/**
	 * Terminals meta and input field name
	 *
	 * @var string
	 */
	public $field_name;

	/**
	 * Formatting option for terminal name
	 *
	 * @var string
	 */
	public $name_format = FALSE;

	/**
	 * Current order ID that is being showed
	 *
	 * @var integer
	 */
	public $order_id = FALSE;

	/**
	 * Just a tracker to understand whether terminals have already been fetched
	 *
	 * @var boolean
	 */
	public $terminals_fetched = FALSE;

	/**
	 * API URL
	 *
	 * @var string
	 */
	public $api_url = 'http://iseteenindus.smartpost.ee/api/';

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Add terminal selection dropdown and save it
		add_action( 'woocommerce_review_order_after_shipping',                 array( $this, 'review_order_after_shipping' ) );
		add_action( 'woocommerce_checkout_update_order_meta',                  array( $this, 'checkout_save_order_terminal_id_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review',                array( $this, 'checkout_save_session_terminal_id' ), 10, 1 );

		// Show selected terminal in order and emails
		add_action( 'woocommerce_order_details_after_customer_details',        array( $this, 'show_selected_terminal' ), 10, 1 );
		add_action( 'woocommerce_email_order_meta',                            array( $this, 'show_selected_terminal' ), 10, 1 );

		// Checkout validation
		add_action( 'woocommerce_after_checkout_validation',                   array( $this, 'validate_user_selected_terminal' ), 10, 1 );

		// Show selected terminal in admin order review
		if( is_admin() ) {
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_selected_terminal' ), 20 );
		}

		// Meta and input field name
		$this->field_name = apply_filters( 'wc_shipping_smartpost_terminals_field_name', 'wc_shipping_smartpost_terminal' );

		parent::__construct();
	}

	function is_available( $package = array() ) {
		return parent::is_available( $package ) && ( ! isset( $this->country ) || ( isset( $this->country ) && isset( $package['destination'] ) && isset( $package['destination']['country'] ) && $package['destination']['country'] == $this->country ) );
	}

	/**
	 * Adds dropdown selection of terminals right after shipping in checkout
	 * @return void
	 */
	function review_order_after_shipping() {
		// Get currently selected shipping methods
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		// Check if ours is one of the selected methods
		if( ! empty( $chosen_shipping_methods ) && in_array( $this->id, $chosen_shipping_methods ) ) {
			// Get selected terminal
			$selected_terminal   = WC()->session->get( $this->field_name );

			// Set data for terminals template
			$template_data = array(
				'terminals'  => $this->get_sorted_and_grouped_terminals(),
				'field_name' => $this->field_name,
				'field_id'   => $this->field_name,
				'selected'   => $selected_terminal ? $selected_terminal : ''
			);

			// Allow to do some activity before terminals
			do_action( $this->id . '_before_terminals' );

			// Get terminals template
			wc_get_template( 'checkout/form-shipping-smartpost.php', $template_data );

			// Allow to do some activity after terminals
			do_action( $this->id . '_after_terminals' );
		}
	}

	/**
	 * Get user preferred terminal grouping option
	 *
	 * @return string Grouping option
	 */
	function get_grouping_option() {
		return apply_filters( 'wc_shipping_smartpost_terminal_grouping', $this->get_option( 'group_terminals', 'cities' ) );
	}

	/**
	 * Get user preferred terminal sorting option
	 *
	 * @return string Sorting option
	 */
	function get_sorting_option() {
		return apply_filters( 'wc_shipping_smartpost_terminal_sorting', $this->get_option( 'sort_terminals', 'alphabetically' ) );
	}

	/**
	 * Get user preferred terminal name format option
	 *
	 * @return string Formatting option
	 */
	function get_name_formatting_option() {
		if( ! $this->name_format )
			$this->name_format = apply_filters( 'wc_shipping_smartpost_terminal_format', $this->get_option( 'terminals_format', 'name' ) );

		return $this->name_format;
	}

	/**
	 * Sorts and groups all terminals as user prefers
	 *
	 * @return array Sorted and grouped terminals
	 */
	function get_sorted_and_grouped_terminals() {
		$sorted_terminals  = $this->get_sorted_terminals();
		$grouped_terminals = $this->get_grouped_terminals( $sorted_terminals );

		// If everything needed to be sorted alphabetically, do so
		if( $this->get_sorting_option() == 'alphabetically' ) {
			ksort( $grouped_terminals );
		}

		// Format name
		foreach( $grouped_terminals as $group => $terminals ) {
			foreach( $terminals as $terminal_key => $terminal ) {
				$grouped_terminals[ $group ][ $terminal_key ]->name = $this->get_formatted_terminal_name( $terminal );
			}
		}

		return $grouped_terminals;
	}

	/**
	 * Formats terminal name
	 *
	 * @param  object $terminal Terminal
	 * @return string           Terminal name
	 */
	function get_formatted_terminal_name( $terminal ) {
		$name = $terminal->name;

		if( $this->get_name_formatting_option() == 'with_address' ) {
			$name .= ' ('. $terminal->address .')';
		}

		return apply_filters( 'wc_shipping_smartpost_terminal_name', $name, $terminal->name, $terminal->city, $terminal->address );
	}

	/**
	 * Groups all terminals as user prefers
	 *
	 * @param  mixed $terminals Terminals (false = will fetch)
	 * @return array            Grouped terminals
	 */
	function get_grouped_terminals( $terminals = false ) {
		$group_by          = $this->get_grouping_option();
		$terminals         = $terminals ? $terminals : $this->get_terminals();
		$grouped_terminals = array();

		switch( $group_by ) {
			// By default, group by cities
			default:
			case 'cities':
				// Go through terminals
				foreach( $terminals as $terminal ) {
					// Allow manipulating city name
					$city_name = apply_filters( 'wc_shipping_smartpost_city_name', $terminal->city );

					if( ! isset( $grouped_terminals[ $city_name ] ) )
						$grouped_terminals[ $city_name ] = array();

					$grouped_terminals[ $city_name ][] = $terminal;
				}
			break;

			// Group by counties
			case 'counties':
				// Go through terminals
				foreach( $terminals as $terminal ) {
					// Replace Tallinn/Harjumaa with Harjumaa, because Tallinn is not a county,
					// also allow manipulating group names
					$group_name = apply_filters( 'wc_shipping_smartpost_group_name', str_ireplace( 'Tallinn/Harjumaa', 'Harjumaa', $terminal->group_name ), $terminal->group_name );

					if( ! isset( $grouped_terminals[ $group_name ] ) )
						$grouped_terminals[ $group_name ] = array();

					$grouped_terminals[ $group_name ][] = $terminal;
				}
			break;
		}

		return $grouped_terminals;
	}

	/**
	 * Sorts all terminals as user prefers
	 *
	 * @param  mixed $terminals Terminals (false = will fetch)
	 * @return array            Sorted terminals
	 */
	function get_sorted_terminals( $terminals = false ) {
		$sort_by          = $this->get_sorting_option();
		$terminals        = $terminals ? $terminals : $this->get_terminals();
		$sorted_terminals = $terminals;

		switch( $sort_by ) {
			// By default, sort by Itella's priority (bigger cities first)
			default:
			case 'cities_first':
				// Sort by group_sort attribute provided by Smartpost
				usort( $sorted_terminals, array( $this, 'terminals_group_sort' ) );
			break;

			// Alphabetically
			case 'alphabetically':
				usort( $sorted_terminals, array( $this, 'terminals_alphabetical_sort' ) );
			break;

			// No sorting
			case 'none':
				// Do nothing
			break;
		}

		return $sorted_terminals;
	}

	/**
	 * Fetches locations and stores them to cache.
	 *
	 * @return array Terminals
	 */
	function get_terminals() {
		// Shipping country
		$shipping_country        = $this->get_shipping_country();

		// Check if terminals are already loaded
		if( $this->terminals !== FALSE && $this->terminals_country == $shipping_country )
			return $this->terminals;

		// Save country
		$this->terminals_country = $shipping_country;

		// Get terminals transient/cache
		$transient_name          = strtolower( $this->id . '_terminals' );
		$terminals_transient     = get_transient( $transient_name );

		// Check if terminals transient exists
		if ( $terminals_transient && WP_DEBUG === FALSE ) {
			// Get terminals from transient
			$terminals       = $terminals_transient;
		}
		else {
			// Get all of the possible places
			$terminals_xml   = @file_get_contents( $this->get_terminals_url() );

			// XML to array
			$terminals       = $this->xml_to_array( $terminals_xml );

			// Check if shipping country if Finland
			if( $shipping_country == 'FI' ) {
				// We may need to merge terminals and post offices together
				if( $this->get_option( 'terminals_filter', 'all' ) == 'both' && $this->terminals_fetched === FALSE ) {
					$this->terminals_fetched = TRUE;
					$more_terminals          = $this->get_terminals();

					$terminals               = array_merge( $terminals, $more_terminals );
				}
			}

			// Set transient for cache
			set_transient( $transient_name, $terminals, 86400 );
		}

		// Set terminals locally
		$this->terminals     = $terminals;

		// Return terminals
		return apply_filters( 'wc_shipping_smartpost_terminals', $terminals, $shipping_country );
	}

	/**
	 * Reformat XML to array
	 *
	 * @param  string $data XML as string
	 * @return array        XML as array
	 */
	function xml_to_array( $data ) {
		$xml   = simplexml_load_string( $data );
		$array = array();

		foreach( $xml->item as $item ) {
			$array_row = array();

			foreach( $item as $key => $value ) {
				$array_row[ (string) $key ] = (string) $value;
			}

			$array[] = (object) $array_row;
		}

		return $array;
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
			if( intval( $terminal->place_id ) === intval( $place_id ) ) {
				return $this->get_formatted_terminal_name( $terminal );

				break;
			}
		}
	}

	/**
	 * Fetches terminal data
	 *
	 * @param  integer $place_id Place ID
	 * @return string            Place name
	 */
	function get_terminal_data( $place_id ) {
		$terminals = $this->get_terminals();

		foreach( $terminals as $terminal ) {
			if( $terminal->place_id == $place_id ) {
				return $terminal;
			}
		}

		return FALSE;
	}

	/**
	 * Get order shipping country
	 *
	 * @return string Shipping country code
	 */
	function get_shipping_country() {
		$country     = FALSE;

		if( $this->order_id ) {
			$order   = wc_get_order( $this->order_id );
			$country = $order->shipping_country;
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
	 * Sort terminals by group_sort attribute
	 *
	 * @see    usort()
	 *
	 * @param  object $a Terminal A
	 * @param  object $b Terminal B
	 *
	 * @return integer   0 = stay, 1 = up, -1 = down
	 */
	function terminals_group_sort( $a, $b ) {
		if( isset( $a->group_sort ) && isset( $b->group_sort ) ) {
			if( $a->group_sort == $b->group_sort ) {
				return 0;
			}
			else {
				return ( $a->group_sort > $b->group_sort ) ? -1 : 1;
			}
		}
		else {
			// Get city ordering number
			$a_number = $this->get_city_order_number( $a->city );
			$b_number = $this->get_city_order_number( $b->city );

			return ( $a_number == $b_number ) ? 0 : ( $a_number > $b_number ) ? -1 : 1;
		}
	}

	/**
	 * Get city ordering number
	 *
	 * @param  string $city City name
	 *
	 * @return integer      Ordering number
	 */
	private function get_city_order_number( $city ) {
		$cities_order = array(
			'Tallinn'      => 30,
			'Tartu'        => 29,
			'Narva'        => 28,
			'Pärnu'        => 27,
			'Kohtla Järve' => 26,
			'Kohtla-Järve' => 26,
			'Maardu'       => 25,
			'Viljandi'     => 24,
			'Rakvere'      => 23,
			'Sillamäe'     => 22,
			'Kuressaare'   => 21,
			'Võru'         => 20,
			'Valga'        => 19,
			'Jõhvi'        => 18,
			'Haapsalu'     => 17,
			'Keila'        => 16,
			'Paide'        => 15,
			'Türi'         => 14,
			'Tapa'         => 13,
			'Põlva'        => 12,
			'Kiviõli'      => 11,
			'Elva'         => 10,
			'Saue'         => 9,
			'Jõgeva'       => 8,
			'Rapla'        => 7,
			'Põltsamaa'    => 6,
			'Paldiski'     => 5,
			'Sindi'        => 4,
			'Kunda'        => 3,
			'Kärdla'       => 2,
			'Kehra'        => 1
		);

		if( isset( $cities_order[ $city ] ) ) {
			return $cities_order[ $city ];
		}

		return 0;
	}


	/**
	 * Sort terminals alphabetically by name
	 *
	 * @see    strcmp()
	 *
	 * @param  object $a Terminal A
	 * @param  object $b Terminal B
	 *
	 * @return integer   0 = stay/same, 1 = up, -1 = down
	 */
	function terminals_alphabetical_sort( $a, $b ) {
		return strcmp( $a->name, $b->name );
	}

	/**
	 * Saves selected terminal to order meta
	 *
	 * @param  integer $order_id Order ID
	 * @param  array   $posted   WooCommerce posted data
	 *
	 * @return void
	 */
	function checkout_save_order_terminal_id_meta( $order_id, $posted ) {
		if( isset( $_POST[ $this->field_name ] ) ) {
			update_post_meta( $order_id, $this->field_name, $_POST[ $this->field_name ] );
		}
	}

	/**
	 * Saves selected terminal in session whilst order review updates
	 *
	 * @param  string $posted Posted data
	 *
	 * @return void
	 */
	function checkout_save_session_terminal_id( $post_data ) {
		parse_str( $post_data, $posted );

		if( isset( $posted[ $this->field_name ] ) ) {
			WC()->session->set( $this->field_name, $posted[ $this->field_name ] );
		}
	}

	/**
	 * Outputs user selected Smartpost terminal in different locations (admin screen, email, orders)
	 *
	 * @param  mixed $order Order (ID or WC_Order)
	 * @return void
	 */
	function show_selected_terminal( $order ) {
		// Create order instance if needed
		if( is_int( $order ) )
			$order         = wc_get_order( $order );

		// Store order ID
		$this->order_id    = $order->id;

		// Check if the order has our shipping method (or something similar for compability)
		if( $order->has_shipping_method( $this->id ) || $order->has_shipping_method( 'itella_smartpost_estonia' ) || $order->has_shipping_method( 'itella_smartpost' ) || $order->has_shipping_method( 'smartpost' ) || $order->has_shipping_method( 'itella_smartpost_finland' ) ) {
			// Fetch selected terminal ID
			$terminal_id   = $this->get_order_terminal( $order->id );
			$terminal_name = $this->get_terminal_name( $terminal_id );

			// Output selected terminal to user customer details
			if( current_filter() == 'woocommerce_order_details_after_customer_details' ) {
				if( version_compare( WC_VERSION, '2.3.0', '<' ) ) {
					$terminal  = '<dt>' . __( 'Chosen Smartpost terminal:', 'wc-estonian-shipping-methods' ) . '</dt>';
					$terminal .= '<dd>' . $terminal_name . '</dd>';
				}
				else {
					$terminal  = '<tr>';
					$terminal .= '<th>' . __( 'Chosen Smartpost terminal', 'wc-estonian-shipping-methods' ) . ':</th>';
					$terminal .= '<td data-title="' . __( 'Chosen Smartpost terminal', 'wc-estonian-shipping-methods' ) . '">' . $terminal_name . '</td>';
					$terminal .= '</tr>';
				}
			}
			// Output selected terminal to everywhere else
			else {
				$terminal  = '<div class="selected_terminal">';
				$terminal .= '<div><strong>' . __( 'Smartpost terminal:', 'wc-estonian-shipping-methods' ) . '</strong></div>';
				$terminal .= $terminal_name;
				$terminal .= '</div>';
			}

			// Allow manipulating output
			echo apply_filters( 'wc_shipping_smartpost_selected_terminal', $terminal, $terminal_id, $terminal_name, current_filter() );
		}
	}

	/**
	 * Get selected terminal ID from order meta
	 * @param  integer $order_id Order ID
	 * @return integer           Selected terminal ID
	 */
	function get_order_terminal( $order_id ) {
		return (int) get_post_meta( $order_id, $this->field_name, true );
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
}