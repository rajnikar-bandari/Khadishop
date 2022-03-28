<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Calculator {
	/**
	 * @var WDP_Rules_Collection
	 */
	protected $rule_collection;
	/**
	 * @var WDP_Cart
	 */
	protected $cart;

	/**
	 * @var WDP_Cart_Calculator_Subscriber[]
	 */
	protected $subscribers = array();

	/**
	 * @param WDP_Rules_Collection $rule_collection
	 */
	public function __construct( $rule_collection ) {
		$this->rule_collection = $rule_collection;
	}

	public function add_subscriber( $sub ) {
		if ( $sub instanceof WDP_Cart_Calculator_Subscriber ) {
			$this->subscribers[] = $sub;
		}
	}

	/**
	 * @return WDP_Rule[]
	 */
	public function get_rule_array() {
		return $this->rule_collection->to_array();
	}

	/**
	 * @return WDP_Rule[]
	 */
	public function get_bulk_rule_array() {
		return $this->rule_collection->with_bulk()->to_array();
	}

	/**
	 * @return bool
	 */
	public function at_least_one_rule_active() {
		$rule_array = $this->rule_collection->to_array();
		return ! empty( $rule_array );
	}

	/**
	 * @param WDP_Cart       $cart
	 * @param WC_Product|int $the_product
	 *
	 * @return WDP_Rules_Collection
	 */
	public function find_product_matches( $cart, $the_product ) {
		$matched = array();

		$rule_array = $this->rule_collection->to_array();
		foreach ( $rule_array as $rule ) {
			$is_matched = false;
			try {
				$is_matched = $rule->is_product_matched( $cart, $the_product );
			} catch ( Exception $e ) {
			}

			if ( $is_matched ) {
				$matched[] = $rule;
			}
		}

		return new WDP_Rules_Collection( $matched );
	}

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return WDP_Cart|boolean
	 */
	public function process_cart_new( $cart ) {
		$applied_rules = 0;

		$rule_array = $this->rule_collection->to_array();
		foreach ( $rule_array as $rule ) {
			if ( $rule->apply_to_cart( $cart ) ) {
				$applied_rules ++;
			}
		}

		return $applied_rules ? $cart : false; // no new cart
	}

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return WDP_Cart|boolean
	 */
	public function main_process_cart($cart) {
		if ( $cart->is_empty() ) {
			return false;
		}
		$applied_rules = 0;
		foreach ( $this->subscribers as $subscriber ) {
			$subscriber->start_cart_calculation();
		}

		$rule_array = $this->rule_collection->to_array();
		foreach ( $rule_array as $rule ) {
			if ( $rule->apply_to_cart( $cart ) ) {
				$applied_rules ++;
			}

			foreach ( $this->subscribers as $subscriber ) {
				$subscriber->rule_calculated_cart($rule);
			}
		}

		foreach ( $this->subscribers as $subscriber ) {
			$subscriber->cart_calculated( $cart );
		}

		return $applied_rules ? $cart : false; // no new cart
	}

	/**
	 * @param WDP_Cart               $cart
	 * @param WDP_Product|WC_Product $product
	 * @param integer                $qty
	 *
	 * @return WDP_Cart|boolean
	 */
	public function process_cart_with_product( $cart, $product, $qty = 1 ) {
		if ( $product instanceof WC_Product ) {
			$wc_product = $product;
		} elseif ( $product instanceof WDP_Product ) {
			$wc_product = $product->get_wc_product();
		} else {
			return false;
		}

		$applied_rules = 0;

		if ( ! $cart->add_product_to_calculate( $product, $qty ) ) {
			return false;
		}

		foreach ( $this->subscribers as $subscriber ) {
			$subscriber->start_product_calculation();
		}

		$rule_array = $this->rule_collection->to_array();
		foreach ( $rule_array as $rule ) {
			if ( $rule->apply_to_cart( $cart ) ) {
				$applied_rules ++;
			}

			foreach ( $this->subscribers as $subscriber ) {
				$subscriber->rule_calculated_product( $rule );
			}
		}

		foreach ( $this->subscribers as $subscriber ) {
			$subscriber->product_calculated( $wc_product, $qty, $this->get_tmp_item( $cart, $wc_product ) );
		}

		return $applied_rules ? $cart : false; // no new cart
	}

	/**
	 * @param WDP_Cart   $cart
	 * @param WC_Product $wc_product
	 *
	 * @return WDP_Cart_Item|false
	 */
	protected function get_tmp_item( $cart, $wc_product ) {
		$items   = $cart->get_temporary_items_by_product( $wc_product );
		if ( ! $items ) {
			return false;
		}

		$item = end( $items );

		return $item;
	}

	/**
	 * @param WDP_Cart $cart
	 * @param WDP_Rule[] $rule_array
	 *
	 * @return WDP_Cart|boolean
	 */
	public function process_cart_use_exact_rules( $cart, $rule_array ) {
		$applied_rules = 0;

		foreach ( $rule_array as $rule ) {
			if ( $rule->apply_to_cart( $cart ) ) {
				$applied_rules ++;
			}
		}

		return $applied_rules ? $cart : false; // no new cart
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $product WDP_Product|integer
	 * @param $qty
	 *
	 * @return null|WDP_Product
	 *
	 */
	public function apply_changes_to_product( $cart, $product, $qty ) {
		if ( is_null( $cart ) || ! $cart ) {
			return $product;
		}

		if ( ! is_a( $product, 'WDP_Product' ) ) {
			if ( is_integer( $product ) ) {
				try {
					$product = new WDP_Product( $product );
				} catch ( Exception $e ) {
					return null;
				}
			} else {
				return null;
			}
		}

		if ( ! $product ) {
			return null;
		}

		$items = $cart->get_temporary_items_by_product( $product->get_wc_product() );
		if ( ! $items ) {
			return $product;
		}

		$all_items = $cart->get_items_by_product( $product->get_wc_product() );
		$total_qty = array_sum( array_map( function ( $item ) {
			return $item->get_qty();
		}, $all_items ) );
		$total_tmp_qty = array_sum( array_map( function ( $item ) {
			return $item->get_qty();
		}, $items ) );

		$item = end($items);
		/**
		 * @var $item WDP_Cart_Item
		 */

		$product->set_price( $item->get_initial_price() );
		$product->set_new_price( $item->get_calculated_price() );
		$product->apply_initial_data( $cart->get_item_initial_data( $item ) );
		$product->set_index_number( $total_qty );
		$product->set_qty_already_in_cart( $total_qty - $total_tmp_qty );

		if ( (boolean) count( $item->get_history() ) ) {
			$product->rules_applied();
			$product->apply_history( $item->get_history() );

			$total_adjustments = array_sum( array_map( function ( $amounts ) {
				return array_sum( $amounts );
			}, $item->get_history() ) );

			if ( $total_adjustments > 0 ) {
				$product->discount_applied();
			}
		}

		/**
		 * Min bulk price calculation
		 * Should be located after accepting initial price from @var WDP_Cart_Item $item
		 */
		if ( $item->get_first_discount_range_rule() ) {
			$applied_bulk_rule = $this->rule_collection->get_exact( $item->get_first_discount_range_rule() )->get_first();
			/**
			 * @var $applied_bulk_rule WDP_Rule
			 */
			$this->calculate_min_bulk_price( $product, $applied_bulk_rule, $cart->get_context() );
		}


		return $product;
	}

	/**
	 * @param $product WDP_Product
	 * @param $bulk_rule WDP_Rule
	 * @param $context WDP_Cart_Context
	 */
	public function calculate_min_bulk_price( $product, $bulk_rule, $context ) {
		$data       = $bulk_rule->get_bulk_details( $context );
		$wc_product = $product->get_wc_product();

//		$options    = WDP_Helpers::get_settings();
//		$price_mode = $options['discount_for_onsale'];
//		$price      = apply_filters( "wdp_get_product_price", (float) $product->get_price(), $wc_product, $price_mode, array() );
		$price     = (float) $product->get_price();

		$min_price = null;

		foreach ( $data['ranges'] as $line ) {
			$new_price  = null;
			$line_value = (float) $line['value'];

			if ( 'price__fixed' === $data['discount'] ) {
				$new_price = wc_get_price_to_display( $wc_product, array( 'price' => $line_value ) );
			} elseif ( 'discount__amount' === $data['discount'] ) {
				$new_price = $price - $line_value;
			} elseif ( 'discount__percentage' === $data['discount'] ) {
				$new_price = $price - $price * $line_value / 100;
			}

			if ( ! is_null( $min_price ) ) {
				if ( ! is_null( $new_price ) && $new_price < $min_price ) {
					$min_price = $new_price;
				}
			} else {
				$min_price = $new_price;
			}
		}

		if ( ! is_null( $min_price ) ) {
			$product->affected_by_bulk();
			$product->set_min_bulk_price( $min_price );
		}
	}

	/**
	 * @param WDP_Cart $cart
	 * @param WC_Product|int $the_product
	 * @param int $qty
	 *
	 * @return array|bool
	 */
	public function get_active_rules_for_product( $cart, $the_product, $qty ) {
		$product = false;

		if ( ! is_a( $the_product, 'WC_Product' ) ) {
			if ( is_integer( $the_product ) ) {
				$product = WDP_Object_Cache::get_instance()->get_wc_product( $the_product );
			}
		} else {
			$product = $the_product;
		}

		if ( ! $product ) {
			return false;
		}

		if ( ! $cart->add_product_to_calculate( $product, $qty ) ) {
			return false;
		}

		$rules = $this->get_applied_rules( $cart );

		return $rules;
	}

	/**
	 * @param $cart WDP_Cart
	 *
	 * @return WDP_Rule[]
	 */
	public function get_applied_rules( $cart ) {
		$this->cart = $cart;

		$rule_array = $this->rule_collection->to_array();
		$applied_rules = array();
		foreach ( $rule_array as $rule ) {
			if( $rule->apply_to_cart( $cart ) ) {
				$applied_rules[] = $rule;
			}
		}

		return $applied_rules;
	}

	public function get_first_discount_range_rule_supports_product_table( $cart, $product_id ) {
		$matched_rules = $this->find_product_matches( $cart, $product_id );
		if ( $matched_rules->is_empty() ) {
			return null;
		}

		$bulk_rules = $matched_rules->with_bulk();
		if ( $bulk_rules->is_empty() ) {
			return null;
		}

		foreach ( $bulk_rules->to_array() as $rule ) {
			/**
			 * @var WDP_Rule $rule
			 */
			if ( $rule->get_bulk_details( $cart->get_context() ) && $rule->support_output_discount_table() ) {
				return $rule;
			}
		}

		return null;
	}

	public function hash() {
		return md5( json_encode( $this->rule_collection->to_array() ) );
	}

}