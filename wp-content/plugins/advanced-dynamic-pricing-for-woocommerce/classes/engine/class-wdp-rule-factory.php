<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Factory {
	protected static $instance;

	private $rule_types;

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param array $args
	 *
	 * @return WDP_Rule
	 * @throws Exception
	 */
	public function build_rule( $args ) {
		$rule_type = isset( $args['type'] ) ? $args['type'] : '';
		if ( ! isset( $this->rule_types[ $rule_type ] ) ) {
			throw new Exception( __( 'Wrong rule type', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}
		$rule_class = $this->rule_types[ $rule_type ];

		return new $rule_class( $args );
	}

	private function __construct() {
		$this->rule_types = array(
			'package' => 'WDP_Rule',
		);
	}
}