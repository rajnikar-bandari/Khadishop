<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Adjustment_Fee_Amount_Repeatable_Sets_Count extends WDP_Cart_Adjustment {
	public function __construct( $data ) {
		$this->data = $data;
		$this->amount_indexes = array( 0 );
	}

	/**
	 * @param WDP_Cart $cart
	 * @param          $set_collection WDP_Cart_Set_Collection
	 * @param int      $rule_id
	 *
	 * @return bool
	 */
	public function apply_to_cart( $cart, $set_collection, $rule_id ) {
		$options = $this->data['options'];

		$tax_class = ! empty( $options[2] ) ? $options[2] : "";

		for ( $i = 0; $i < count( $set_collection->get_sets() ); $i ++ ) {
			$cart->add_fee_amount( $options[1], (float) $options[0], $rule_id, $tax_class );
		}

		return true;
	}
}