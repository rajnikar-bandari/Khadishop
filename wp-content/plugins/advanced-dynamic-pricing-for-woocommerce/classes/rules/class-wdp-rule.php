<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule {
	private $rule_data = array();

	/**
	 * @var WDP_Cart_Adjustment_Interface[] array
	 */
	protected $cart_adjustments = array();

	/**
	 * @var WDP_Condition[]
	 */
	protected $conditions = array();

	/**
	 * @var WDP_Limit[]
	 */
	protected $limits = array();

	/**
	 * @var WC_Product[]
	 */
	protected $loaded_products = array();

	/**
	 * @var WDP_Rule_Range_Adjustments
	 */
	protected $rule_range_adjustments;

	/**
	 * @var float
	 */
	protected $exec_rule_start;

	/**
	 * Used for compatibility with currency plugins
	 *
	 * @var string
	 */
	protected $currency;


	/**
	 * @var float
	 */
	protected $last_exec_time;

	private $status;

	public static $STATUS_LIMITS_NOT_PASSED = 'limits_not_passed';
	public static $STATUS_CONDITIONS_NOT_PASSED = 'conditions_not_passed';
	public static $STATUS_FILTERS_NOT_PASSED = 'filters_not_passed';
	public static $STATUS_OK_NO_ITEM_CHANGES = 'ok_no_item_changes';
	public static $STATUS_OK_ITEM_CHANGES = 'ok_item_changes';
	public static $STATUS_OK_WITH_TMP_CHANGES = 'ok_with_tmp_changes';

	public function __construct( $rule_data ) {
		$this->currency = get_woocommerce_currency();

		$rule_data = apply_filters( 'wdp_rule_construct', $rule_data );
		$this->rule_data = $rule_data;

		if ( ! empty( $rule_data['cart_adjustments'] ) ) {
			foreach ( $rule_data['cart_adjustments'] as $cart_adj_data ) {
				try {
					$cart_adj                 = WDP_Cart_Adj_Registry::get_instance()->create_adj( $cart_adj_data );
					$this->cart_adjustments[] = $cart_adj;
				} catch ( Exception $exception ) {
				}
			}
		}

		if ( ! empty( $rule_data['conditions'] ) ) {
			foreach ( $rule_data['conditions'] as $condition_data ) {
				try {
					$condition          = WDP_Condition_Registry::get_instance()->create_condition( $condition_data );
					$this->conditions[] = $condition;
				} catch ( Exception $exception ) {
				}
			}
		}

		if ( ! empty( $rule_data['limits'] ) ) {
			foreach ( $rule_data['limits'] as $limit_data ) {
				try {
					$limit          = WDP_Limit_Registry::get_instance()->create_limit( $limit_data );
					$this->limits[] = $limit;
				} catch ( Exception $exception ) {
				}
			}
		}

		if ( $this->has_bulk() ) {
			$this->rule_range_adjustments = new WDP_Rule_Range_Adjustments( $this->rule_data );
		}

		do_action( 'wdp_rule_init', $this);

		// TODO move to compatibility
		if ( isset( $GLOBALS['sitepress'] ) && $sitepress = $GLOBALS['sitepress'] ) {
			$this->translate( $sitepress->get_current_language() );
		}
	}

	public function set_currency( $currency, $rate ) {
		$this->currency = $currency;

		if ( $this->has_product_adjustment() ) {
			$adj_type = isset( $this->rule_data['product_adjustments']['type'] ) ? $this->rule_data['product_adjustments']['type'] : false;

			$adjustment_data = isset( $this->rule_data['product_adjustments'][ $adj_type ] ) ? $this->rule_data['product_adjustments'][ $adj_type ] : false;
			if ( ! empty( $adjustment_data ) ) {
				if ( isset( $this->rule_data['product_adjustments']['max_discount_sum'] ) ) {
					if ( "" !== $this->rule_data['product_adjustments']['max_discount_sum'] ) {
						$this->rule_data['product_adjustments']['max_discount_sum'] *= $rate;
					}
				}

				if ( 'total' === $adj_type ) {
					$adjustment = $adjustment_data;
					if ( ! empty( $adjustment ) && ! empty( $adjustment['type'] ) && $adjustment['type'] !== 'discount__percentage' ) {
						$this->rule_data['product_adjustments'][ $adj_type ]['value'] *= $rate;
					}
				} elseif ( 'split' === $adj_type ) {
					$adjustments = $adjustment_data;

					foreach ( $adjustments as $index => $adjustment ) {
						if ( ! empty( $adjustment ) && ! empty( $adjustment['type'] ) && $adjustment['type'] !== 'discount__percentage' ) {
							$this->rule_data['product_adjustments'][ $adj_type ][ $index ]['value'] *= $rate;
						}
					}
				}
			}
		}

		if ( $this->has_bulk() ) {
			$this->rule_range_adjustments->change_currency( $rate );
		}

		if ( ! empty( $this->rule_data['role_discounts']['rows'] ) && is_array( $this->rule_data['role_discounts']['rows'] ) ) {
			$rows = $this->rule_data['role_discounts']['rows'];

			foreach ( $rows as $index => $row ) {
				if ( isset( $row['discount_type'], $row['discount_value'] ) && $row['discount_type'] !== 'discount__percentage' ) {
					$this->rule_data['role_discounts']['rows'][ $index ]['discount_value'] *= (float) $rate;
				}
			}
		}

		foreach ( $this->cart_adjustments as $cart_adjustment ) {
			$cart_adjustment->multiply_amounts( $rate );
		}

		foreach ( $this->conditions as $cart_condition ) {
			$cart_condition->multiply_amounts( $rate );
		}
	}

	public function translate( $language_code ) {
		$filter_translator = new WDP_Rule_Filter_Translator();

		if ( isset( $this->rule_data['filters'] ) ) {
			foreach ( $this->rule_data['filters'] as &$filter ) {
				if ( empty( $filter['value'] ) ) {
					continue;
				}

				$filter['value'] = $filter_translator->translate_by_type( $filter['type'], $filter['value'], $language_code );
				$filter['product_exclude']['values'] = $filter_translator->translate_product( $filter['product_exclude']['values'], $language_code );
			}
		};

		if ( $this->has_free_products() ) {
			$free_products = $this->rule_data['get_products']['value'];
			foreach ( $free_products as &$free_product ) {
				$value = $free_product['value'];
				$value = $filter_translator->translate_product( $value, $language_code );
				$free_product['value'] = $value;
			}
			$this->rule_data['get_products']['value'] = $free_products;
		}

		if ( $this->has_bulk() ) {
			$this->rule_range_adjustments->translate( $language_code );
		}


		foreach ( $this->conditions as $cart_condition ) {
			$cart_condition->translate( $language_code );
		}
	}

	public function get_currency() {
		return $this->currency;
	}

	public function get_rule_data() {
		return $this->rule_data;
	}

	public function has_bulk() {
		return ! empty( $this->rule_data['bulk_adjustments']['ranges'] );
	}

	public function get_needle_cary_sort() {
		return isset( $this->rule_data['options']['apply_to'] ) ? $this->rule_data['options']['apply_to'] : 'expensive';
	}

	public function has_roles_discount() {
		return ! empty( $this->rule_data['role_discounts']['rows'] );
	}

	public function get_id() {
		return (int) $this->rule_data['id'];
	}

	public function get_ad_title() {
		return $this->rule_data['title'];
	}

	/**
	 * @param $cart WDP_Cart
	 *
	 * @return boolean
	 */
	public function apply_to_cart( $cart ) {
		$this->exec_rule_start = microtime( true );
		$this->last_exec_time = null;

		global $wp_filter;
		$current_wp_filter = $wp_filter;

		$result = false;
		try {
			$result = $this->process_apply_to_cart( $cart );
		} catch ( WDP_Rule_Execution_Timeout $e ) {
			$this->leave_disable_notice();
			WDP_Database::mark_as_disabled_by_plugin( $this->get_id() );
		}

		$wp_filter = $current_wp_filter;

		$this->last_exec_time = microtime( true ) - $this->exec_rule_start;

		return $result;
	}

	/**
	 * @param $cart WDP_Cart
	 * @throws WDP_Rule_Execution_Timeout
	 *
	 * @return boolean
	 */
	private function process_apply_to_cart( $cart ) {
		$this->status          = null;

		$this->rule_data = apply_filters( 'wdp_before_apply_rule', $this->rule_data, $cart );

		if ( apply_filters( 'wdp_rule_force_disable', false, $this, $this->rule_data, $cart ) ) {
			return false;
		}

		if ( ! $this->check_limits( $cart ) ) {
			$this->status = $this::$STATUS_LIMITS_NOT_PASSED;
			return false;
		}

		$this->check_execution_time( $cart->get_context() );

		if ( ! $this->check_conditions( $cart ) ) {
			$this->status = $this::$STATUS_CONDITIONS_NOT_PASSED;
			return false;
		}

		$this->check_execution_time( $cart->get_context() );

		$set_collection = $this->create_sets($cart);

		if ( ! $set_collection ) {
			$this->status = $this::$STATUS_FILTERS_NOT_PASSED;

			return false;
		}

		foreach ( $set_collection->get_sets() as $index => &$set ) {
			$this->apply_product_adjustment( $set );
		}

		$this->check_execution_time( $cart->get_context() );

		$this->add_free_products( $cart, $set_collection );

		$this->check_execution_time( $cart->get_context() );

		if ( $set_collection->is_empty() ) {
			$this->status = $this::$STATUS_FILTERS_NOT_PASSED;
			return false;
		}

		if ( $this->get_sortable_apply_mode() === 'consistently' ) {
			if ( ! empty( $this->rule_data['sortable_blocks_priority'] ) && is_array( $this->rule_data['sortable_blocks_priority'] ) ) {
				$roles_applied = false;
				$do_not_apply_bulk_after_role = $this->get_is_dont_apply_bulk_if_roles_matched();

				$initial_collection = clone $set_collection;

				foreach ( $this->rule_data['sortable_blocks_priority'] as $block_name ) {
					if ( 'roles' == $block_name ) {
						$this->apply_roles_discount( $cart, $set_collection );
						$roles_applied = ! $this->is_set_collections_equal( $initial_collection, $set_collection );
					} elseif ( 'bulk-adjustments' == $block_name ) {
						if ( $do_not_apply_bulk_after_role && $roles_applied ) {
							continue;
						}

						$this->apply_bulk_adjustment( $cart, $set_collection );

						if ( $set_collection instanceof WDP_Cart_Items_Collection ) {
							$set_collection = $this->collect_sets( $set_collection->get_items(), $cart );
						}
					}
				}
			} else {
				$this->apply_roles_discount( $cart, $set_collection );
				$this->apply_bulk_adjustment( $cart, $set_collection );
			}
		} elseif ($this->get_sortable_apply_mode() === 'min_price_between' || $this->get_sortable_apply_mode() === 'max_price_between') {
			$role_set_collection = clone $set_collection;
			$this->apply_roles_discount( $cart, $role_set_collection );

			$discount_range_set_collection = clone $set_collection;
			$this->apply_bulk_adjustment( $cart, $discount_range_set_collection );
			if ( $discount_range_set_collection instanceof WDP_Cart_Items_Collection ) {
				$discount_range_set_collection = $this->collect_sets( $discount_range_set_collection->get_items(),
					$cart );
			}

			$discount_range_sets = $discount_range_set_collection->get_sets();

			$set_collection->purge();
			foreach ( $role_set_collection->get_sets() as $role_set ) {
				$matched = false;
				foreach ( $discount_range_sets as $index => $discount_range_set ) {
					if ( $role_set->calc_no_price_hash() !== $discount_range_set->calc_no_price_hash() ) {
						continue;
					}

					$comparison = $this->get_sortable_apply_mode() === 'min_price_between' ? "min" : "max";

					if ( $comparison( $role_set->get_total_price(), $discount_range_set->get_total_price() ) === $role_set->get_total_price() ) {
						$set_collection->add( $role_set );
					} else {
						$set_collection->add( $discount_range_set );
					}

					unset( $discount_range_sets[ $index ] );
					$matched = true;
					break;
				}

				if ( ! $matched ) {
					$set_collection->add( $role_set );
				}
			}
		}

		$this->check_execution_time( $cart->get_context() );

		$this->apply_cart_adjustment( $cart, $set_collection );

		$this->check_execution_time( $cart->get_context() );

		if ( $set_collection instanceof WDP_Cart_Set_Collection ) {
			$collection = $this->decompose_sets( $set_collection );
		} else {
			$collection = $set_collection;
		}

		$this->check_execution_time( $cart->get_context() );

		$this->apply_changes_to_cart( $cart, $collection );

		return true;
	}

	private function get_sortable_apply_mode() {
		return ! empty( $this->rule_data['additional']['sortable_apply_mode'] ) ? $this->rule_data['additional']['sortable_apply_mode'] : 'consistently';
	}

	public function get_last_apply_status() {
		return $this->status;
	}

	private function get_is_dont_apply_bulk_if_roles_matched() {
		return isset( $this->rule_data['role_discounts']['dont_apply_bulk_if_roles_matched'] ) ? wc_string_to_bool( $this->rule_data['role_discounts']['dont_apply_bulk_if_roles_matched'] ) : false;
	}

	/**
	 * @param $collection_a WDP_Cart_Set_Collection
	 * @param $collection_b WDP_Cart_Set_Collection
	 *
	 * @return boolean
	 */
	private function is_set_collections_equal( $collection_a, $collection_b ) {
		return $collection_a->get_hash() === $collection_b->get_hash();
	}

	protected function check_limits( $cart ) {
		foreach ( $this->limits as $limit ) {
			if ( ! $limit->check( $cart, $this->get_id() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param $cart WDP_Cart
	 *
	 * @return bool
	 */
	protected function check_conditions( $cart ) {
		if ( empty( $this->conditions ) ) {
			return true;
		}

		$relationship = $this->get_conditions_relationship( $this->rule_data );
		$result       = false;

		foreach ( $this->conditions as $condition ) {
			if ( $condition->check( $cart ) ) {
				// check_conditions always true if relationship not 'and' and at least one condition checked
				$result = true;
			} elseif ( 'and' == $relationship ) {
				return false;
			}
		}

		return $result;
	}

	protected function check_conditions_for_a_match( $cart ) {
		if ( empty( $this->conditions ) ) {
			return true;
		}
		$relationship = $this->get_conditions_relationship( $this->rule_data );
		$result       = false;
		foreach ( $this->conditions as $condition ) {
			if ( ! $condition->match( $cart ) ) {
				if ( 'and' == $relationship ) {
					return false;
				}
			} else {
				if ( 'or' == $relationship ) {
					return true;
				} else {
					$result = true;
				}
			}
		}

		return $result;
	}

	protected function get_conditions_relationship( $rule_data ) {
		return ! empty( $rule_data['additional']['conditions_relationship'] ) ? $rule_data['additional']['conditions_relationship'] : 'and';
	}

	protected function get_roles_rows( $rule_data ) {
		if ( empty( $rule_data['role_discounts']['rows'] ) || ! is_array( $rule_data['role_discounts']['rows'] ) ) {
			return array();
		}
		$rows = $rule_data['role_discounts']['rows'];

		foreach ( $rows as $index => $row ) {
			$remove = false;

			if ( ! isset( $row['discount_type'], $row['discount_value'] ) ) {
				$remove = true;
			} elseif ( ! isset( $row['roles'] ) ) {
				$remove = true;
			} else {
				$remove = ! is_array( $row['roles'] );
			}

			if ( $remove ) {
				unset( $rows[ $index ] );
			}
		}

		return array_values( $rows );
	}

	protected function get_attempt_limit() {
		return isset( $this->rule_data['options']['repeat'] ) ? (int) $this->rule_data['options']['repeat'] : - 1;
	}

	protected function get_filters() {
		$filters = isset( $this->rule_data['filters'] ) ? $this->rule_data['filters'] : array();

		if ( empty( $filters ) ) {
			$filters[] = array(
				'qty'     => 1,
				'qty_end' => 1,
				'type'    => 'products',
				'method'  => 'any',
				'value'   => array(),
			);
		}

		foreach ( $filters as &$filter ) {
			if ( ! isset( $filter['qty_end'] ) ) {
				$filter['qty_end'] = $filter['qty'];
			}

			if ( empty( $filter['value'] ) ) {
				$filter['value'] = array();
			}
		}

		return $filters;
	}

	/**
	 * @param $cart WDP_Cart
	 * @throws WDP_Rule_Execution_Timeout
	 *
	 * @return WDP_Cart_Set_Collection|false
	 */
	protected function create_sets( &$cart ) {
		if ( ! $cart_mutable_items = $cart->get_mutable_items() ) {
			return false;
		}

		uasort( $cart_mutable_items, array( $this, 'sort_items' ) );
		$cart_mutable_items = array_values( $cart_mutable_items);

		$cart->purge_mutable_items();

		return $this->collect_sets( $cart_mutable_items, $cart );
	}

	/**
	 * @param WDP_Cart_Item[] $cart_items
	 * @param WDP_Cart        $cart
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	protected function collect_sets( $cart_items, $cart ) {
		$set_collector = new WDP_Rule_Set_Collector( $this->get_id() );
		$set_collector->register_check_execution_time_function( array( $this, 'check_execution_time' ), $cart->get_context() );
		$set_collector->set_limit( $this->get_attempt_limit() );
		$set_collector->add_items( $cart_items );
		$set_collector->apply_filters( $cart, $this->get_filters() );

		return $set_collector->collect_sets( $cart, 'legacy' );
	}

	protected function sort_items( $item1, $item2 ) {
		/**
		 * @var $item1 WDP_Cart_Item
		 * @var $item2 WDP_Cart_Item
		 */
		$price1 = $item1->get_price();
		$price2 = $item2->get_price();

		if ( 'cheap' === $this->get_needle_cary_sort() ) {
			return $price1 - $price2;
		} elseif ( 'expensive' === $this->get_needle_cary_sort() ) {
			return $price2 - $price1;
		}

		return 0;
	}

	public function has_product_adjustment() {
		$adj_type = isset( $this->rule_data['product_adjustments']['type'] ) ? $this->rule_data['product_adjustments']['type'] : false;
		if ( ! $adj_type ) {
			return false;
		}

		$adjustment_data = isset( $this->rule_data['product_adjustments'][ $adj_type ] ) ? $this->rule_data['product_adjustments'][ $adj_type ] : null;
		if ( is_null( $adjustment_data ) ) {
			return false;
		}

		if ( 'total' === $adj_type ) {
			$adjustment = $adjustment_data;
			if ( empty( $adjustment ) || empty( $adjustment['type'] ) ) {
				return false;
			}
		} elseif ( 'split' === $adj_type ) {
			if ( count( $adjustment_data ) === 0 ) {
				return false;
			}

			$adjustment = reset( $adjustment_data );
			if ( empty( $adjustment ) || empty( $adjustment['type'] ) ) {
				return false;
			}
		}


		return true;
	}

	/**
	 * @param $set WDP_Cart_Set
	 */
	protected function apply_product_adjustment( &$set ) {
		if ( ! $this->has_product_adjustment() ) {
			return;
		}

		$adj_type = isset( $this->rule_data['product_adjustments']['type'] ) ? $this->rule_data['product_adjustments']['type'] : false;
		if ( ! $adj_type ) {
			return;
		}

		$adjustment_data = isset( $this->rule_data['product_adjustments'][ $adj_type ] ) ? $this->rule_data['product_adjustments'][ $adj_type ] : false;
		if ( ! $adjustment_data ) {
			return;
		}

		$limit = isset( $this->rule_data['product_adjustments']['max_discount_sum'] ) ? $this->rule_data['product_adjustments']['max_discount_sum'] : null;

		$price_calculator = new WDP_Price_Calculator();
		if ( $limit !== null ) {
			$price_calculator->set_discount_total_limit( $limit );
		}

		if ( 'total' === $adj_type ) {
			$adjustment = $adjustment_data;
			if ( empty( $adjustment ) || empty( $adjustment['type'] ) ) {
				return;
			}
			$set = $price_calculator->set_type( $adjustment['type'] )->set_value( $adjustment['value'] )->apply_to_set()->calculate_price_for_set( $set );
		} elseif ( 'split' === $adj_type ) {
			$adjustments = $adjustment_data;

			$iterator = new MultipleIterator;
			$iterator->attachIterator( new ArrayIterator( $adjustments ) );
			$iterator->attachIterator( new ArrayIterator( range( 0, count( $set->get_items() ) ) ) );

			foreach ( $iterator as $list ) {
				$adjustment = $list[0];
				$index      = $list[1];
				$items      = $set->get_items_by_position( $index );

				/**
				 * @var $item WDP_Cart_Item
				 */
				if ( empty( $adjustment['type'] ) ) {
					continue;
				}

				$items  = $price_calculator->set_type( $adjustment['type'] )->set_value( $adjustment['value'] )->calculate_price_for_items( $this->get_id(), $items );
				if ( ! $items ) {
					continue;
				}

				$prices = array();
				foreach ( $items as $item ) {
					/**
					 * @var $item WDP_Cart_Item
					 */
					$prices[] = $item->get_price();
				}

				$set->set_price_for_items_by_position( $index, $prices );
			}
		}


		return;
	}

	/**
	 * @param WDP_Cart_Set_Collection $set_collection
	 * @param WDP_Cart $cart
	 *
	 * @return int
	 */
	protected function get_deal_attempt_limit( $set_collection, $cart ) {
		$attempts_limit = 0;

		if ( $this->rule_data['get_products']['repeat'] === 'based_on_subtotal' ) {
			if ( isset( $this->rule_data['get_products']['repeat_subtotal'] ) ) {
				$repeat_subtotal = abs( (float) $this->rule_data['get_products']['repeat_subtotal'] );
				$subtotal = $cart->get_initial_cart_subtotal();

				$attempts_limit = (int) ( $subtotal / $repeat_subtotal );
			}
		} else {
			$attempts_limit = (int) $this->rule_data['get_products']['repeat'];
			if ( $attempts_limit < 0 ) {
				$attempts_limit = INF;
			}

			$attempts_limit = min( $set_collection->get_total_sets_qty(), $attempts_limit );
		}

		return $attempts_limit;
	}

	public function has_free_products() {
		return isset( $this->rule_data['get_products']['value'] ) ? $this->rule_data['get_products']['value'] : false;
	}

	/**
	 * @param $set_collection WDP_Cart_Set_Collection
	 * @param $cart WDP_Cart
	 * @param $attempt integer
	 * @param $index integer
	 *
	 * @return null|WC_Product
	 */
	protected function find_product_by_attempt_value_in_set_collection( $set_collection, $cart, $attempt, $index ) {
		$total_qty = 0;

		foreach ( $set_collection->get_sets() as $set ) {
			foreach ( $set->get_items_by_position( $index ) as $item ) {
				/**
				 * @var $item WDP_Cart_Item
				 */
				$items_qty           = $item->get_qty() * $set->get_qty();
				$total_qty_with_item = $total_qty + $items_qty;

				if ( $total_qty < $attempt && $attempt <= $total_qty_with_item ) {
					$cart_data = $cart->get_item_data_by_hash( $item->get_hash() );

					return isset( $cart_data['data'] ) ? $cart_data['data'] : null;
				}

				$total_qty = $total_qty_with_item;
			}
		}

		return null;
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 */
	protected function add_free_products( &$cart, $set_collection ) {
		if ( ! $this->has_free_products() ) {
			return;
		}

		$adjustments = isset( $this->rule_data['get_products']['value'] ) ? $this->rule_data['get_products']['value'] : false;

		if ( empty( $adjustments ) ) {
			return;
		}

		$temporary_qties = $this->calculate_temporary_qties( $cart, $set_collection );

		$deal_apply_count = $this->get_deal_attempt_limit( $set_collection, $cart );

		for ( $attempt = 1; $attempt <= $deal_apply_count; $attempt ++ ) {
			foreach ( $adjustments as $deal_index => $deal_adjustment ) {
				$value = isset( $deal_adjustment['value'] ) ? $deal_adjustment['value'] : array();
				$use_product_from_filter = isset( $deal_adjustment['use_product_from_filter'] ) ? 'on' === $deal_adjustment['use_product_from_filter'] : false;

				if ( $use_product_from_filter ) {
					$filter_product = $this->find_product_by_attempt_value_in_set_collection( $set_collection, $cart, $attempt, $deal_index );

					if ( $filter_product && $filter_product instanceof WC_Product ) {
						$value = array( $filter_product->get_id() );
					}
				}

				if ( empty( $deal_adjustment['qty'] ) || empty( $value ) ) {
					continue;
				}
				$qty = (float) $deal_adjustment['qty'];

				foreach ( $value as $deal_adjustment_product_id ) {
					if ( $qty < 1 ) {
						break;
					}

//					$product = $this->get_product( $deal_adjustment_product_id );
					$product = WDP_Object_Cache::get_instance()->get_wc_product( $deal_adjustment_product_id );
					if ( ! $product ) {
						continue;
					}

					$qty_used = $cart->get_qty_used( $deal_adjustment_product_id );
					if ( isset( $temporary_qties[ $deal_adjustment_product_id ] ) ) {
						$qty_used += $temporary_qties[ $deal_adjustment_product_id ];
					}

					$qty_to_add = $this->get_product_qty_available_for_sale( $product, $qty_used, $qty );

					if ( $qty_to_add > 0 ) {
						$cart->gift_product( $this->get_id(), $product, $qty_to_add );
						$qty -= $qty_to_add;
					}
				}

			}
		}
	}

	/**
	 * @param $product WC_Product
	 * @param $qty_used integer
	 * @param $qty_required integer
	 *
	 * @return int
	 */
	protected function get_product_qty_available_for_sale( $product, $qty_used, $qty_required ) {
		$available_qty = 0;
		if ( $product->managing_stock() ) {
			if ( $product->backorders_allowed() ) {
				$available_qty = $qty_required;
			} else {
				$available_for_now = $product->get_stock_quantity() - $qty_used;
				$available_qty     = $available_for_now >= $qty_required ? $qty_required : $available_for_now;
			}
		} else {
			$available_qty = $qty_required;
		}

		return $available_qty;
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return array
	 */
	protected function calculate_temporary_qties( $cart, $set_collection ) {
		$temporary_qties = array();

		$collection = $this->decompose_sets( $set_collection );
		foreach ( $collection->get_items() as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			if ( $item->is_temporary() ) {
				$original_item = $cart->get_item_data_by_hash( $item->get_hash() );
				$product_id    = $original_item['product_id'];

				if ( ! isset( $temporary_qties[ $product_id ] ) ) {
					$temporary_qties[ $product_id ] = 0;
				}
				$temporary_qties[ $product_id ] += $item->get_qty();
			}
		}

		return $temporary_qties;
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	protected function apply_roles_discount( $cart, &$set_collection ) {
		if ( ! $this->has_roles_discount() ) {
			return $set_collection;
		}

		if ( ! ( $current_user_roles = $cart->get_context()->get_customer_roles() ) ) {
			return $set_collection;
		}

		foreach ( $this->get_roles_rows( $this->rule_data ) as $roles_rule ) {
			if ( ! count( array_intersect( $roles_rule['roles'], $current_user_roles ) ) ) {
				continue;
			}

			$price_calculator = new WDP_Price_Calculator();
			$price_calculator->set_type( $roles_rule['discount_type'] )->set_value( $roles_rule['discount_value'] );
			$price_calculator->apply_to_set();

			$sets = $set_collection->get_sets();
			$set_collection->purge();
			foreach ( $sets as $set ) {
				$set_collection->add( $price_calculator->calculate_price_for_set( $set ) );
			}
		}

		return $set_collection;
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 */
	protected function apply_bulk_adjustment( $cart, &$set_collection ) {
		if ( ! $this->has_bulk() || is_null( $this->rule_range_adjustments ) ) {
			return;
		}

		foreach ( $set_collection->get_sets() as $set ) {
			$set->set_first_discount_range_rule( $this->get_id() );
		}

		$set_collection = $this->rule_range_adjustments->apply_adjustments( $cart, $set_collection );
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 */
	protected function apply_cart_adjustment( &$cart, $set_collection ) {
		foreach ( $this->cart_adjustments as $cart_adjustment ) {
			/**
			 * @var $cart_adjustment WDP_Cart_Adjustment
			 */
			$cart_adjustment->apply_to_cart( $cart, $set_collection, $this->get_id() );
		}
	}

	/**
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Items_Collection
	 */
	protected function decompose_sets( $set_collection ) {
		$items_collection = new WDP_Cart_Items_Collection( $this->get_id() );
		foreach ( $set_collection->get_sets() as $set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			foreach ( $set->get_items() as $hash => $item ) {
				/**
				 * @var $item WDP_Cart_Item
				 */
				$new_item = clone $item;
				$new_item->set_qty( $item->get_qty() * $set->get_qty() );
				$items_collection->add( $new_item );
			}
		}

		return $items_collection;
	}

	protected function is_replace_free_products_with_discount() {
		return isset( $this->rule_data['additional']['is_replace_free_products_with_discount'] ) ? $this->rule_data['additional']['is_replace_free_products_with_discount'] : false;
	}

	protected function get_adjustment_free_products_replace_name() {
		$coupon_name = false;

		if ( $this->is_replace_free_products_with_discount() ) {
			$coupon_name = isset( $this->rule_data['additional']['free_products_replace_name'] ) ? $this->rule_data['additional']['free_products_replace_name'] : $coupon_name;
		}

		return $coupon_name;
	}

	protected function is_replace_discounts() {
		return isset( $this->rule_data['additional']['is_replace'] ) ? $this->rule_data['additional']['is_replace'] : false;
	}

	protected function get_adjustment_replace_name() {
		$coupon_name = false;

		if ( $this->is_replace_discounts() ) {
			$coupon_name = isset( $this->rule_data['additional']['replace_name'] ) ? $this->rule_data['additional']['replace_name'] : $coupon_name;
		}

		return $coupon_name;
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $collection WDP_Cart_Items_Collection
	 */
	protected function apply_changes_to_cart( &$cart, $collection ) {
		$this->status = $this::$STATUS_OK_NO_ITEM_CHANGES;

		if ( $this->is_exclusive() ) {
			$collection->make_items_immutable();
		}

		foreach ( $collection->get_items() as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */

			if ( $adjustment_name = $this->get_adjustment_replace_name() ) {
				$item->exclude_rule_adjustments( $this->get_id(), $adjustment_name );
			} else {
				if ( $this->status !== $this::$STATUS_OK_ITEM_CHANGES && count( $item->get_history() ) ) {
					$this->status = $this::$STATUS_OK_ITEM_CHANGES;
				}
			}

			$cart->add_to_cart( $item );
		}

		$cart->replace_gift_products_with_cart_adjustment( $this->get_id(), $this->get_adjustment_free_products_replace_name() );

		$cart->destroy_empty_items();
	}

	public function is_exclusive() {
		return (bool) $this->rule_data['exclusive'];
	}

	public function get_priority() {
		return (int) $this->rule_data['priority'];
	}

	/**
	 * Legacy function
	 *
	 * @return array
	 */
	public function get_product_dependencies() {
		return $this->get_rule_filters();
	}

	public function get_rule_filters() {
		$dependencies = array();
		foreach ( $this->get_filters() as $filter ) {
			$dependencies[] = array(
				'qty'     => $filter['qty'],
				'qty_end' => $filter['qty_end'],
				'type'    => $filter['type'],
				'method'  => $filter['method'],
				'values'  => $filter['value'],
			);
		}

		return $dependencies;
	}

	/**
	 * @param $context WDP_Cart_Context
	 *
	 * @return array|bool
	 */
	public function get_bulk_details( $context ) {
		if ( ! $this->has_bulk() ) {
			return array();
		}

		$bulk   = $this->maybe_update_bulk_prices( $context );
		$ranges = $bulk['ranges'];

		$ret = array(
			'type'          => $bulk['type'],
			'discount'      => $bulk['discount_type'],
			'ranges'        => array(),
			'table_message' => $bulk['table_message'],
		);
		foreach ( $ranges as $range ) {
			$ret['ranges'][] = array(
				'from'  => $range['from'],
				'to'    => $range['to'],
				'value' => $range['value'],
			);
		}

		return $ret;
	}

	/**
	 * Change bulk prices according ONLY roles rules.
	 * Apply only if roles has larger priority and bulk adjustments type is fixed price.
	 * Affected only in bulk table.
	 * We need not to apply other rule sections because fixed price from bulk overrides them.
	 * Be advised, you must rework this method if order of submethods in "apply_to_cart" has been changed.
	 *
	 * @return array
	 * @var $context WDP_Cart_Context
	 *
	 */
	private function maybe_update_bulk_prices( $context ) {
		$bulk = $this->rule_data['bulk_adjustments'];
		if ( empty( $bulk['discount_type'] ) || "price__fixed" !== $bulk['discount_type'] ) {
			return $bulk;
		}

		$update = false;
		if ( ! empty( $this->rule_data['sortable_blocks_priority'] ) && is_array( $this->rule_data['sortable_blocks_priority'] ) ) {
			$role_priority = - 1;
			$bulk_priority = - 1;

			foreach ( $this->rule_data['sortable_blocks_priority'] as $key => $priority_label ) {
				if ( "roles" == $priority_label ) {
					$role_priority = $key;
				} elseif ( "bulk-adjustments" == $priority_label ) {
					$bulk_priority = $key;
				}
			}

			if ( $role_priority !== - 1 && $bulk_priority !== - 1 && ( $role_priority > $bulk_priority ) ) {
				$update = true;
			}
		}


		if ( $update && ! empty( $bulk['ranges'] ) ) {
			$price_calculator = new WDP_Price_Calculator();

			$roles_rule = array();

			$roles_rules = $this->get_roles_rows( $this->rule_data );

			if ( ! $roles_rules ) {
				return $bulk;
			}

			if ( ! ( $current_user_roles = $context->get_customer_roles() ) ) {
				return $bulk;
			}

			foreach ( $roles_rules as $tmp_roles_rule ) {
				if ( empty( $tmp_roles_rule['roles'] ) ) {
					continue;
				}
				$roles = $tmp_roles_rule['roles'];
				if ( ! count( array_intersect( $roles, $current_user_roles ) ) ) {
					continue;
				}

				$roles_rule = $tmp_roles_rule;
			}

			foreach ( $bulk['ranges'] as &$range ) {
				if ( empty( $range['value'] ) || ! isset( $roles_rule['discount_type'], $roles_rule['discount_value'] ) ) {
					continue;
				}

				$price_calculator->set_type( $roles_rule['discount_type'] )->set_value( $roles_rule['discount_value'] );
				$range['value'] = $price_calculator->calculate_single_price( $range['value'] );
			}

		}

		return $bulk;
	}

	/**
	 * @param                $cart WDP_Cart
	 * @param WC_Product|int $the_product
	 *
	 * @throws Exception
	 *
	 * @return bool
	 */
	public function is_product_matched( $cart, $the_product ) {
		$limits_verified = $this->check_limits( $cart );
		if ( ! $limits_verified ) {
			return false;
		}

		if ( ! wc_string_to_bool( $cart->get_context()->get_option( 'discount_table_ignores_conditions' ) ) ) {
			$conditions_verified = $this->check_conditions_for_a_match( $cart );
			if ( ! $conditions_verified ) {
				return false;
			}
		}

		$matched = $this->match_product_with_filters( $the_product, $cart );

		return $matched;
	}


	/**
	 * @param WC_Product|int $the_product
	 * @param WDP_Cart $cart
	 *
	 * @throws Exception
	 *
	 * @return bool
	 */
	public function match_product_with_filters( $the_product, $cart ) {
		$product = null;
		if ( $the_product instanceof WC_Product ) {
			$product = $the_product;
		} elseif ( is_numeric( $the_product ) ) {
			$product = WC()->product_factory->get_product( $the_product );
		}

		if ( ! $product ) {
			throw new Exception( __( 'Product does not exists', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}

		$product_filtering = WDP_Loader::get_product_filtering_class();
		$product_excluding = WDP_Loader::get_product_filtering_class();
		$product_exclude_enabled = $cart->get_context()->get_option( 'allow_to_exclude_products' );

		$matched = false;
		foreach ( $this->get_filters() as $filter_key => $filter ) {
			$product_filtering->prepare( $filter['type'], $filter['value'], $filter['method'] );

			$exclude_product_ids = ! empty( $filter['product_exclude']['values'] ) ? (array) $filter['product_exclude']['values'] : array();
			$exclude_on_wc_sale = ! empty( $filter['product_exclude']['on_wc_sale'] ) ? wc_string_to_bool( $filter['product_exclude']['on_wc_sale'] ) : false;
			$exclude_already_affected = ! empty( $filter['product_exclude']['already_affected'] ) ? wc_string_to_bool( $filter['product_exclude']['already_affected'] ) : false;
			$product_excluding->prepare( 'products', $exclude_product_ids, 'in_list' );

			/** We are not able to exclude already affected here */

			$product_exclude_on_wc_sale = $product_exclude_enabled && $exclude_on_wc_sale ? $product->is_on_sale('') : false;
			if ( $product_exclude_on_wc_sale ) {
				continue;
			}

			$product_exclude = $product_exclude_enabled ? $product_excluding->check_product_suitability( $product ) : false;
			if ( $product_exclude ) {
				continue;
			}

			$matched = $product_filtering->check_product_suitability( $product );
			if ( $matched ) {
				break;
			}
		}

		return $matched;
	}

	/**
	 * @param $context WDP_Cart_Context
	 *
	 * @throws WDP_Rule_Execution_Timeout
	 */
	public function check_execution_time( $context ) {
		$rule_max_exec_time = (float) $context->get_option( 'rule_max_exec_time' );

		if ( empty( $rule_max_exec_time ) ) {
			return;
		}

		if ( ( microtime( true ) - $this->exec_rule_start ) > $rule_max_exec_time ) {
			throw new WDP_Rule_Execution_Timeout();
		}
	}

	public function leave_disable_notice() {
		$value = get_option( WDP_Settings::$disabled_rules_option_name, array() );

		$value[] = array(
			'id'           => $this->get_id(),
			'is_exclusive' => $this->is_exclusive(),
		);

		update_option( WDP_Settings::$disabled_rules_option_name, $value );
	}

	public function has_conditions() {
		return ! empty( $this->conditions );
	}

	public function has_cart_adjustments() {
		return ! empty( $this->cart_adjustments );
	}

	public function has_limits() {
		return ! empty( $this->limits );
	}

	/**
	 * @param $cart WDP_Cart
	 *
	 * @return boolean
	 */
	public function is_rule_matched_cart( $cart ) {
		$limits_verified = $this->check_limits( $cart );
		if ( ! $limits_verified ) {
			return false;
		}

		$conditions_verified = $this->check_conditions_for_a_match( $cart );
		if ( ! $conditions_verified ) {
			return false;
		}

		return true;
	}

	public function is_simple_on_sale_rule() {
		$filters = $this->get_filters();
		if ( ! $filters ) {
			return false;
		}

		$filter           = reset( $filters );
		$is_simple_filter = 1 === (int) $filter['qty']
		                    && 1 === (int) $filter['qty_end']
		                    && ( 'in_list' === $filter['method'] && ! empty( $filter['value'] ) || 'any' === $filter['method'] )
		                    && empty( $filter['product_exclude']['values'] );

		return $is_simple_filter
		       && $this->has_product_adjustment()
		       && ! $this->has_free_products()
		       && ! $this->has_bulk()
		       && ! $this->has_roles_discount()
		       && ! $this->has_conditions()
		       && ! $this->has_cart_adjustments()
		       && ! $this->has_limits();
	}

	public function is_simple_bogo_rule() {
		$filters = $this->get_filters();
		if ( ! $filters ) {
			return false;
		}

		$filter           = reset( $filters );
		$is_simple_filter = 1 === (int) $filter['qty']
		                    && 1 === (int) $filter['qty_end']
		                    && ( 'in_list' === $filter['method'] && ! empty( $filter['value'] ) || 'any' === $filter['method'] )
		                    && empty( $filter['product_exclude']['values'] );

		return $is_simple_filter
		       && $this->has_free_products()
		       && ! $this->has_product_adjustment()
		       && ! $this->has_bulk()
		       && ! $this->has_roles_discount()
		       && ! $this->has_conditions()
		       && ! $this->has_cart_adjustments()
		       && ! $this->has_limits();
	}

	public function get_last_exec_time() {
		return $this->last_exec_time;
	}

	public function get_edit_page_url() {
		$tab = 'rules';

		return admin_url( "admin.php?page=wdp_settings&tab={$tab}&rule_id={$this->get_id()}" );
	}

	/**
	 * @return bool
	 */
	public function support_output_discount_table() {
		if ( count( $filters = $this->get_rule_filters() ) > 1 ) {
			return false;
		}

		$filter = reset( $filters );

		if ( (int) $filter['qty'] !== 1 || (int) $filter['qty_end'] !== 1 ) {
			return false;
		}

		return true;
	}

}
