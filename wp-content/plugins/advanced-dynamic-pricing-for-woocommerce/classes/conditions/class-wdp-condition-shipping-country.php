<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Shipping_Country extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$country = $cart->get_context()->get_shipping_country();

		$options           = $this->data['options'];
		$comparison_list   = (array) $options[1];
		$comparison_method = $options[0];

		return $this->compare_value_with_list( $country, $comparison_list, $comparison_method );
	}
}