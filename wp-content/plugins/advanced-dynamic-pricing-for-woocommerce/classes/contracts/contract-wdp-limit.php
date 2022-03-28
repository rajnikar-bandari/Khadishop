<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface WDP_Limit {

	/**
	 * @param array $data
	 */
	public function __construct( $data );

	/**
	 * @param WDP_Cart $cart
	 * @param int      $rule_id
	 *
	 * @return bool
	 */
	public function check( $cart, $rule_id );
}