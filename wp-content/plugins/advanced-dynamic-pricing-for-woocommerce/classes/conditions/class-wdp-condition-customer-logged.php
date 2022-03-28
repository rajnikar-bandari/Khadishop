<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Customer_Logged extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$options = $this->data['options'];

		$comparison_value = 'yes' === $options[0];

		return $cart->get_context()->is_customer_logged_in() === $comparison_value;
	}
}