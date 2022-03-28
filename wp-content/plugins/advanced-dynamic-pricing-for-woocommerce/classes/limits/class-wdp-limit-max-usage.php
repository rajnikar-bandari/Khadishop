<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Limit_Max_Usage implements WDP_Limit {
	private $data;

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function check( $cart, $rule_id ) {
		$comparison_value = (int) $this->data['options'];

		$value = $cart->get_context()->get_count_of_rule_usages( $rule_id );

		return $value < $comparison_value;
	}
}