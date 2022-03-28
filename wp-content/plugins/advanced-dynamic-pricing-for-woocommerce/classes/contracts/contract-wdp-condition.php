<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface WDP_Condition {

	/**
	 * @param array $data
	 */
	public function __construct( $data );

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart );

	/** @return array|null */
	public function get_involved_cart_items();

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function match( $cart );

	/**
	 * @return bool
	 */
	public function has_product_dependency();

	/**
	 * @return array|false
	 */
	public function get_product_dependency();

	/**
	 * Compatibility with currency plugins
	 *
	 * @param float $rate
	 */
	public function multiply_amounts( $rate );

	/**
	 * @param $language_code
	 *
	 */
	public function translate($language_code);
}