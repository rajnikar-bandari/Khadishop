<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Time extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$time = strtotime( $cart->get_context()->datetime( 'H:i' ) );

		$options              = $this->data['options'];
		$comparison_time      = strtotime( $options[1] );
		$comparison_method    = $options[0];

		return $this->compare_time_unix_format( $time, $comparison_time, $comparison_method );
	}
}