<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Post office shipping method
 *
 * @class     WC_Estonian_Shipping_Method_Post
 * @extends   WC_Shipping_Method
 * @category  Shipping Methods
 * @package   Estonian_Shipping_Methods_For_WooCommerce
 */
abstract class WC_Estonian_Shipping_Method_Post extends WC_Estonian_Shipping_Method_Terminals
{
    /**
     * Where to fetch the file from
     *
     * @var string
     */
    public $terminals_file = WC_ESTONIAN_SHIPPING_METHODS_INCLUDES_PATH . "/files/post.json";

    /**
     * Which variable in the locations will contain address value
     *
     * @var string
     */
    public $variable_address = 'ADDRESS';

    /**
     * Which variable in the locations will contain address value
     *
     * @var string
     */
    public $variable_city = 'CITY';

    /**
     * Class constructor
     */
    public function __construct() {
        $this->terminals_template = 'post';

        // Construct parent
        parent::__construct();
    }

    /**
     * Fetches locations and stores them to cache.
     *
     * @return array Terminals
     */
    public function get_terminals( $filter_country = false, $filter_type = 0 ) {
        // Fetch terminals from cache
        $terminals_cache = $this->get_terminals_cache();

        if( $terminals_cache !== null ) {
            return $terminals_cache;
        }

        $locations          = array();

        if( file_exists($this->terminals_file) ) {

            $terminals_json  = json_decode(file_get_contents($this->terminals_file));
            $filter_country  = $filter_country ? $filter_country : $this->get_shipping_country();

            foreach( $terminals_json as $key => $location ) {
                if( $location->COUNTRY == $filter_country) {
                    $locations[] = (object) array(
                        'place_id'   => $location->ZIP,
                        'zipcode'    => $location->ZIP,
                        'name'       => $location->NAME,
                        'address'    => $location->{ $this->variable_address } . ", " . $location->ZIP,
                        'city'       => $location->{ $this->variable_city },
                    );
                }
            }
        }

        // Save cache
        $this->save_terminals_cache( $locations );

        return $locations;
    }

    /**
     * Check if shipping is available
     *
     * @param  array $package
     * @return bool
     */
    function is_available( $package = array() ) {
        return parent::is_available( $package ) && ( ! isset( $this->country ) || ( isset( $this->country ) && isset( $package['destination'] ) && isset( $package['destination']['country'] ) && $package['destination']['country'] == $this->country ) );
    }
}
