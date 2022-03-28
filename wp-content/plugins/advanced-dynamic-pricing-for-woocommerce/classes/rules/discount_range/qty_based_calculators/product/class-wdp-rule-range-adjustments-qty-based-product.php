<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Qty_Based_Product implements WDP_Rule_Range_Adjustments_Qty_Based {
	private $key = 'product';
	private $rule_id;

	public function __construct( $rule_id ) {
		$this->rule_id = $rule_id;
	}

	public function get_key() {
		return $this->key;
	}

	public static function get_available_discount_types() {
		return WDP_Rule_Range_Discount_Types::get_item_types();
	}

	public static function get_label() {
		return __( 'Qty based on product', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection|WDP_Cart_Items_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection ) {
		$product_id_hash_mapping = array();

		$cart_items_collection = new WDP_Cart_Items_Collection( $this->rule_id );
		$calculator            = new WDP_Price_Calculator();

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

		foreach ( $cart_items_collection->get_items() as $item ) {
			$hash = $item->get_hash();

			$item_data  = $cart->get_item_data_by_hash( $hash );
			$product = $item_data['data'];
			/**
			 * @var $product WC_Product
			 */

			$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

			if ( ! isset( $product_id_hash_mapping[ $product_id ] ) ) {
				$product_id_hash_mapping[ $product_id ] = array();
			}
			$product_id_hash_mapping[ $product_id ][] = $hash;
		}

		foreach ( $product_id_hash_mapping as $product_id => $hashes ) {
			$range_calculation_items = array();
			foreach ( $hashes as $hash ) {
				$range_calculation_items[] = new WDP_Rule_Discount_Range_Calculation_Item( $hash, $cart_items_collection->get_item_by_hash( $hash )->get_qty() );
			}

			foreach ( $bulk_calc->calculate_items_discounts( $range_calculation_items ) as $calculate_items_discount ) {
				/**
				 * @var $calculate_items_discount WDP_Rule_Discount_Range_Calculation_Item
				 */
				if ( ! $calculate_items_discount->has_discount() ) {
					continue;
				}

				$calculator->set_type( $calculate_items_discount->get_discount_type() )->set_value( $calculate_items_discount->get_discount_value() );
				$price = $calculator->calculate_price_for_item( $cart_items_collection->get_item_by_hash( $calculate_items_discount->get_hash() ) );
				$cart_items_collection->set_price_for_item( $calculate_items_discount->get_hash(), $price, $calculate_items_discount->get_qty() );
			}
		}

		return $cart_items_collection;
	}

	public function apply_additional_data( $data ) {
		return;
	}
}