<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Customer_Role extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$options           = $this->data['options'];
		$comparison_method = $options[0];
		$comparison_roles   = (array) $options[1];

		$roles = $cart->get_context()->get_customer_roles();

		return $this->compare_lists( $roles, $comparison_roles, $comparison_method );
	}
}