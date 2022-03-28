<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rules_Registry {
	private $rules;

	protected static $instance = false;

	private function __construct() {
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_active_rules() {
		$args            = array(
			'active_only' => true,
		);
		$raw_rules       = WDP_Database::get_rules( $args );
		$rules           = $this->build_rules( $raw_rules );
		$rule_collection = new WDP_Rules_Collection( $rules );

		return $rule_collection;
	}

	private function get_rules_by_type( $rule_type ) {
		if ( ! isset( $this->rules[ $rule_type ] ) ) {
			try {
				$args                      = array(
					'types'       => $rule_type,
					'active_only' => true,
				);
				$raw_rules                 = WDP_Database::get_rules( $args );
				$this->rules[ $rule_type ] = $this->build_rules( $raw_rules );
			} catch ( Exception $exception ) {
				$this->rules[ $rule_type ] = array();
			}
		}

		return $this->rules[ $rule_type ];
	}

	/**
	 * @param array $raw_rules
	 *
	 * @return WDP_Rule[]
	 */
	private function build_rules( $raw_rules ) {
		$rules = array_map( array( $this, 'build_rule' ), $raw_rules );

		return $rules;
	}

	/**
	 * @param array $raw_rule
	 *
	 * @return WDP_Rule
	 */
	private function build_rule( $raw_rule ) {
		return WDP_Rule_Factory::get_instance()->build_rule( $raw_rule );
	}
}