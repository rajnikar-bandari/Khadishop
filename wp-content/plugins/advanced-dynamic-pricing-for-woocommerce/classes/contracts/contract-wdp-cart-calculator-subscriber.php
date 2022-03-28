<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface WDP_Cart_Calculator_Subscriber {
	/**
	 *
	 */
	public function start_cart_calculation();

	/**
	 * @param $rule WDP_Rule
	 */
	public function rule_calculated_cart( $rule );

	/**
	 * @param $cart WDP_Cart
	 */
	public function cart_calculated( $cart );

	/**
	 *
	 */
	public function start_product_calculation();

	/**
	 * @param $rule WDP_Rule
	 */
	public function rule_calculated_product( $rule );

	/**
	 * @param $product WC_Product
	 * @param $qty integer
	 * @param $item WDP_Cart_Item
	 */
	public function product_calculated( $product, $qty, $item );
}