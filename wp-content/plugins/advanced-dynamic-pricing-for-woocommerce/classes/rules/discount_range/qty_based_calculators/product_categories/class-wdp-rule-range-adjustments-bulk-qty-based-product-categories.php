<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Product_Categories implements WDP_Rule_Range_Adjustments_Qty_Based {
	private $key = 'product_categories';
	private $rule_id;

	public function __construct( $rule_id ) {
		$this->rule_id = $rule_id;
	}

	public function get_key() {
		return $this->key;
	}

	public static function get_available_discount_types() {
		return WDP_Rule_Range_Discount_Types::get_all_types();
	}

	public static function get_label() {
		return __( 'Qty based on product categories in all cart', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection ) {
		$items = new WDP_Cart_Items_Collection( $this->rule_id );
		$used_categories = array();

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
				$items->add( $new_item );
			}
		}

		foreach ( $items->get_items() as $item ) {
			$hash = $item->get_hash();

			$item_data = $cart->get_item_data_by_hash( $hash );
			$product   = $item_data['data'];
			/**
			 * @var $product WC_Product
			 */

			foreach ( $product->get_category_ids() as $category_id ) {
				if ( ! in_array( $category_id, $used_categories ) ) {
					$used_categories[] = $category_id;
				}
			}
		}

		// count items with same categories in WC cart
		$qty = 0;
		if ( $used_categories && $cart->get_wc_cart() ) {
			foreach ( $cart->get_wc_cart()->get_cart() as $cart_item ) {
				$product = $cart_item['data'];
				/**
				 * @var $product WC_Product
				 */
				if ( count( array_intersect( $product->get_category_ids(), $used_categories ) ) ) {
					$qty += $cart_item['quantity'];
				}
			}
		}

		$calculator         = new WDP_Price_Calculator();
		$new_set_collection = new WDP_Cart_Set_Collection();

		$range_calculation_items = array_map( function ( $set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			return new WDP_Rule_Discount_Range_Calculation_Item( $set->get_hash(), $set->get_qty() );
		}, $set_collection->get_sets() );

		foreach ( $bulk_calc->calculate_items_discounts( $range_calculation_items, $qty ) as $item ) {
			/**
			 * @var $item WDP_Rule_Discount_Range_Calculation_Item
			 */
			$new_set = $set_collection->get_set_by_hash( $item->get_initial_hash() );
			$new_set->set_qty( $item->get_qty() );

			if ( $item->has_discount() ) {
				$calculator->set_type( $item->get_discount_type() )->set_value( $item->get_discount_value() );
				$new_set = $calculator->calculate_price_for_set( $new_set );
			}

			$new_set_collection->add( $new_set );
		}

		return $new_set_collection;
	}

	public function apply_additional_data( $data ) {
		return;
	}
}