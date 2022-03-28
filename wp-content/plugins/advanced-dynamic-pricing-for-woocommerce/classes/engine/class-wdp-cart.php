<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart {
	const INITIAL_DATA_KEY = 'wdp_initial_data';

	/**
	 * @var WC_Cart
	 */
	private $wc_cart;

	/**
	 * @var WDP_Cart_Context
	 */
	private $context;

	/**
	 * @var WDP_Cart_Item[]
	 */
	private $items = array();

	/**
	 * @var array
	 */
	private $hash_item_mapping = array();

	/**
	 * @var array
	 */
	private $coupons = array();

	/**
	 * @var array WC_Coupon[]
	 */
	private $external_coupons = array();

	/**
	 * @var array
	 */
	private $fees = array();


	/**
	 * @var WDP_Cart_Adjustments_Shipping
	 */
	private $shipping_adjustments;

	/**
	 * @var WDP_Cart_Free_Products
	 */
	private $free_products;

	private $initial_cart_subtotal;
	private $initial_cart_subtotal_tax;

	/**
	 * Associated array of price adjustments applied to added item during 'apply_to_wc_cart' method.
	 * Needs to store history of calculation for user reporter. 'wdp_rules' key of cart item may be wiped by e.g.
	 * 'best between' discount mode
	 *
	 * @var array cart_item_hash => array (rule_id => amount)
	 */
	private $calculation_history = array();


	private $initial_products_data = array();

	/**
	 * WDP_Cart constructor.
	 *
	 * @param $context WDP_Cart_Context
	 * @param $wc_cart WC_Cart|false
	 */
	public function __construct( $context, $wc_cart = false ) {
		$this->context       = $context;
		$this->wc_cart       = $wc_cart;
		$this->free_products = new WDP_Cart_Free_Products();

		if ( $this->wc_cart instanceof WC_Cart ) {
			$index = 0;

			foreach ( $this->wc_cart->get_cart_contents() as $wc_cart_item ) {
				$hash                             = $this->calculate_hash( $wc_cart_item );
				$this->hash_item_mapping[ $hash ] = $wc_cart_item;

				$product = $wc_cart_item['data'];
				$product = apply_filters( 'wdp_get_product', WDP_Object_Cache::get_instance()->get_wc_product( $product ), $wc_cart_item );

				if ( isset( $wc_cart_item['wdp_gifted'] ) ) {
					$rule_id = array_keys( $wc_cart_item['wdp_rules'] );
					$rule_id = reset($rule_id);

					$qty = (float)$wc_cart_item['quantity'];

					if ( $rule_id && $product && $qty ) {
						$this->free_products->add_previously_gifted_cart_item( $rule_id, $product, $qty );
					}
					continue;
				}

				if ( apply_filters( 'wdp_skip_cart_item', false, $wc_cart_item, $product ) ) {
					continue;
				}

				$price = $this->get_original_price( $product, $wc_cart_item );
				$qty   = apply_filters( 'wdp_get_product_qty', $wc_cart_item['quantity'], $wc_cart_item );
				$item  = new WDP_Cart_Item( $hash, $price, $index, $qty );
				$item->set_additional_adjustments( $price - $this->get_original_price( $product, $wc_cart_item, 'edit' ) );
				if ( $this->is_readonly_price( $product, $wc_cart_item ) ) {
					$item->make_readonly_price();
				}

				if ( ! empty( $wc_cart_item['wdp_immutable'] ) ) {
					$history      = $wc_cart_item['wdp_rules'];
					$initial_cost = $item->get_price();
					foreach ( $history as $rule_id => $amount ) {
						$initial_cost += $amount;
					}
					$item = new WDP_Cart_Item( $hash, $initial_cost, $index, $qty );
					foreach ( $history as $rule_id => $amount ) {
						$item->set_price( $rule_id, $item->get_price() - $amount );
					}
					$item->make_immutable();
				}

				$this->store_item_initial_data( $item, $product, $wc_cart_item );

				$this->items[] = apply_filters( 'wdp_prepare_cart_item', $item, $wc_cart_item );
				$index++;
			}

			foreach ( $this->wc_cart->get_coupons() as $coupon ) {
				/**
				 * @var $coupon WC_Coupon
				 */
				$this->external_coupons[ $coupon->get_code() ] = $coupon;
			}
			$this->initial_cart_subtotal     = $this->wc_cart->get_subtotal();
			$this->initial_cart_subtotal_tax = $this->wc_cart->get_subtotal_tax();
		}


		$this->shipping_adjustments = new WDP_Cart_Adjustments_Shipping();
	}

	public function __clone() {
		$new_items = array();
		foreach ( $this->items as $item ) {
			$new_items[] = clone $item;
		}
		$this->items = $new_items;

		$this->free_products = clone $this->free_products;

		$new_shipping = new WDP_Cart_Adjustments_Shipping();
		if ( $this->shipping_adjustments->is_free() ) {
			$new_shipping->apply_free_shipping($this->shipping_adjustments->get_rule_id_applied_free_shipping());
		} else {
			foreach ( $this->shipping_adjustments->get_items() as $adjustment ) {
				$new_shipping->add( $adjustment['type'], $adjustment['value'], $adjustment['rule_id'] );
			}
		}
		$this->shipping_adjustments = $new_shipping;

	}

	/**
	 * @param             $product WC_Product
	 * @param array       $item_meta
	 *
	 * @return boolean
	 */
	private function get_is_on_sale( $product, $item_meta = array(), $context = 'view' ) {
		$result = $product->is_on_sale( $this->context->get_option('initial_price_context') );
		return $context === 'view' ? apply_filters( "wdp_get_product_is_on_sale", $result, $product, $item_meta ) : $result;
	}

	/**
	 * @param             $product WC_Product
	 * @param array       $item_meta
	 *
	 * @return float
	 */
	private function get_regular_price( $product, $item_meta = array(), $context = 'view' ) {
		$result = $product->get_regular_price( $this->context->get_option('initial_price_context') );
		return $context === 'view' ? apply_filters( "wdp_get_product_regular_price", $result, $product, $item_meta ) : $result;
	}

	/**
	 * @param             $product WC_Product
	 * @param array       $item_meta
	 *
	 * @return float
	 */
	private function get_sale_price( $product, $item_meta = array(), $context = 'view' ) {
		$result = $product->get_sale_price( $this->context->get_option('initial_price_context') );
		return $context === 'view' ? apply_filters( "wdp_get_product_sale_price", $result, $product, $item_meta ) : $result;
	}

	/**
	 * @param             $product WC_Product
	 * @param array       $item_meta
	 *
	 * @return float
	 */
	private function get_price( $product, $item_meta = array(), $context = 'view' ) {
		$result = $product->get_price( $this->context->get_option('initial_price_context') );
		return $context === 'view' ? apply_filters( "wdp_get_product_initial_price", $result, $product, $item_meta ) : $result;
	}

	/**
	 * @param WC_Product $product
	 * @param array      $item_meta
	 *
	 * @return float
	 */
	public function get_original_price( $product, $item_meta = array(), $context = 'view' ) {
		$price_mode = $this->context->get_price_mode();

		if ( $this->get_is_on_sale( $product, $item_meta, $context ) ) {
			if ( 'sale_price' === $price_mode || 'discount_sale' === $price_mode ) {
				$price = $this->get_sale_price( $product, $item_meta, $context );
			} else {
				$price = $this->get_regular_price( $product, $item_meta, $context );
			}
		} else {
			$price = $this->get_price( $product, $item_meta, $context );
		}

		return $context === 'view' ? apply_filters( "wdp_get_product_price", (float) $price, $product, $price_mode, $item_meta ) : (float) $price;
	}

	/**
	 * @param WC_Product $product
	 * @param array      $item_meta
	 *
	 * @return boolean
	 */
	public function is_readonly_price( $product, $item_meta ) {
		if ( $this->get_is_on_sale( $product, $item_meta ) ) {
			if ( 'sale_price' === $this->context->get_price_mode() ) {
				return true;
			}
		}

		return false;
	}

	private function calculate_hash( $wc_cart_item ) {
		$qty = $wc_cart_item['quantity'];

		unset( $wc_cart_item['quantity'] );

		return md5( json_encode( $wc_cart_item ) );
	}

	public function get_item_data_by_hash( $hash ) {
		return isset( $this->hash_item_mapping[ $hash ] ) ? $this->hash_item_mapping[ $hash ] : null;
	}

	private function sort_items() {
		usort( $this->items, function ( $item_a, $item_b ) {
			/**
			 * @var $item_a WDP_Cart_Item
			 * @var $item_b WDP_Cart_Item
			 */
			if ( ! $item_a->is_temporary() && $item_b->is_temporary() ) {
				return - 1;
			}

			if ( $item_a->is_temporary() && ! $item_b->is_temporary() ) {
				return 1;
			}

			return 0;
		} );

	}

	public function get_mutable_items() {
		$this->sort_items();

		return array_filter( $this->items, function ( $item ) {
			return ! $item->is_immutable();
		} );
	}

	public function get_context() {
		return $this->context;
	}

	public function get_wc_cart() {
		return $this->wc_cart;
	}

	public function get_external_coupons() {
		return $this->external_coupons;
	}

	public function purge_mutable_items() {
		$this->items = array_filter( $this->items, function ( $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			return $item->is_immutable();
		} );
	}

	public function destroy_empty_items() {
		$this->items = array_values( array_filter( $this->items, function ( $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			return $item->get_qty() > 0;
		} ) );
	}

	/**
	 * @param $new_item WDP_Cart_Item|WDP_Cart_Item[]
	 */
	public function add_to_cart( $new_item ) {
		if ( is_array( $new_item ) ) {
			foreach ( $new_item as $item ) {
				$this->add_to_cart( $item );
			}
		}

		if ( ! ( $new_item instanceof WDP_Cart_Item ) ) {
			return;
		}

		foreach ( $this->items as &$item ) {
			if ( ! $item->is_immutable()
			     && ( $item->is_immutable() === $new_item->is_immutable() )
			     && ( $item->get_hash() === $new_item->get_hash() )
			     && ( $item->get_price() === $new_item->get_price() )
			     && ( md5( json_encode( $item->get_history() ) ) === md5( json_encode( $new_item->get_history() ) ) )
			     && ( $item->get_exclude_rules_hash() === $new_item->get_exclude_rules_hash() )
			     && ( $item->get_first_discount_range_rule() === $new_item->get_first_discount_range_rule() )
			) {
				$item->inc_qty( $new_item->get_qty() );

				return;
			}
		}

		// if unique item, create new
		$this->items[] = $new_item;
		usort( $this->items, function ( $item1, $item2 ) {
			/**
			 * @var $item1 WDP_Cart_Item
			 * @var $item2 WDP_Cart_Item
			 */

			$pos1 = $item1->get_pos();
			$pos2 = $item2->get_pos();

			return $pos1 - $pos2;
		} );

		return;
	}

	/**
	 * @param int|WC_Product|WDP_Product $the_product
	 * @param int                        $qty
	 *
	 * @return boolean
	 */
	public function add_product_to_calculate( $the_product, $qty = 1 ) {
		if ( $the_product instanceof WC_Product ) {
			$wc_product = $the_product;
			$wdp_product = null;
		} elseif ( $the_product instanceof WDP_Product ) {
			$wc_product = $the_product->get_wc_product();
			$wdp_product = $the_product;
		} else if ( $the_product && is_numeric( $the_product ) ) {
			$wc_product = WDP_Object_Cache::get_instance()->get_wc_product( $the_product );
			$wdp_product = null;
		} else {
			return false;
		}

		if ( '' === $this->get_price( $wc_product ) ) {
			return false;
		}

		$hash = md5( $wc_product->get_id() );

		/** Prepare temporary cart item */
		$wc_cart_item               = array();
		$wc_cart_item['data']       = $wc_product;
		$wc_cart_item['product_id'] = $wc_product->get_id();


		if ( $wc_product->is_type( 'variation' ) ) {
			$variation_id = $wc_product->get_id();
			$product_id   = $wc_product->get_parent_id();
		} else {
			$product_id   = $wc_product->get_id();
			$variation_id = 0;
		}


		/**
		 * Create pseudo cart items data
		 * use set_error_handler() to throw fatal errors
		 */
		set_error_handler( function ( $errno, $string, $file, $line, $context ) {
			throw new Exception();
		}, E_ERROR | E_RECOVERABLE_ERROR );
		try {
			$wc_cart_item = (array) apply_filters( 'woocommerce_add_cart_item_data', $wc_cart_item, $product_id, $variation_id, $qty );
		} catch ( Throwable $e ) {}
		restore_error_handler();

		// restore if were removed
		if ( empty( $wc_cart_item['product_id'] ) ) {
			$wc_cart_item['product_id'] = $wc_product->get_id();
		}
		if ( empty( $wc_cart_item['data'] ) ) {
			$wc_cart_item['data'] = $wc_product;
		}

		$this->hash_item_mapping[ $hash ] = $wc_cart_item;

		if ( $wdp_product && $wdp_product->is_use_custom_initial_price() ) {
			$original_price = $wdp_product->get_price();
		} else {
			$original_price = $this->get_original_price( $wc_product, $wc_cart_item );
		}

		$original_price = apply_filters( "wdp_add_to_calculate_product_original_price", $original_price, $this, $wc_product, $wdp_product, $qty, $wc_cart_item );

		$item = new WDP_Cart_Item( $hash, $original_price, count( $this->get_items() ), $qty );

		if ( $wdp_product ) {
			$price_mode = $this->context->get_price_mode();

			if ( $wdp_product->get_wc_product()->is_on_sale( 'edit' ) ) {
				if ( 'sale_price' === $price_mode || 'discount_sale' === $price_mode ) {
					$price = $wdp_product->get_wc_product()->get_sale_price( 'edit' );
				} else {
					$price = $wdp_product->get_wc_product()->get_regular_price( 'edit' );
				}
			} else {
				$price = $wdp_product->get_wc_product()->get_price( 'edit' );
			}

			$item->set_additional_adjustments( $original_price - floatval( $price ) );
		}

		if ( $this->is_readonly_price( $wc_product, $wc_cart_item ) ) {
			$item->make_readonly_price();
		}
		$item->mark_as_temporary();

		$this->store_item_initial_data( $item, $wc_product, $wc_cart_item );

		$item = apply_filters( 'wdp_add_to_calculate_cart_item', $item, $wc_product, $qty, $wc_cart_item );

		$this->add_to_cart( $item );

		return true;
	}

	/** DISCOUNT AMOUNT AS COUPONS */

	public function add_coupon_amount( $coupon_amount, $rule_id, $coupon_name = '' ) {
		$this->coupons[] = array(
			'type'    => 'amount',
			'value'   => $coupon_amount,
			'rule_id' => $rule_id,
			'name'    => $coupon_name,
		);
	}

	public function add_coupon_percentage( $coupon_percentage, $rule_id, $coupon_name = '' ) {
		$this->coupons[] = array(
			'type'    => 'percentage',
			'value'   => $coupon_percentage,
			'rule_id' => $rule_id,
			'name'    => $coupon_name,
		);
	}

	public function add_replaced_item_adjustment_coupon( $value, $coupon_name, $rule_id ) {
		$coupon = array(
			'type'         => 'item_adjustments',
			'value'        => $value,
			'name'         => $coupon_name,
			'rule_id'      => (int) $rule_id,
			'is_item_free' => false,
		);

		$this->coupons[] = $coupon;
	}

	public function add_replaced_free_product_adjustment_coupon( $value, $coupon_name, $rule_id ) {
		$coupon = array(
			'type'         => 'item_adjustments',
			'value'        => $value,
			'name'         => $coupon_name,
			'rule_id'      => (int) $rule_id,
			'is_item_free' => true,
		);

		$this->coupons[] = $coupon;
	}

	/** END DISCOUNT AMOUNT AS COUPONS */

	/** FEE */

	public function add_fee_amount( $fee_name, $fee_amount, $rule_id, $tax_class = "" ) {
		$this->fees[] = array(
			'type'      => 'amount',
			'value'     => $fee_amount,
			'rule_id'   => $rule_id,
			'name'      => $fee_name,
			'tax_class' => $tax_class,
		);
	}

	public function add_fee_percentage( $fee_name, $fee_percentage, $rule_id, $tax_class = "" ) {
		$this->fees[] = array(
			'type'      => 'percentage',
			'value'     => $fee_percentage,
			'rule_id'   => $rule_id,
			'name'      => $fee_name,
			'tax_class' => $tax_class,
		);
	}

	public function add_replaced_item_adjustments_fee( $fee_amount, $fee_name, $rule_id, $tax_class = "" ) {
		$fee = array(
			'type'      => 'item_adjustments',
			'value'     => $fee_amount,
			'name'      => $fee_name,
			'tax_class' => $tax_class,
			'rule_id'   => $rule_id,
		);

		$this->fees[] = $fee;
	}

	public function set_is_tax_exempt( $tax_exempt ) {
		$this->context->set_is_tax_exempt( $tax_exempt );
	}

	/** END FEE */

	/** SHIPPING */

	/**
	 * @param $shipping_amount
	 * @param $rule_id
	 */
	public function add_shipping_amount_adjustment( $shipping_amount, $rule_id ) {
		$this->shipping_adjustments->add_amount_discount( $shipping_amount, $rule_id );
	}

	/**
	 * @param $shipping_percentage
	 * @param $rule_id
	 */
	public function add_shipping_percentage_adjustment( $shipping_percentage, $rule_id ) {
		$this->shipping_adjustments->add_percentage_discount( $shipping_percentage, $rule_id );
	}

	/**
	 * @param $price
	 * @param $rule_id
	 */
	public function set_shipping_price( $price, $rule_id ) {
		$this->shipping_adjustments->set_fixed_price( $price, $rule_id );
	}

	/**
	 * @param $rule_id
	 */
	public function add_free_shipping( $rule_id ) {
		$this->shipping_adjustments->apply_free_shipping( $rule_id );
	}

	/** END SHIPPING */

	/**
	 * @param $rule_id int
	 * @param $product WC_Product
	 * @param $qty int
	 */
	public function gift_product( $rule_id, $product, $qty ) {
		if ( '' === $this->get_price( $product ) ) {
			return;
		}

		$this->free_products->add( $rule_id, $product, $qty );
	}

	public function replace_gift_products_with_cart_adjustment( $rule_id, $adj_name ) {
		$this->free_products->exclude_rule_adjustments( $rule_id, $adj_name );
	}

	public function get_qty_used( $product_id ) {
		$temporary_qty = 0;
		foreach ( $this->items as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			$original_item   = $this->get_item_data_by_hash( $item->get_hash() );
			$item_product_id = $original_item['product_id'];

			if ( $item->is_temporary() && $item_product_id === $product_id ) {
				$temporary_qty += $item->get_qty();
			}
		}

		return $product_id ? $this->free_products->get_qty( $product_id ) + $temporary_qty : null;
	}

	/**
	 * @return WDP_Cart_Item[]
	 */
	public function get_items() {
		return $this->items;
	}

	public function is_empty() {
		return ! count( $this->items );
	}

	/**
	 * Do not call in __construct!
	 * Because, "Price display" creates new object of WDP_Cart and so destroys applied coupons
	 */
	public static function purge_wdp_coupons() {
		foreach ( WC()->cart->get_coupons() as $coupon ) {
			/**
			 * @var $coupon WC_Coupon
			 */
			if ( $coupon->meta_exists( "is_wdp" ) ) {
				WC()->cart->remove_coupon( $coupon->get_code() );
			}
		}
	}

	/**
	 * @return bool
	 */
	public function apply_to_wc_cart() {
		$this->calculation_history = array();

		$wc_cart = WC()->cart;

		do_action( 'wdp_before_apply_to_wc_cart', $this, $wc_cart );

		/** Store removed_cart_contents to enable undo deleted items */
		$removed_cart_contents = $wc_cart->get_removed_cart_contents();
		$wc_cart->empty_cart();
		$wc_cart->set_removed_cart_contents( $removed_cart_contents );

		// Suppress total recalculation until finished.
		remove_action( 'woocommerce_add_to_cart', array( $wc_cart, 'calculate_totals' ), 20 );

		/**
		 * Prevent to "Set-Cookie" spam in response headers
		 * Because every add_to_cart() call triggers setcookie(..)
		 * Remove action WC_Cart_Session->maybe_set_cart_cookies()
		 */
		try {
			$reflection = new ReflectionClass( $wc_cart );
			$property   = $reflection->getProperty( 'session' );
			$property->setAccessible( true );
			$session = $property->getValue( $wc_cart );
			if ( ! remove_action( 'woocommerce_add_to_cart', array( $session, 'maybe_set_cart_cookies' ), 10 ) ) {
				$session = null;
			}
		} catch ( Exception $e ) {
			$session = null;
		}


		$this->items = apply_filters( 'wdp_internal_cart_items_before_apply', $this->items, $this );
		$this->free_products = apply_filters( 'wdp_internal_free_products_before_apply', $this->free_products, $this );

		/**
		 * Put to down items that are not filtered
		 */
		if ( apply_filters( 'wdp_move_down_not_filtered_items_before_apply', true ) ) {
			usort( $this->items, function ( $item_a, $item_b ) {
				/**
				 * @var $item_a WDP_Cart_Item
				 * @var $item_b WDP_Cart_Item
				 */
				if ( $item_a->get_history() && ! $item_b->get_history() ) {
					return - 1;
				}

				if ( ! $item_a->get_history() && $item_b->get_history() ) {
					return 1;
				}

				return 0;
			} );
		}

		$is_price_changed = false; // disabled external coupons only if items updated

		foreach ( $this->items as $item ) {
			$calculation_history = array();
			foreach ( $item->get_history() as $rule_id => $amounts ) {
				$calculation_history[ $rule_id ] = array_sum( $amounts );
			}

			$wc_cart_item = $this->get_item_data_by_hash( $item->get_hash() );
			/**
			 * @var $wc_product WC_Product
			 * @var $wdp_product WDP_Product
			 */

			$wc_product   = clone $wc_cart_item['data'];
			try {
				$wdp_product = new WDP_Product( $wc_product );
			} catch ( Exception $e ) {
				continue;
			}

			$list_of_adjustments_data = $item->replace_rules_adjustments();
			$true_story = $item->get_history();

			$initial_data = $this->get_item_initial_data( $item );
			$wdp_product->apply_initial_data( $initial_data );

			if ( $item->is_price_changed() ) {
				$wdp_product->set_price( $item->get_initial_price() );
				$wdp_product->set_new_price( $item->get_calculated_price() );
			}
			if ( $item->is_at_least_one_rule_changed_price() ) {
				$wdp_product->apply_history( $item->get_history() );
				$wdp_product->update_prices( $this->get_context() );
				$true_story = $wdp_product->get_history(); // history purges if used WooCommerce sale price
			}

			foreach ( $list_of_adjustments_data as $rule_id => $adjustment_data ) {
				if ( empty( $true_story[ $rule_id ] ) ) {
					continue;
				}

				if ( ! empty( $adjustment_data['amount'] ) && ! empty( $adjustment_data['name'] ) ) {
					if ( $adjustment_data['amount'] > 0 ) {
						$this->add_replaced_item_adjustment_coupon( $adjustment_data['amount'], $adjustment_data['name'], $rule_id );
					} else {
						$this->add_replaced_item_adjustments_fee( ( - 1 ) * $adjustment_data['amount'], $adjustment_data['name'], $rule_id );
					}
				}
			}

			do_action( 'wdp_product_price_updated_before_apply_to_wc_cart', $wdp_product, $item->get_qty() );

			$product_price = $wdp_product->get_new_price();
			if ( $this->context->get_option( 'is_calculate_based_on_wc_precision' ) ) {
				$product_price = round( $product_price, wc_get_price_decimals() );
			}
			$wc_product->set_price( $product_price );

			$product_id = $wdp_product->get_id();
			if ( $wc_product instanceof WC_Product_Variation ) {
				/** @var WC_Product_Variation $wc_product */
				$variation_id = $product_id;
				$product_id   = $wc_product->get_parent_id();
				$variation    = $wc_cart_item['variation'];
			} else {
				$variation_id = null;
				$variation    = array();
			}

			$original_item  = $wc_cart_item;
			$default_keys = array(
				'key',
				'product_id',
				'variation_id',
				'variation',
				'quantity',
				'data',
				'data_hash',
				'line_tax_data',
				'line_subtotal',
				'line_subtotal_tax',
				'line_total',
				'line_tax',
			);
			foreach ( $default_keys as $key ) {
				unset($original_item[$key]);
			}

			$wdp_rules = array();
			foreach ( $wdp_product->get_history() as $rule_id => $amounts ) {
				$wdp_rules[ $rule_id ] = array_sum( $amounts );
			}
			$wdp_rules = apply_filters( 'wdp_rules_amount_for_item', $wdp_rules, $wdp_product, $wc_product );

			foreach ( $wdp_rules as $rule_id => $amount ) {
				if ( $amount > 0.0 ) {
					$is_price_changed = true;
				}
			}

			$cart_item_data = array(
//				'wdp_gifted'             => $cart_item['gifted'],
				'wdp_rules'              => $wdp_rules,
//				'wdp_rules_for_singular' => $cart_item['rules_for_singular'],
//				'wdp_history' => $item->get_history(),
				'wdp_original_price' => $wdp_product->get_price(),
				self::INITIAL_DATA_KEY => array(
					'subtotal'     => floatval( $initial_data['line_subtotal'] ) / floatval( $initial_data['quantity'] ),
					'subtotal_tax' => floatval( $initial_data['line_subtotal_tax'] ) / floatval( $initial_data['quantity'] ),
				),
			);

			$original_cart_item_data = array_diff_key( $original_item, $cart_item_data );
//			$cart_item_data = array_merge($cart_item_data, $original_cart_item_data);
			$cart_item_data = apply_filters( 'wdp_cart_item_data_before_apply', $cart_item_data, $original_cart_item_data );


			$exclude_hooks = apply_filters( 'wdp_exclude_hooks_when_add_to_cart_calculated_items', array( 'woocommerce_add_cart_item_data' ) );
			$cart_item_key = WDP_Frontend::process_without_hooks( function () use ( $wc_cart, $product_id, $item, $variation_id, $variation, $cart_item_data ) {
				return $wc_cart->add_to_cart( $product_id, $item->get_qty(), $variation_id, $variation, $cart_item_data );
			}, $exclude_hooks );

			$original_cart_item_data = apply_filters( 'wdp_original_cart_item_data', $original_cart_item_data );

			//Must  replace the product in the cart!
			if ( $cart_item_key ) {
				$wc_cart->cart_contents[ $cart_item_key ] ['data'] = $wc_product;
				// restore cart item data after rules applied
				foreach ( $original_cart_item_data as $key => $value ) {
					$wc_cart->cart_contents[ $cart_item_key ][ $key ] = $value;
				}

				$this->calculation_history[$cart_item_key] = $calculation_history;
			}

		}

		foreach ( $this->free_products->get_items() as $rule_id => $rule_free_products ) {
			foreach ( $rule_free_products as $product_id => $qty ) {
				$adj_replace_name = $this->free_products->get_rule_adjustment_replacement($rule_id);

				try {
					$wdp_product = new WDP_Product( $this->free_products->get_product( $product_id ) );
				} catch ( Exception $e ) {
					continue;
				}

				if ( ! empty( $adj_replace_name ) ) {
					$this->add_replaced_free_product_adjustment_coupon( $wdp_product->get_price() * $qty, $adj_replace_name, $rule_id );
					$rules = array();
				} else {
					$wdp_product->set_new_price( 0 );
					$rules = array( $rule_id => $wdp_product->get_price() );
				}

				$wdp_product->update_prices( $this->get_context() );

				do_action( 'wdp_product_price_updated_before_apply_to_wc_cart', $wdp_product, $qty );

				$cart_item_data = array(
					'wdp_gifted' => $qty,
					'wdp_rules'  => $rules,
					'wdp_original_price' => $wdp_product->get_price(),
				);

				$wc_product = $wdp_product->get_wc_product();
				$product_id = $wdp_product->get_id();
				if ( $wc_product instanceof WC_Product_Variation ) {
					/** @var WC_Product_Variation $wc_product */
					$variation_id = $product_id;
					$product_id   = $wc_product->get_parent_id();
					$variation    = $wc_product->get_variation_attributes();
				} else {
					$variation_id = 0;
					$variation    = array();
				}

				$exclude_hooks = apply_filters( 'wdp_exclude_hooks_when_add_to_cart_calculated_items', array( 'woocommerce_add_cart_item_data' ) );
				$cart_item_key = WDP_Frontend::process_without_hooks( function () use ( $wc_cart, $product_id, $variation_id, $variation, $qty, $cart_item_data ) {
					return $wc_cart->add_to_cart( $product_id, $qty, $variation_id, $variation, $cart_item_data );
				}, $exclude_hooks );

				if ( $cart_item_key ) {
					$wc_cart->cart_contents[ $cart_item_key ] ['data']->set_price( $wdp_product->get_new_price() );
				}
			}
		}

		if ( $this->context->get_option( 'show_message_after_add_free_product' ) ) {
			foreach ( $this->free_products->get_recently_gifted_products() as $product_id => $qty ) {
				$product   = WDP_Object_Cache::get_instance()->get_wc_product( $product_id );
				$template  = $this->context->get_option( 'message_template_after_add_free_product' );
				$arguments = array(
					'{{qty}}'          => $qty,
					'{{product_name}}' => $product->get_name(),
				);
				wc_add_notice( str_replace( array_keys( $arguments ), array_values( $arguments ), $template ) );
			}
		}

		if ( $this->context->get_option('disable_external_coupons_only_if_items_updated') && apply_filters('wdp_is_disable_external_coupons_if_items_updated', $is_price_changed, $this, $wc_cart ) ) {
			$this->external_coupons = array();
		}


		add_action( 'woocommerce_add_to_cart', array( $wc_cart, 'calculate_totals' ), 20, 0 );

		/**
		 * Restore action WC_Cart_Session->maybe_set_cart_cookies() on demand
		 * Do not forget to call maybe_set_cart_cookies() to set current cookie of the cart
		 */
		if ( ! empty( $session ) ) {
			add_action( 'woocommerce_add_to_cart', array( $session, 'maybe_set_cart_cookies' ) );
			$session->maybe_set_cart_cookies();
		}

		new WDP_Cart_Totals( $this, $wc_cart );

		do_action( 'wdp_after_apply_to_wc_cart', $this );

		return true;
	}

	/**
	 * @return array WDP_Cart_Item[]
	 */
	public function get_temporary_items() {
		$items = array();
		foreach ( $this->items as $cart_item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */

			if ( $cart_item->is_temporary() ) {
				$items[] = clone $cart_item;
				break;
			}
		}

		return $items;
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return array WDP_Cart_Item[]
	 */
	public function get_temporary_items_by_product( $product ) {
		$items = array();
		foreach ( $this->items as $cart_item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			$original_item   = $this->get_item_data_by_hash( $cart_item->get_hash() );
			$item_product_id = ! empty( $original_item['variation_id'] ) ? $original_item['variation_id'] : $original_item['product_id'];

			if ( $cart_item->is_temporary() && $item_product_id === $product->get_id() ) {
				$items[] = clone $cart_item;
			}
		}

		return $items;
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return array WDP_Cart_Item[]
	 */
	public function get_items_by_product( $product ) {
		$items = array();
		foreach ( $this->items as $cart_item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			$original_item   = $this->get_item_data_by_hash( $cart_item->get_hash() );
			$item_product_id = ! empty( $original_item['variation_id'] ) ? $original_item['variation_id'] : $original_item['product_id'];

			if ( $item_product_id === $product->get_id() ) {
				$items[] = clone $cart_item;
			}
		}

		return $items;
	}

	public function get_adjustments() {
		return array(
			'shipping' => $this->shipping_adjustments,
			'coupons' => $this->coupons,
			'fees' => $this->fees,
		);
	}

	public function has_immutable_changed_items() {
		$result = false;
		foreach ( $this->items as $item ) {
			/**
			 * @var WDP_Cart_Item $item
			 */
			if ( $item->is_immutable() && $item->are_rules_applied() ) {
				$result = true;
				break;
			}

		}

		return $result;
	}

	public function get_initial_cart_subtotal() {
		return $this->initial_cart_subtotal;
	}

	public function get_initial_cart_totals() {
		return array(
			'subtotal'     => $this->initial_cart_subtotal,
			'subtotal_tax' => $this->initial_cart_subtotal_tax,
		);
	}

	public function get_calculation_history( $cart_item_key ) {
		return isset( $this->calculation_history[ $cart_item_key ] ) ? $this->calculation_history[ $cart_item_key ] : null;
	}

	/**
	 * @param WDP_Cart_Item $item
	 * @param WC_Product $product
	 * @param array $wc_cart_item
	 */
	private function store_item_initial_data( $item, $product, $wc_cart_item ) {
		$this->initial_products_data[ $item->get_hash() ] = array(
			'is_on_sale'    => $this->get_is_on_sale( $product, $wc_cart_item ),
			'regular_price' => $this->get_regular_price( $product, $wc_cart_item ),
			'sale_price'    => $this->get_sale_price( $product, $wc_cart_item ),
			'price'         => $this->get_price( $product, $wc_cart_item ),
		);

		foreach (
			array(
				'line_tax_data',
				'line_subtotal',
				'line_subtotal_tax',
				'line_total',
				'line_tax',
				'quantity'
			) as $total_key
		) {
			$value = null;
			if ( isset( $wc_cart_item[ $total_key ] ) ) {
				$value = $wc_cart_item[ $total_key ];
			}

			$this->initial_products_data[ $item->get_hash() ][ $total_key ] = $value;
		}
	}

	/**
	 * @param WDP_Cart_Item $item
	 *
	 * @return array
	 */
	public function get_item_initial_data( $item ) {
		$hash = $item->get_hash();

		return isset( $this->initial_products_data[ $hash ] ) ? $this->initial_products_data[ $hash ] : array();
	}

	public function hash() {
		$data = array();

		$items = array();
		foreach ( $this->items as $item ) {
			$items[] = $item->get_calc_hash();
		}
		$data['items'] = $items;

		$data['free_products']        = $this->free_products->get_items();
		$data['adjustments_shipping'] = $this->shipping_adjustments->get_items();

		return md5( json_encode( $data ) );
	}
}