<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Adjustment_Free_Shipping extends WDP_Cart_Adjustment {
	public function __construct( $data ) {
		$this->data = $data;
		$this->amount_indexes = array();
	}

	/**
	 * @param WDP_Cart $cart
	 * @param          $set_collection WDP_Cart_Set_Collection
	 * @param int      $rule_id
	 *
	 * @return bool
	 */
	public function apply_to_cart( $cart, $set_collection, $rule_id ) {
		$cart->add_free_shipping( $rule_id );

		return true;
	}
}