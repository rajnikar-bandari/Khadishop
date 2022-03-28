<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Product {
	/**
	 * @var WC_Product
	 */
	private $wc_product;

	/**
	 * @var WC_Product|null
	 */
	private $wc_product_parent;

	private $data = array(
		'initial_price'            => '',
		'initial_wc_price'         => '',
		'initial_regular_price'    => '',
		'initial_sale_price'       => '',
		'initial_is_on_sale'       => false,
		'calculated_price'         => '',
		'rule_applied'             => false,
		'discount_applied'         => false,
		'min_price'                => - 1,
		'max_price'                => - 1,
		'adjustments_amount'       => 0,
		'history'                  => array(),
		'affected_by_bulk'         => false,
		'min_bulk_price'           => - 1,
		'is_use_custom_init_price' => false,
		'index_number'             => null,
		'qty_already_in_cart'      => null,
	);

	private $children_summary = array();

	/**
	 * WDP_Product constructor.
	 * @throws Exception
	 *
	 * @param $the_product int|WC_Product
	 * @param $parent null|WC_Product
	 */
	public function __construct( $the_product, $parent = null ) {
		if ( is_numeric( $parent ) ) {
			$this->wc_product_parent = WDP_Object_Cache::get_instance()->get_wc_product( $parent );
		} elseif ( $parent instanceof WC_Product ) {
			$this->wc_product_parent = $parent;
			WDP_Object_Cache::get_instance()->load_variation_post_meta( $this->wc_product_parent->get_id() );
		}

		if ( is_numeric( $the_product ) ) {
			if ( $this->wc_product_parent && $this->wc_product_parent->is_type( 'variable' ) ) {
				add_filter( 'woocommerce_product-variation_data_store', array( $this, 'apply_data_store' ), 10 );
				add_filter( 'woocommerce_product_type_query', array( $this, 'override_product_type_query' ), 10, 2 );
				$wc_product = WDP_Object_Cache::get_instance()->get_wc_product( $the_product );
				if ( ! $wc_product ) {
					throw new Exception( __( 'Product does not exists', 'advanced-dynamic-pricing-for-woocommerce' ) );
				}
				remove_filter( 'woocommerce_product_type_query', array( $this, 'override_product_type_query' ), 10 );
				remove_filter( 'woocommerce_product-variation_data_store', array( $this, 'apply_data_store' ), 10 );
			} else {
				$wc_product = WC()->product_factory->get_product( $the_product );
			}
		} elseif ( $the_product instanceof WC_Product ) {
			$wc_product = $the_product;
		} else {
			$wc_product = null;
		}

		$wc_product = WDP_Object_Cache::get_instance()->get_wc_product( $wc_product );
		$this->wc_product = apply_filters( 'wdp_wc_product', $wc_product, $this );

		$this->set_prop( 'initial_wc_price', $this->wc_product->get_price( 'nofilter' ) );
	}

	/**
	 * We do not need to get product type if the parent product is known
	 *
	 * @param boolean|string $override
	 * @param integer $product_id
	 *
	 * @return string
	 */
	public function override_product_type_query( $override, $product_id ) {
		return 'variation';
	}

	public function apply_data_store() {
		$data_store = new WDP_Product_Variation_Data_Store_CPT();
		if ( ! is_null( $this->wc_product_parent ) ) {
			$data_store->add_parent( $this->wc_product_parent );
		}

		return $data_store;
	}

	public function get_id() {
		return $this->wc_product->get_id();
	}

	public function get_children() {
		return $this->wc_product->get_children();
	}

	public function get_price() {
		return $this->get_prop( 'initial_price' ) !== '' ? (float) $this->get_prop( 'initial_price' ) : (float) $this->get_prop( 'initial_wc_price' );
	}

	public function get_new_price() {
		if ( '' !== $this->get_prop( 'calculated_price' ) ) {
			$price = (float) $this->get_prop( 'calculated_price' );
		} else {
			if ( $this->get_prop( 'initial_is_on_sale' ) ) {
				$price = $this->get_prop( 'initial_sale_price' );
			} else {
				$price = $this->get_price();
			}
		}

		return $price;
	}

	public function are_all_children_having_save_new_price() {
		return '' !== $this->get_prop( 'calculated_price' );
	}

	public function get_min_price() {
		return (float) $this->get_prop( 'min_price' );
	}

	public function get_max_price() {
		return (float) $this->get_prop( 'max_price' );
	}

	public function are_rules_applied() {
		return (boolean) $this->get_prop( 'rule_applied' );
	}

	public function are_discount_applied() {
		return (boolean) $this->get_prop( 'discount_applied' );
	}

	public function get_adjustments_amount() {
		return (float) $this->get_prop( 'adjustments_amount' );
	}

	public function set_price( $value ) {
		$this->set_prop( 'initial_price', (float) $value );
	}

	public function set_new_price( $value ) {
		$this->set_prop( 'calculated_price', (float) $value );
	}

	public function set_min_price( $value ) {
		$this->set_prop( 'min_price', (float) $value );
	}

	public function set_max_price( $value ) {
		$this->set_prop( 'max_price', (float) $value );
	}

	public function set_index_number( $value ) {
		$this->set_prop( 'index_number', (int) $value );
	}

	public function set_qty_already_in_cart( $value ) {
		$this->set_prop( 'qty_already_in_cart', (float) $value );
	}

	public function get_index_number() {
		return $this->get_prop( 'index_number' );
	}

	public function get_qty_already_in_cart() {
		return $this->get_prop( 'qty_already_in_cart' );
	}

	public function rules_applied() {
		$this->set_prop( 'rule_applied', true );
	}

	public function discount_applied() {
		$this->set_prop( 'discount_applied', true );
	}

	public function apply_history( $history ) {
		$this->set_prop( 'history', $history );
	}

	public function get_history() {
		return $this->get_prop( 'history' );
	}

	public function is_on_wdp_sale() {
		return $this->get_new_price() < $this->get_price();
	}

	/**
	 * @return WC_Product
	 */
	public function get_wc_product() {
		return $this->wc_product;
	}

	public function is_variable() {
		return $this->wc_product->is_type( 'variable' );
	}
	
	public function is_grouped() {
		return $this->wc_product->is_type( 'grouped' );
	}

	public function is_price_defined() {
		return "" !== $this->wc_product->get_price();
	}

	public function get_price_suffix($price = '', $qty = 1){
		return $this->wc_product->get_price_suffix($price, $qty);
	}

	public function affected_by_bulk() {
		$this->set_prop( 'affected_by_bulk', true );
	}

	public function is_affected_by_bulk() {
		return $this->get_prop( 'affected_by_bulk' );
	}

	public function set_min_bulk_price( $price ) {
		$this->set_prop( 'min_bulk_price', $price );
	}

	public function get_min_bulk_price() {
		return $this->get_prop( 'min_bulk_price' );
	}

	public function use_custom_initial_price() {
		$this->set_prop( 'is_use_custom_init_price', true );
	}

	public function is_use_custom_initial_price() {
		return $this->get_prop( 'is_use_custom_init_price' );
	}

	public function get_child_initial_price_for_min_price() {
		return isset( $this->children_summary['min']['initial_price'] ) ? $this->children_summary['min']['initial_price'] : false;
	}

	public function get_child_initial_price_for_max_price() {
		return isset( $this->children_summary['max']['initial_price'] ) ? $this->children_summary['max']['initial_price'] : false;
	}

	public function get_child_initial_price_for_min_bulk_price() {
		return isset( $this->children_summary['min_bulk']['initial_price'] ) ? $this->children_summary['min_bulk']['initial_price'] : false;
	}

	public function are_all_children_affected_by_bulk() {
		return isset( $this->children_summary['all_children_affected_by_bulk'] ) ? $this->children_summary['all_children_affected_by_bulk'] : false;
	}

	/**
	 * @return array
	 */
	public function get_children_summary() {
		return $this->children_summary;
	}

	/**
	 * Method for currency switcher
	 * Convert prices in summary
	 *
	 * @param array
	 */
	public function set_children_summary( $summary ) {
		$this->children_summary = $summary;
	}

	/**
	 * @param $context WDP_Cart_Context
	 */
	public function update_prices( $context ) {
		$new_price            = $this->get_new_price();
		$is_override_cents    = $context->get_option( 'is_override_cents' ) && ( $this->get_new_price() !== $this->get_price() );
		$price_ends_with      = $context->get_option( 'prices_ends_with' );
		$price_mode           = $context->get_price_mode();
		$price_includes_tax   = $context->is_prices_includes_tax();
		$wc_sale_price        = $this->get_prop( 'initial_is_on_sale' ) ? $this->get_prop( 'initial_sale_price' ) : "";
		$is_maximize_discount = 'compare_discounted_and_sale' === $price_mode && $wc_sale_price !== "";

		$initial_num_decimals = wc_get_price_decimals();
		$set_price_decimals = function ( $num_decimals ) use ( $initial_num_decimals ) {
			return $initial_num_decimals + 1;
		};

		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			add_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		if ( $is_maximize_discount || $is_override_cents ) {
			if ( ! $price_includes_tax ) {
				$new_price = $this->get_price_including_tax( $this->wc_product, array( 'price' => $new_price ) );
			}
			if ( $is_maximize_discount ) {
				$new_price = $this->maximize_discount( $new_price );
			}
			if ( $is_override_cents ) {
				$new_price = $this->override_cents( $new_price, $price_ends_with );
			}
			if ( ! $price_includes_tax ) {
				$new_price = $this->get_price_excluding_tax( $this->wc_product, array( 'price' => $new_price ) );
			}
		}

		$initial_price = $this->get_price();

		$new_price     = apply_filters( 'wdp_update_new_price', $new_price, $this->wc_product );
		$initial_price = apply_filters( 'wdp_update_initial_price', $initial_price, $this->wc_product );

		if ( ! $context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
			remove_filter( 'wc_get_price_decimals', $set_price_decimals );
		}

		$this->set_new_price( $new_price );
		$this->set_price( $initial_price );
		$this->set_prop( 'adjustments_amount', (float) $new_price - (float) $initial_price );

		// for 3rd party plugins
		// update by reference WC_Product which "travels" thought price filters
		// So, e.g. after apply our "get_sale_price" filter, WC_Product price props update too
//		$this->wc_product->set_regular_price( $initial_price );
//		$this->wc_product->set_sale_price( $new_price );
//		$this->wc_product->set_price( $new_price );
	}

	/**
	 * @param WDP_Product $child
	 */
	public function update_children_summary( $child ) {
		// initial price
		// required the greatest among children
		$max_initial_price     = $this->get_price();
		$new_max_initial_price = (float) ( $max_initial_price > - 1 ? max( $max_initial_price, $child->get_price() ) : $child->get_price() );
		if ( (float) $new_max_initial_price !== (float) $max_initial_price ) {
			$this->set_price( $new_max_initial_price );
		}


		// min and max calculated price
		$max_price = $this->get_max_price();
		$min_price = $this->get_min_price();

		if ( $child->are_rules_applied() ) {
			$child_price = $child->get_new_price();

			if ( ! $this->are_rules_applied() ) {
				$this->rules_applied();
			}

			$total_adjustments = array_sum( array_map( function ( $amounts ) {
				return array_sum( $amounts );
			}, $child->get_history() ) );

			if ( ! $this->are_discount_applied() && $total_adjustments > 0 ) {
				$this->discount_applied();
			}
		} else {
			$child_price = $child->get_price();
		}

		$new_max_price = (float) ( $max_price > - 1 ? max( $max_price, $child_price ) : $child_price );
		$new_min_price = (float) ( $min_price > - 1 ? min( $min_price, $child_price ) : $child_price );

		$max_price_updated = (float) $new_max_price !== (float) $max_price;
		$min_price_updated = (float) $new_min_price !== (float) $min_price;

		if ( $max_price_updated ) {
			$this->set_max_price( $new_max_price );
			$this->children_summary['max'] = array(
				'initial_price' => (float) $child->get_price(),
			);
		}

		if ( $min_price_updated ) {
			$this->set_min_price( $new_min_price );
			$this->children_summary['min'] = array(
				'initial_price' => (float) $child->get_price(),
			);
		}

		if ( $max_price_updated || $min_price_updated ) {
			if ( $new_max_price === $new_min_price ) {
				$this->set_new_price( $new_min_price );
			} else {
				$this->set_prop( 'calculated_price', "" );
			}
		}


		// min bulk price
		$all_children_affected_by_bulk = isset( $this->children_summary['all_children_affected_by_bulk'] ) ? (boolean) $this->children_summary['all_children_affected_by_bulk'] : null;

		if ( $child->is_affected_by_bulk() ) {
			if ( ! $this->is_affected_by_bulk() ) {
				$this->affected_by_bulk();
			}

			$min_bulk_price     = $this->get_min_bulk_price();
			$new_min_bulk_price = (float) ( $min_bulk_price > - 1 ? min( $min_bulk_price, $child->get_min_bulk_price() ) : $child->get_min_bulk_price() );
			if ( (float) $new_min_bulk_price !== (float) $min_bulk_price ) {
				$this->children_summary['min_bulk'] = array(
					'initial_price' => (float) $child->get_price(),
				);
				$this->set_min_bulk_price( (float) $child->get_min_bulk_price() );
			}

			if ( is_null( $all_children_affected_by_bulk ) ) {
				$all_children_affected_by_bulk = true;
			}

		} else {
			$all_children_affected_by_bulk = false;
		}
		$this->children_summary['all_children_affected_by_bulk'] = $all_children_affected_by_bulk;
	}

	/**
	 * @param array $data
	 */
	public function apply_initial_data( $data ) {
		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case "is_on_sale":
					$this->set_prop( 'initial_is_on_sale', $value );
					break;
				case "regular_price":
					$this->set_prop( 'initial_regular_price', $value );
					break;
				case "sale_price":
					$this->set_prop( 'initial_sale_price', $value );
					break;
				case "price":
					$this->set_prop( 'initial_wc_price', $value );
					break;
			}
		}
	}

	public function get_initial_wc_price() {
		return $this->get_prop( 'initial_wc_price' );
	}

	private function maximize_discount( $new_price ) {
		$wc_sale_price = $this->get_prop( 'initial_is_on_sale' ) ? $this->get_prop( 'initial_sale_price' ) : "";
		if ( "" !== $wc_sale_price ) {
			$wc_sale_price = $this->get_price_including_tax( $this->wc_product, array( 'price' => $wc_sale_price ) );
			if ( $wc_sale_price < $new_price ) {
				$new_price = $wc_sale_price;
				$this->set_prop( 'history', array() );
			}
		}

		return $new_price;
	}

	/**
	 * @param  WC_Product $product WC_Product object.
	 * @param  array      $args Optional arguments to pass product quantity and price.
	 *
	 * @return float
	 */
	private function get_price_including_tax( $product, $args ) {
		return wc_get_price_including_tax( $product, $args );
	}

	/**
	 * @param  WC_Product $product WC_Product object.
	 * @param  array      $args Optional arguments to pass product quantity and price.
	 *
	 * @return float
	 */
	private function get_price_excluding_tax( $product, $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'qty'   => '',
				'price' => '',
			)
		);

		$price = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : $product->get_price();
		$qty   = '' !== $args['qty'] ? max( 0.0, (float) $args['qty'] ) : 1;

		if ( '' === $price ) {
			return '';
		} elseif ( empty( $qty ) ) {
			return 0.0;
		}

		$line_price = $price * $qty;

		if ( $product->is_taxable() && ! wc_prices_include_tax() ) {
			$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
			$remove_taxes     = WC_Tax::calc_tax( $line_price, $tax_rates, true );
			$return_price   = $line_price - array_sum( $remove_taxes ); // Unrounded since we're dealing with tax inclusive prices. Matches logic in cart-totals class. @see adjust_non_base_location_price.
		} else {
			$return_price = $line_price;
		}

		return apply_filters( 'woocommerce_get_price_excluding_tax', $return_price, $qty, $product );
	}

	private function override_cents( $price, $prices_ends_with ){
		$price_fraction = $price - intval( $price );
		$new_price_fraction = $prices_ends_with / 100;

		$round_new_price_fraction = round($new_price_fraction);


		if ( 0 == intval( $price ) AND 0 < $new_price_fraction ){
			$price = $new_price_fraction;
			return $price;
		}


		if ( $round_new_price_fraction ) {

			if ( $price_fraction <= $new_price_fraction - round(1/2, 2) ){
				$price = intval( $price ) - 1 + $new_price_fraction;
			} else {
				$price = intval( $price ) + $new_price_fraction;
			}

		} else {

			if ( $price_fraction >= $new_price_fraction + round(1/2, 2) ){
				$price = intval( $price ) + 1 + $new_price_fraction;
			} else {
				$price = intval( $price ) + $new_price_fraction;
			}

		}

		return $price;
	}

	protected function get_prop( $prop ) {
		return array_key_exists( $prop, $this->data ) ? $this->data[ $prop ] : null;
	}

	protected function set_prop( $prop, $value ) {
		if ( array_key_exists( $prop, $this->data ) ) {
			$this->data[ $prop ] = $value;
		}
	}

	public function get_wc_price_html( $qty = 1 ) {
		if ( class_exists( "WC_Product_Subscription" ) && $this->wc_product instanceof WC_Product_Subscription ) {
			return $this->wc_product->get_price_html();
		}

		if ( $this->wc_product instanceof WC_Product_Simple || $this->wc_product instanceof WC_Product_Variation ) {
			if ( '' === $this->wc_product->get_price() ) {
				$price = apply_filters( 'woocommerce_empty_price_html', '', $this->wc_product );
			} elseif ( $this->wc_product->is_on_sale() ) {
				$price = wc_format_sale_price( wc_get_price_to_display( $this->wc_product, array(
						'price' => $this->wc_product->get_regular_price(),
						'qty'   => $qty
					) ), wc_get_price_to_display( $this->wc_product, array( 'qty' => $qty ) ) ) . $this->wc_product->get_price_suffix();
			} else {
				$price = wc_price( wc_get_price_to_display( $this->wc_product, array( 'qty' => $qty ) ) ) . $this->wc_product->get_price_suffix();
			}

			return apply_filters( 'woocommerce_get_price_html', $price, $this->wc_product );
		}


		return $this->wc_product->get_price_html();
	}

	public function is_range_correct() {
		return $this->get_min_price() < $this->get_max_price();
	}

	public function is_all_children_have_equal_price() {
		return $this->get_min_price() === $this->get_max_price();
	}

	public function is_range_defined() {
		return $this->get_max_price() !== floatval( - 1 ) && $this->get_min_price() !== floatval( - 1 );
	}
}