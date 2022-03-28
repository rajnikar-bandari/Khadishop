<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Execution_Timeout extends Exception {
	public function errorMessage() {
		return __( 'Rule execution timeout', 'advanced-dynamic-pricing-for-woocommerce' );
	}

}