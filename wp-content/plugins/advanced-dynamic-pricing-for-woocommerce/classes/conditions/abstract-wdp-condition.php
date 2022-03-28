<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Condition_Abstract implements WDP_Condition {
	use WDP_Comparison;

	protected $data;
	protected $amount_indexes = array();
	protected $has_product_dependency = false;

	/**
	 * @param array $data
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		return false;
	}

	/** @return array|null */
	public function get_involved_cart_items() {
		return null;
	}

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function match( $cart ) {
		return $this->check( $cart );
	}

	/**
	 * @return bool
	 */
	public function has_product_dependency() {
		return $this->has_product_dependency;
	}

	/**
	 * @return array|false
	 */
	public function get_product_dependency() {
		return false;
	}

	/**
	 * @param float $factor
	 */
	public function multiply_amounts( $factor ) {
		foreach ( $this->amount_indexes as $index ) {
			if ( isset( $this->data['options'][ $index ] ) ) {
				$amount = (float) $this->data['options'][ $index ];
				$this->data['options'][ $index ] = $amount * $factor;
			}
		}
	}

	/**
	 * @param string $language_code
	 */
	public function translate( $language_code ) {
		return;
	}
}