<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


interface WDP_Rule_Range_Adjustments_Qty_Based {
	/**
	 * WDP_Rule_Range_Adjustments_Qty_Based constructor.
	 *
	 * @param $rule_id int
	 */
	public function __construct( $rule_id );

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection );

	/**
	 * @param $data array
	 */
	public function apply_additional_data( $data );

	/**
	 * @return array
	 */
	public static function get_available_discount_types();

	/**
	 * @return string
	 */
	public static function get_label();
}