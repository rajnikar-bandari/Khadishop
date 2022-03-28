<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Total_Qty_In_Cart implements WDP_Rule_Range_Adjustments_Qty_Based {
	private $key = 'total_qty_in_cart';
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
		return __( 'Qty based on all items in the cart', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection ) {
		$qty = array_sum( array_map( function ( $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			return $item->get_qty();
		}, $cart->get_items() ) );

		foreach ( $set_collection->get_sets() as $set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			foreach ( $set->get_items() as $hash => $item ) {
				/**
				 * @var $item WDP_Cart_Item
				 */
				$qty += $item->get_qty() * $set->get_qty();
			}
		}

		if ( $qty < 1 ) {
			return $set_collection;
		}

		$new_set_collection = new WDP_Cart_Set_Collection();
		$range_calculation_items = array_map( function ( $set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			return new WDP_Rule_Discount_Range_Calculation_Item( $set->get_hash(), $set->get_qty() );
		}, $set_collection->get_sets() );

		$calculator = new WDP_Price_Calculator();
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