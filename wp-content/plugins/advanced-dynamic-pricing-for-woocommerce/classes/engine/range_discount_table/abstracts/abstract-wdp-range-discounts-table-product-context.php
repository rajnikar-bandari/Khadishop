<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Range_Discounts_Table_Product_Context_Abstract extends WDP_Range_Discounts_Table_Abstract {
	/**
	 * @param WDP_Price_Display $price_display
	 * @param integer           $product_id
	 */
	public function load_rule( $price_display, $product_id ) {
		$product = WDP_Object_Cache::get_instance()->get_wc_product( $product_id );
		if ( ! $product ) {
			return;
		}
		$available_product_ids = array_merge( array( $product->get_id() ), $product->get_children() );

		$matched_rule = null;
		foreach ( $available_product_ids as $product_id ) {
			$matched_rule = $price_display->get_calculator()->get_first_discount_range_rule_supports_product_table( $price_display->get_cart(), $product_id );

			if ( $matched_rule ) {
				break;
			}
		}

		if ( $matched_rule ) {
			$this->fill_bulk_details( $matched_rule->get_bulk_details( $price_display->get_cart()->get_context() ) );
			$this->rule               = $matched_rule;
			$this->object_id          = $product_id;

			$bulk_table_calculation_mode = $price_display->get_cart()->get_context()->get_option('bulk_table_calculation_mode');

			if ( $bulk_table_calculation_mode === 'only_bulk_rule_table' ) {
				$calc                = new WDP_Cart_Calculator( new WDP_Rules_Collection( array( $matched_rule ) ) );
				$this->price_display = new WDP_Price_Display();
				$this->price_display->attach_calc( $calc );
			} elseif ( $bulk_table_calculation_mode === 'all' ) {
				$this->price_display = new WDP_Price_Display();
				$this->price_display->attach_calc( $price_display->get_calculator() );
			}

			$this->price_display->apply_empty_cart();
		}
	}
}
