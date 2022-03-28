<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Rule_Range_Adjustments_Qty_Based_All_Matched_Products implements WDP_Rule_Range_Adjustments_Qty_Based {
	private $key = 'all';
	private $rule_id;

	public function __construct( $rule_id ) {
		$this->rule_id = $rule_id;
	}

	public function get_key() {
		return $this->key;
	}

	public static function get_label() {
		return __( 'Qty based on all matched products', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection|WDP_Cart_Items_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection ) {
		$cart_items_collection = new WDP_Cart_Items_Collection( $this->rule_id );
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
				$cart_items_collection->add( $new_item );
			}
		}

		$range_calculation_items = array_map( function ( $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			return new WDP_Rule_Discount_Range_Calculation_Item( $item->get_hash(), $item->get_qty() );
		}, $cart_items_collection->get_items() );

		$calculator = new WDP_Price_Calculator();
		foreach ( $bulk_calc->calculate_items_discounts( $range_calculation_items ) as $item ) {
			/**
			 * @var $item WDP_Rule_Discount_Range_Calculation_Item
			 */
			if ( $item->has_discount() ) {
				$calculator->set_type( $item->get_discount_type() )->set_value( $item->get_discount_value() );
				$price = $calculator->calculate_price_for_item( $cart_items_collection->get_item_by_hash( $item->get_hash() ) );
				$cart_items_collection->set_price_for_item( $item->get_hash(), $price, $item->get_qty() );
			}
		}

		return $cart_items_collection;
	}

	public function apply_additional_data( $data ) {
		return;
	}
}