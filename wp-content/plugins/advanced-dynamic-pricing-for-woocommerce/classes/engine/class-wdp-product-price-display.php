<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Price_Display {
	private $options;

	/**
	 * @var WDP_Cart
	 */
	private $cart;

	/**
	 * @var WDP_Cart_Calculator
	 */
	private $calc;

	/**
	 * @var WDP_Product[]
	 */
	private $cached_products = array();

	private $hooks_init = false;

	/**
	 * WDP_Price_Display constructor.
	 *
	 */
	public function __construct() {
		$this->options = WDP_Helpers::get_settings();
	}

	public function is_enabled() {
		return ! ( ( is_admin() && ! wp_doing_ajax() ) || WDP_Loader::is_request_to_rest_api() || defined( 'DOING_CRON' ) );
	}

	public function init_while_doing_cron_or_rest_api() {
		$this->apply_calc();
		$this->apply_empty_cart();
		$this->init_hooks();
	}

	public function get_option( $option, $default = false ) {
		return isset( $this->options[ $option ] ) ? $this->options[ $option ] : $default;
	}

	public function init_hooks() {
		$this->hooks_init = true;
		$this->restore_hooks();
	}

	public function restore_hooks() {
		if ( ! $this->hooks_init ) {
			return;
		}

		// for prices in catalog and single product mode
		add_filter( 'woocommerce_get_price_html', array( $this, 'hook_get_price_html' ), 10, 2 );
//		add_filter( 'woocommerce_variable_price_html', array( $this, 'hook_get_price_html' ), 100, 2 );

		if ( $this->get_option('show_onsale_badge') && ! $this->get_option('do_not_modify_price_at_product_page') ) {
			add_filter( 'woocommerce_product_is_on_sale', array( $this, 'hook_product_is_on_sale' ), 10, 2 );
			add_filter( 'woocommerce_product_get_sale_price', array( $this, 'hook_product_get_sale_price' ), 100, 2 );
			add_filter( 'woocommerce_product_get_regular_price', array( $this, 'hook_product_get_regular_price' ), 100, 2 );
		}

		if ( $this->get_option( 'show_cross_out_subtotal_in_cart_totals' ) ) {
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'hook_cart_subtotal' ), 10, 3 );
		}

		// strike prices for items
		if ( $this->get_option( 'show_striked_prices' ) ) {
			add_filter( 'woocommerce_cart_item_price', array( $this, 'woocommerce_cart_item_price_and_price_subtotal' ), 10, 3 );
			add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'woocommerce_cart_item_price_and_price_subtotal' ), 10, 3 );
		}

		if($this->get_option('use_first_range_as_min_qty')) {
			add_filter('woocommerce_quantity_input_args', array($this, 'woocommerce_item_page_qty'), 10, 2);
		}

		do_action( 'wdp_price_display_init_hooks', $this );
	}

	public function remove_price_hooks() {
		if ( ! $this->hooks_init ) {
			return;
		}

		remove_filter( 'woocommerce_get_price_html', array( $this, 'hook_get_price_html' ), 10 );
		remove_filter( 'woocommerce_product_is_on_sale', array( $this, 'hook_product_is_on_sale' ), 10 );
		remove_filter( 'woocommerce_product_get_sale_price', array( $this, 'hook_product_get_sale_price' ), 100 );
		remove_filter( 'woocommerce_product_get_regular_price', array( $this, 'hook_product_get_regular_price' ), 100 );

		do_action( 'wdp_price_display_remove_hooks', $this );
	}

	public function get_cached_products() {
		return $this->cached_products;
	}

	/**
	 * @param $cart_subtotal_html string
	 * @param $compound boolean
	 * @param $wc_cart WC_Cart
	 *
	 * @return string
	 */
	public function hook_cart_subtotal( $cart_subtotal_html, $compound, $wc_cart ) {
		if ( ! is_cart() ) {
			return $cart_subtotal_html;
		}

		if ( ! $compound ) {
			$cart_subtotal_suffix = '';

			$totals = $wc_cart->get_totals();

			if ( isset( $totals['wdp_initial_totals'] ) ) {
				$initial_cart_subtotal     = $totals['wdp_initial_totals']['subtotal'];
				$initial_cart_subtotal_tax = $totals['wdp_initial_totals']['subtotal_tax'];
			} else {
				return $cart_subtotal_html;
			}

			if ( $wc_cart->display_prices_including_tax() ) {
				$initial_cart_subtotal += $initial_cart_subtotal_tax;
				$cart_subtotal         = $wc_cart->get_subtotal() + $wc_cart->get_subtotal_tax();

				if ( $wc_cart->get_subtotal_tax() > 0 && ! wc_prices_include_tax() ) {
					$cart_subtotal_suffix = ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
				}
			} else {
				$cart_subtotal = $wc_cart->get_subtotal();

				if ( $wc_cart->get_subtotal_tax() > 0 && wc_prices_include_tax() ) {
					$cart_subtotal_suffix = ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
				}
			}

			$initial_cart_subtotal = apply_filters( 'wdp_initial_cart_subtotal', $initial_cart_subtotal, $wc_cart );
			$cart_subtotal         = apply_filters( 'wdp_cart_subtotal', $cart_subtotal, $wc_cart );

			if ( $cart_subtotal < $initial_cart_subtotal ) {
				$cart_subtotal_html = wc_format_sale_price( $initial_cart_subtotal, $cart_subtotal ) . $cart_subtotal_suffix;
			}
		}

		return $cart_subtotal_html;
	}

	/**
	 * Hook for create calculator and cart for frontend price calculation.
	 * We must do it as late as possible in wp_loaded hook for including (e.g.) items which added during POST.
	 */
	public function apply_cart_and_calc() {
		$this->apply_calc();
		$this->apply_cart();
	}

	public function apply_calc() {
		$rule_collection = WDP_Rules_Registry::get_instance()->get_active_rules();
		$this->calc      = WDP_Loader::get_cart_calculator_class( $rule_collection );
	}

	public function apply_cart( $context = 'view' ) {
		if ( ! did_action( 'wp_loaded' ) ) {
			wc_doing_it_wrong( __FUNCTION__, __( 'Apply cart and calc should not be called before the wp_loaded action for including (e.g.) items which added during POST.', 'advanced-dynamic-pricing-for-woocommerce' ), '1.6.0' );
		}

		$cart_context = WDP_Frontend::make_wdp_cart_context_from_wc();
		$this->cart   = new WDP_Cart( $cart_context, WC()->cart );

		if ( 'view' === $context ) {
			$this->cart = apply_filters( 'wdp_apply_cart_to_price_display', $this->cart );
		}

		if ( $this->is_enabled() ) {
			$this->remove_price_hooks();
			$this->init_hooks();
		}
	}

	public function apply_empty_cart( $context = 'view' ) {
		$cart_context = WDP_Frontend::make_wdp_cart_context_from_wc();
		$this->cart   = new WDP_Cart( $cart_context );
		if ( 'view' === $context ) {
			$this->cart = apply_filters( 'wdp_apply_empty_cart_to_price_display', $this->cart );
		}
	}

	/**
	 * @param $wdp_cart_calc WDP_Cart_Calculator
	 */
	public function attach_calc( $wdp_cart_calc ) {
		$this->calc   = $wdp_cart_calc;
	}

	/**
	 * @param $price_html string
	 * @param $product WC_Product
	 *
	 * @return string
	 */
	public function hook_get_price_html( $price_html, $product ) {
		$formatter  = WDP_Loader::create_formatter();
		/**
		 * @var WDP_Price_Formatter $formatter
		 */

		if ( apply_filters( "wdp_use_hooked_price_html_without_pricing", true ) && ( ! class_exists( "WC_Product_Subscription" ) || ! ( $product instanceof WC_Product_Subscription ) ) ) {
			$price_html = $this->get_product_price_html_without_pricing( $product );
		}

		if ( ! $formatter->are_modifications_needed( $product ) ) {
			return $price_html;
		}

		if ( ! ( $product instanceof WC_Product ) ) {
			return $price_html;
		}

		$qty = floatval( 1 );
 		//only if bulk rule must override QTY input
		if ( $this->get_option( 'use_first_range_as_min_qty' ) ) {
			$args = $this->woocommerce_item_page_qty( array(), $product );
			if ( isset( $args['input_value'] ) ) {
				$qty = (float) $args['input_value'];
			}
		}

		$wdp_product = $this->process_product( $product, $qty );

		if ( is_null( $wdp_product ) ) {
			return $price_html;
		}

		$wdp_product = apply_filters( 'wdp_get_price_html_from_wdp_product', $wdp_product, $this );
		if ( ! ( $wdp_product instanceof WDP_Product ) ) {
			return $formatter->format_price( $price_html, $wdp_product );
		}

		if ( $formatter->is_replacement_with_bulk_price_needed( $wdp_product ) ) {
			return $formatter->format_bulk_price( $wdp_product );
		}

		if ( ! $wdp_product->are_rules_applied() ) {
			return apply_filters( 'wdp_price_display_html', $formatter->format_price( $price_html, $wdp_product ), $wdp_product );
		}

		$this->remove_price_hooks();
		if ( $product_price_html = $formatter->format_wdp_product_price( $wdp_product ) ) {
			$price_html = $product_price_html;
		}
		$this->init_hooks();

		return apply_filters( 'wdp_price_display_html', $formatter->format_price( $price_html, $wdp_product ), $wdp_product );
	}

	/**
	 * @param WC_Product|WDP_Product $product
	 * @param float|int $qty
	 *
	 * @return string
	 */
	public function get_product_price_html_without_pricing( $product, $qty = 1 ) {
		$html = null;

		if ( $product instanceof WC_Product || $product instanceof WDP_Product ) {
			$this->remove_price_hooks();
			if ( $product instanceof WDP_Product ) {
				$html = $product->get_price_html( $qty );
			} else {
				$html = $product->get_price_html();
			}
			$this->restore_hooks();
		}

		return $html;
	}

	/**
	 * @param $the_product WC_Product|int|WDP_Product
	 * @param $qty int
	 *
	 * @return WDP_Product|null
	 */
	public function process_product( $the_product, $qty = 1 ) {
		if ( is_null( $this->calc ) ) {
			global $wp;
			$logger = wc_get_logger(); // >Woocommerce>Status>Logs , file "log-2019-06-24-xxxx"
			$logger->error( sprintf( 'Calling null calc at %s', home_url( $wp->request ) ) );

			return null;
		}

		if ( ! $this->calc->at_least_one_rule_active() ) {
			return null;
		}

		if ( is_numeric( $the_product ) ) {
			$product_id = $the_product;
		} elseif ( $the_product instanceof WC_Product || $the_product instanceof WDP_Product ) {
			$product_id = $the_product->get_id();
		} else {
			$product_id = null;
		}

		$wdp_product = WDP_Object_Cache::get_instance()->get_wdp_product( $this, $product_id, $qty );
		if ( ! $wdp_product ) {
			if ( $the_product instanceof WDP_Product ) {
				$wdp_product = $the_product;
			} else {
				try {
					$wdp_product = new WDP_Product( $the_product );
				} catch ( Exception $e ) {
					return null;
				}
			}

			$wdp_product = $this->calculate_product( $wdp_product, $qty );
			$wdp_product = WDP_Object_Cache::get_instance()->get_wdp_product( $this, $wdp_product, $qty );
		}

//		if ( ! isset( $this->cached_products[ $product_id ][ $qty ] ) ) {
//			if ( ! isset( $this->cached_products[ $product_id ] ) ) {
//				$this->cached_products[ $product_id ] = array();
//			}
//
//			if ( $the_product instanceof WDP_Product ) {
//				$wdp_product = $the_product;
//			} else {
//				try {
//					$wdp_product = new WDP_Product( $the_product );
//				} catch ( Exception $e ) {
//					return null;
//				}
//			}
//
//			$wdp_product                                  = $this->calculate_product( $wdp_product, $qty );
//			$this->cached_products[ $product_id ][ $qty ] = $wdp_product;
//		} else {
//			$wdp_product = $this->cached_products[ $product_id ][ $qty ];
//		}

		return $wdp_product;
	}

	/**
	 * @param $product WDP_Product
	 * @param $qty int
	 *
	 * @return null|WDP_Product
	 */
	private function calculate_product( &$product, $qty = 1 ) {
		if ( ! $this->cart ) {
			$this->apply_cart();
		}

		$cart = clone $this->cart;
		
		$has_children = $product->is_variable() || $product->is_grouped() ;

		if ( $has_children && $product->get_children() ) {
			foreach ( $product->get_children() as $child_id ) {
				try {
					$child = new WDP_Product( $child_id, $product->get_wc_product() );
				} catch ( Exception $e ) {
					continue;
				}

				if ( ! $child->is_price_defined() ) {
					continue;
				}

				$child = $this->process_product( $child, $qty );
				if ( is_null( $child ) ) {
					return null;
				}

				$product->update_children_summary( $child );
			}
		} elseif ( ! $has_children  ) {
			$this->remove_price_hooks();
			$new_cart = $this->calc->process_cart_with_product( $cart, $product, $qty );
			$this->restore_hooks();

			if ( $new_cart ) {
				$product  = $this->calc->apply_changes_to_product( $new_cart, $product, $qty );
				$product->update_prices( $new_cart->get_context() );
				$product = $this->prepare_product_to_display( $product, $new_cart->get_context() );
			} else {
				$product = $this->prepare_product_to_display( $product, $cart->get_context() );
			}
		}

		return $product;
	}

	/**
	 * @param $product WDP_Product
	 * @param $context WDP_Cart_Context
	 *
	 * @return WDP_Product
	 */
	public function prepare_product_to_display($product, $context) {
		$initial_num_decimals = wc_get_price_decimals();
		$set_price_decimals = function ( $num_decimals ) use ( $initial_num_decimals ) {
			return $initial_num_decimals + 1;
		};
		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			add_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		$product->set_price( $this->get_price_to_display( $product->get_wc_product(), $product->get_price(), $context ) );
		$product->set_new_price( $this->get_price_to_display( $product->get_wc_product(), $product->get_new_price(), $context ) );

		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			remove_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		return $product;
	}

	/**
	 * Copied from wc_get_price_to_display()
	 *
	 * @param WC_Product $product WC_Product object.
	 * @param string|float     $price
	 * @param WDP_Cart_Context $context
	 *
	 * @return float
	 * @see wc_get_price_to_display()
	 *
	 */
	private function get_price_to_display( $product, $price, $context ) {
		$qty   = 1;

		if ( 'incl' === $context->get_tax_display_shop() ) {
			$result = wc_get_price_including_tax( $product, array(
				'qty'   => $qty,
				'price' => $price,
			) );
		} else {
			$result = wc_get_price_excluding_tax( $product, array(
				'qty'   => $qty,
				'price' => $price,
			) );
		}

		return $result;
	}

	/**
	 * @param $on_sale boolean
	 * @param $product WC_Product
	 *
	 * @return boolean
	 */
	public function hook_product_is_on_sale( $on_sale, $product ) {
		$wdp_product = $this->process_product( $product );
		if ( is_null( $wdp_product ) ) {
			return $on_sale;
		}

		return $on_sale || ( $wdp_product->are_rules_applied() && $wdp_product->are_discount_applied() );
	}

	/**
	 * @param $value string
	 * @param $product WC_Product
	 *
	 * @return string|float
	 */
	public function hook_product_get_sale_price( $value, $product ) {
		$wdp_product = $this->process_product( $product );
		if ( is_null( $wdp_product ) ) {
			return $value;
		}

		return $wdp_product->are_rules_applied() ? $wdp_product->get_new_price() : $value;
	}

	public function hook_product_get_regular_price( $value, $product ) {
		$wdp_product = $this->process_product( $product );
		if ( is_null( $wdp_product ) ) {
			return $value;
		}

		return $wdp_product->are_rules_applied() ? $wdp_product->get_price() : $value;
	}

	public function is_request_to_rest_api() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );

		// Check if our endpoint.
		$woocommerce = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix . 'wc/' ) ); // @codingStandardsIgnoreLine

		// Allow third party plugins use our authentication methods.
		$third_party = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix . 'wc-' ) ); // @codingStandardsIgnoreLine

		return apply_filters( 'woocommerce_rest_is_request_to_rest_api', $woocommerce || $third_party );
	}

	/**
	 * @param WC_Product $product
	 * @param array      $args
	 *
	 * @return string
	 */
	public function get_cart_item_price_to_display( $product, $args = array() ) {
		if ( ! $this->cart ) {
			$this->apply_cart();
		}

		$args = wp_parse_args( $args, array(
			'qty'   => 1,
			'price' => $product->get_price(),
		) );

		$price = $args['price'];
		$qty   = $args['qty'];

		$context = $this->cart->get_context();

		$initial_num_decimals = wc_get_price_decimals();
		$set_price_decimals = function ( $num_decimals ) use ( $initial_num_decimals ) {
			return $initial_num_decimals + 1;
		};

		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			add_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
			$new_price = wc_get_price_including_tax( $product, array( 'qty' => $qty, 'price' => $price ) );
		} else {
			$new_price = wc_get_price_excluding_tax( $product, array( 'qty' => $qty, 'price' => $price ) );
		}

		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			remove_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		return $new_price;
	}

	/**
	 * @param string $price formatted price after wc_price()
	 * @param array $cart_item
	 * @param string $cart_item_key
	 *
	 * @return string
	 */
	public function woocommerce_cart_item_price_and_price_subtotal( $price, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['wdp_original_price'] ) ) {
			return $price;
		}

		$new_price_html = $price;
		$quantity       = $cart_item['quantity'];
		$product        = $cart_item['data'];
		/**
		 * @var $product WC_Product
		 */

		$initial_data = isset($cart_item[WDP_Cart::INITIAL_DATA_KEY]) ? $cart_item[WDP_Cart::INITIAL_DATA_KEY] : array();

		if ( $initial_data && isset( $initial_data['subtotal'], $initial_data['subtotal_tax'] ) ) {
			if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
				$old_price = $initial_data['subtotal'] + $initial_data['subtotal_tax'];
			} else {
				$old_price = $initial_data['subtotal'];
			}
		} else {
			$old_price = (float) $cart_item['wdp_original_price'];
		}

		$new_price = apply_filters( 'wdp_cart_item_new_price', (float) $product->get_price( 'edit' ), $cart_item, $cart_item_key );

		if ( 'woocommerce_cart_item_subtotal' == current_filter() ) {
			$new_price = $this->get_cart_item_price_to_display( $product, array( 'price' => (float) $new_price, 'qty' => $quantity ) );
			$old_price *= $quantity;
		} else {
			$new_price = $this->get_cart_item_price_to_display( $product, array( 'price' => (float) $new_price ) );
		}

		$new_price = apply_filters( 'wdp_cart_item_subtotal', $new_price, $cart_item, $cart_item_key );
		$old_price = apply_filters( 'wdp_cart_item_initial_subtotal', $old_price, $cart_item, $cart_item_key );

		if ( $new_price !== false && $old_price !== false ) {
			$old_price_rounded = round( $old_price, wc_get_price_decimals() );
			$new_price_rounded = round( $new_price, wc_get_price_decimals() );

			if ( $new_price_rounded < $old_price_rounded ) {
				$price_html = wc_format_sale_price( $old_price, $new_price );
			} else {
				$price_html = $new_price_html;
			}
		} else {
			$price_html = $new_price_html;
		}

		return $price_html;
	}

	public function woocommerce_item_page_qty($args, $product) {
		if(!is_product()) {
			return $args;
		}

		$available_product_ids = array_merge( array( $product->get_id() ), $product->get_children() );

		$bulk_details = null;
		foreach ( $available_product_ids as $product_id ) {
			$matched_rules = $this->get_calculator()->find_product_matches( $this->get_cart(), $product->get_id() );
			if ( $matched_rules->is_empty() ) {
				continue;
			}

			$bulk_rules = $matched_rules->with_bulk();
			if ( $bulk_rules->is_empty() ) {
				continue;
			}

			foreach ( $bulk_rules->to_array() as $rule ) {
				/**
				 * @var WDP_Rule $rule
				 */
				if ( $rule->get_bulk_details( $this->get_cart()->get_context() ) ) {
					$bulk_details = $rule->get_bulk_details( $this->get_cart()->get_context() );
					break;
				}
			}

			if ( $bulk_details ) {
				break;
			}
		}
		if( $bulk_details ) {
			$args['input_value'] = $bulk_details['ranges'][0]['from']; // Start from this value (default = 1) 
		  	$args['min_value'] = $bulk_details['ranges'][0]['from']; // Min quantity (default = 0)
		}
		return $args;
	}

	public function get_calculator() {
		return $this->calc;
	}

	public function get_cart() {
		return $this->cart;
	}

	public function hash() {
		if ( ! $this->cart ) {
			$this->apply_cart();
		}

		return md5( json_encode( array(
			$this->calc->hash(),
			$this->cart->hash()
		) ) );
	}

}