<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface WDP_Rule_Discount_Range_Calculator {
	/**
	 * @var $discount_type string
	 */
	public function set_discount_type( $discount_type );

	/**
	 * @var $ranges WDP_Rule_Discount_Range[]
	 */
	public function set_ranges( $ranges );

	/**
	 * @return WDP_Rule_Discount_Range[]
	 */
	public function get_ranges();

	/**
	 * @param $items WDP_Rule_Discount_Range_Calculation_Item[]
	 * @param $custom_qty int
	 *
	 * @return array
	 */
	public function calculate_items_discounts( $items, $custom_qty = null );
}