<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WDP_Rule_Range_Adjustments {
	/**
	 * @var WDP_Rule_Discount_Range_Calculation
	 */
	private $bulk_calc;

	/**
	 * @var WDP_Rule_Range_Adjustments_Qty_Based_Calculator
	 */
	private $qty_based_calculator;

	/**
	 * WDP_Rule_Range_Adjustments constructor.
	 * @throws Exception
	 *
	 * @param $rule_data array
	 */
	public function __construct( $rule_data ) {
		$rule_id           = isset( $rule_data['id'] ) ? (int) $rule_data['id'] : null;
		if ( is_null( $rule_id ) ) {
			throw new Exception( __( 'Invalid rule ID.', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}

		$qty_based           = isset( $rule_data['bulk_adjustments']['qty_based'] ) ? $rule_data['bulk_adjustments']['qty_based'] : 'all';
		$ranges              = isset( $rule_data['bulk_adjustments']['ranges'] ) ? $rule_data['bulk_adjustments']['ranges'] : array();
		$adj_type            = isset( $rule_data['bulk_adjustments']['type'] ) ? $rule_data['bulk_adjustments']['type'] : "bulk";
		$discount_type       = isset( $rule_data['bulk_adjustments']['discount_type'] ) ? $rule_data['bulk_adjustments']['discount_type'] : 'discount__amount';
		$selected_categories = isset( $rule_data['bulk_adjustments']['selected_categories'] ) ? $rule_data['bulk_adjustments']['selected_categories'] : array();
		$selected_products   = isset( $rule_data['bulk_adjustments']['selected_products'] ) ? $rule_data['bulk_adjustments']['selected_products'] : array();

		$additional_data = array(
			'selected_categories' => $selected_categories,
			'selected_products' => $selected_products,
		);

		$ranges_objs     = WDP_Rule_Discount_Range_Calculation::make_ranges( $ranges );
		$this->bulk_calc = new WDP_Rule_Discount_Range_Calculation( $adj_type, $discount_type, $ranges_objs );

		$this->qty_based_calculator = new WDP_Rule_Range_Adjustments_Qty_Based_Calculator( $rule_id, $adj_type, $qty_based );
		if ( ! $this->qty_based_calculator->check_instance() ) {
			throw new Exception( __( 'Impossible bulk rules detected for rule #' . $rule_id, 'advanced-dynamic-pricing-for-woocommerce' ) );
		};
		if ( ! $this->qty_based_calculator->check_discount_type( $discount_type ) ) {
			throw new Exception( __( 'Invalid discount type for quantity based calculator for rule #' . $rule_id, 'advanced-dynamic-pricing-for-woocommerce' ) );
		};
		
		$this->qty_based_calculator->apply_additional_data( $additional_data );
	}

	public function change_currency( $rate ) {
		$this->bulk_calc->multiply_amounts( $rate );
	}

	public function translate( $language_code ) {
		$this->qty_based_calculator->translate( $language_code );
	}

	/**
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	public function apply_adjustments( $cart, $set_collection ) {
		return $this->qty_based_calculator->process( $this->bulk_calc, $cart, $set_collection );
	}

}