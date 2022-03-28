<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Date extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$date = $cart->get_context()->datetime('d-m-Y');
		$date = strtotime($date);

		$options              = $this->data['options'];
		$comparison_date      = strtotime( $options[1] );
		$comparison_method    = $options[0];

		return $this->compare_time_unix_format( $date, $comparison_date, $comparison_method );
	}

}