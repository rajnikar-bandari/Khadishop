<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Set_Collector {
	/**
	 * @var array
	 */
	private $filters;

	/**
	 * @var WDP_Cart_Items_Collection
	 */
	private $items;

	/**
	 * @var int Attempt limit
	 */
	private $limit = - 1;

	/**
	 * @var int
	 */
	private $rule_id;

	/**
	 * @var WDP_Cart_Items_Collection
	 */
	private $mutable_items_collection;

	private $check_execution_time_callback;

	public function __construct( $rule_id ) {
		$this->rule_id                  = $rule_id;
		$this->mutable_items_collection = new WDP_Cart_Items_Collection( $rule_id );
	}

	public function set_limit( $limit ) {
		$this->limit = $limit;
	}

	public function register_check_execution_time_function( $callable, $context ) {
		$this->check_execution_time_callback = array(
			'callable' => $callable,
			'context' => $context,
		);
	}

	private function check_execution_time() {
		if ( ! isset( $this->check_execution_time_callback['callable'] ) && $this->check_execution_time_callback['context'] ) {
			return;
		}

		$callable = $this->check_execution_time_callback['callable'];
		$context     = $this->check_execution_time_callback['context'];

		call_user_func( $callable, $context );
	}

	/**
	 * @param $items WDP_Cart_Items_Collection
	 *
	 * @return $this
	 */
	public function install_items( $items ) {
		$this->items = $items;

		return $this;
	}

	/**
	 * @param $mutable_items WDP_Cart_Item[]
	 */
	public function add_items( $mutable_items ) {
		foreach ( $mutable_items as $index => $cart_item ) {
			$this->mutable_items_collection->add( $cart_item );
		}
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $product_filters array
	 */
	public function apply_filters( $cart, $product_filters ) {
		$product_filtering = WDP_Loader::get_product_filtering_class();
		$filters           = array();

		// hashes with highest priority
		$type_products_hashes = array();

		$product_excluding = WDP_Loader::get_product_filtering_class();
		$product_exclude_enabled = $cart->get_context()->get_option( 'allow_to_exclude_products' );
		$filter_priority_enabled = $cart->get_context()->get_option( 'show_select_filter_priority', false );

		foreach ( $product_filters as $filter_key => $filter ) {
			$select_priority = ! empty( $filter['select_priority'] ) && $filter_priority_enabled ? $filter['select_priority'] : false;

			$product_filtering->prepare( $filter['type'], $filter['value'], $filter['method'] );
			$filter_qty     = '' !== $filter['qty'] ? (float) $filter['qty'] : 1;
			$filter_qty_end = '' !== $filter['qty_end'] ? (float) $filter['qty_end'] : '';

			$exclude_product_ids = ! empty($filter['product_exclude']['values']) ? (array)$filter['product_exclude']['values'] : array();
			$exclude_on_wc_sale = ! empty( $filter['product_exclude']['on_wc_sale'] ) ? wc_string_to_bool( $filter['product_exclude']['on_wc_sale'] ) : false;
			$exclude_already_affected = ! empty( $filter['product_exclude']['already_affected'] ) ? wc_string_to_bool( $filter['product_exclude']['already_affected'] ) : false;
			$product_excluding->prepare( 'products', $exclude_product_ids, 'in_list' );

			$valid_hashes = array();
			foreach ( $this->mutable_items_collection->get_items() as $cart_item ) {
				/**
				 * @var $cart_item WDP_Cart_Item
				 */
				$wc_cart_item = $cart->get_item_data_by_hash( $cart_item->get_hash() );
				$product      = $wc_cart_item['data'];

				if ( $product_exclude_enabled && $exclude_already_affected ? $cart_item->is_price_changed() : false ) {
					continue;
				}

				$product_exclude_on_wc_sale = $product_exclude_enabled && $exclude_on_wc_sale ? $product->is_on_sale('') : false;
				if ( $product_exclude_on_wc_sale ) {
					continue;
				}

				$product_exclude = $product_exclude_enabled ? $product_excluding->check_product_suitability( $product, $wc_cart_item ) : false;
				if ( $product_exclude ) {
					continue;
				}

				if ( $product_filtering->check_product_suitability( $product, $wc_cart_item ) ) {
					$valid_hashes[] = $cart_item->get_hash();
					if ( $product_filtering->is_type( 'products' ) ) {
						$type_products_hashes[] = $cart_item->get_hash();
					}
				}
			}

			uasort( $valid_hashes, function ( $hash1, $hash2 ) use ( $select_priority ) {
				$item1 = $this->mutable_items_collection->get_item_by_hash( $hash1 );
				$item2 = $this->mutable_items_collection->get_item_by_hash( $hash2 );

				if ( 'cheap' === $select_priority ) {
					return $item1->get_initial_price() - $item2->get_initial_price();
				} elseif ( 'expensive' === $select_priority ) {
					return $item2->get_initial_price() - $item1->get_initial_price();
				}

				return 0;
			} );

			$filters[] = array(
				'valid_hashes'   => $valid_hashes,
				'product_filter' => $product_filtering->is_type( 'products' ),
				'qty'            => $filter_qty,
				'qty_end'        => $filter_qty_end,
			);
		}

		if ( count( $filters ) == count( $product_filters ) ) {
			$this->filters = $filters;
		}

		foreach ( $this->filters as &$filter ) {
			$is_product_filter = $filter['product_filter'];
			unset( $filter['product_filter'] );

			/** Do not reorder 'exact products' filter hashes */
			if ( $is_product_filter ) {
				continue;
			}

			foreach ( array_reverse( $type_products_hashes ) as $hash ) {
				foreach ( $filter['valid_hashes'] as $index => $valid_hash ) {
					if ( $hash === $valid_hash ) {
						unset( $filter['valid_hashes'][ $index ] );
						$filter['valid_hashes'][] = $hash;
						$filter['valid_hashes']   = array_values( $filter['valid_hashes'] );
						break;
					}
				}
			}
		}
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $mode string
	 *
	 * @return WDP_Cart_Set_Collection|false
	 */
	public function collect_sets( &$cart, $mode ) {
		if ( 'legacy' == $mode ) {
			$collection = $this->collect_sets_legacy( $cart );
		} else {
			$collection = false;
		}

		return $collection;
	}

	/**
	 * @param $cart WDP_Cart
	 *
	 * @return WDP_Cart_Set_Collection|false
	 */
	public function collect_sets_legacy( &$cart ) {
		$collection = new WDP_Cart_Set_Collection();
		$applied    = true;

		while ( $applied && $collection->get_total_sets_qty() !== $this->limit ) {
			$set_items = array();

			foreach ( $this->filters as $filter_key => &$filter ) {
				$filter_qty     = '' !== $filter['qty'] ? (float) $filter['qty'] : 1;
				$filter_qty_end = '' !== $filter['qty_end'] ? (float) $filter['qty_end'] : '';
				$valid_hashes   = ! empty( $filter['valid_hashes'] ) ? $filter['valid_hashes'] : array();
				$range = new WDP_Range( $filter_qty, $filter_qty_end, $valid_hashes );
				$filter_applied = false;

				$filter_set_items = array();

				foreach ( $valid_hashes as $index => $valid_cart_item_hash ) {
					$cart_item = $this->mutable_items_collection->get_not_empty_item_with_reference_by_hash( $valid_cart_item_hash );

					if ( is_null( $cart_item ) ) {
						unset( $valid_hashes[ $index ] );
						continue;
					}

					$collected_qty = 0;
					foreach ( $filter_set_items as $filter_set_item ) {
						/**
						 * @var $filter_set_item WDP_Cart_Item
						 */
						$collected_qty += $filter_set_item->get_qty();
					}

					$collected_qty += $cart_item->get_qty();

					if ( ! $range->is_range_valid() ) {
						continue;
					}

					if ( $range->is_less( $collected_qty ) ) {
						$set_cart_item = clone $cart_item;
						$cart_item->set_qty( 0 );
						$filter_set_items[] = $set_cart_item;
					} elseif ( $range->is_in( $collected_qty ) ) {
						$set_cart_item = clone $cart_item;
						$cart_item->set_qty( 0 );
						$filter_set_items[] = $set_cart_item;
						$filter_applied = true;
						break;
					} elseif ( $range->is_greater( $collected_qty ) ) {
						$mode_value_to = $range->get_mode_value_to();
						if ( is_infinite( $mode_value_to ) ) {
							continue;
						}

						$require_qty = $mode_value_to + $cart_item->get_qty() - $collected_qty;

						$set_cart_item = clone $cart_item;
						$set_cart_item->dec_qty( $cart_item->get_qty() - $require_qty );
						$cart_item->dec_qty( $require_qty );

						$filter_set_items[] = $set_cart_item;
						$filter_applied = true;
						break;
					}
				}

				if ( $filter_set_items ) {
					if ( $filter_applied ) {
						$set_items[]      = $filter_set_items;
					} else {
						/**
						 * For range filters, try to put remaining items in set
						 *
						 * If range 'to' equals infinity or 'to' not equal 'from'
						 */
						if ( $range->get_qty() === false || $range->get_qty() ) {
							$collected_qty = 0;
							foreach ( $filter_set_items as $filter_set_item ) {
								/**
								 * @var $filter_set_item WDP_Cart_Item
								 */
								$collected_qty += $filter_set_item->get_qty();
							}

							if ( $range->is_in( $collected_qty, false ) ) {
								$set_items[]      = $filter_set_items;
								$filter_set_items = array();
								$filter_applied = true;
							}
						}

						foreach ( $filter_set_items as $item ) {
							/**
							 * @var $item WDP_Cart_Item
							 */
							$this->mutable_items_collection->add( $item );
						}
					}

					$filter_set_items = array();
				}

				$applied = $applied && $filter_applied;
			}

			if ( $set_items && $applied ) {
				$collection->add( new WDP_Cart_Set( $this->rule_id, $set_items ) );
				$set_items = array();
			}

			$this->check_execution_time();
		}

		if ( ! empty( $set_items ) ) {
			foreach ( $set_items as $item ) {
				$cart->add_to_cart( $item );
			}
		}

		if ( ! empty( $filter_set_items ) ) {
			foreach ( $filter_set_items as $item ) {
				$cart->add_to_cart( $item );
			}
		}

		foreach ( $this->mutable_items_collection->get_items() as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			$cart->add_to_cart( $item );
		};

		return $collection;
	}

}
