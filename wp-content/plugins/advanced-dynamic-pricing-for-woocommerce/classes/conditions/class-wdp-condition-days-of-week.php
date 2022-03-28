<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Days_Of_Week extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$value = $cart->get_context()->datetime( 'w' );

		$options = $this->data['options'];

		return $this->compare_value_with_list( $value, $options[1], $options[0] );
	}

}