<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Cart_Subtotal extends WDP_Condition_Abstract {
	protected $amount_indexes = array( 1 );

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$subtotal = array_sum( array_map( function ( $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			return $item->get_total_price();
		}, $cart->get_items() ) );

		$options           = $this->data['options'];
		$comparison_value  = (float) $options[1];
		$comparison_method = $options[0];

		return $this->compare_values( $subtotal, $comparison_value, $comparison_method );
	}

}