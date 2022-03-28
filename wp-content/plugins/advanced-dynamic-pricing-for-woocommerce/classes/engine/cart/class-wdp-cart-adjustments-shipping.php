<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WDP_Cart_Adjustments_Shipping {
	private $is_free_shipping = false;
	private $rule_id_applied_free_shipping;
	private $adjustments = array();

	public function __construct() {
	}

	public function add_amount_discount( $value, $rule_id ) {
		$this->add( 'amount', $value, $rule_id );
	}

	public function add_percentage_discount( $value, $rule_id ) {
		$this->add( 'percentage', $value, $rule_id );
	}

	public function set_fixed_price( $value, $rule_id ) {
		$this->add( 'fixed', $value, $rule_id );
	}

	public function add( $type, $value, $rule_id ) {
		if ( $this->is_free_shipping ) {
			return;
		}

		$this->adjustments[ substr( md5( rand() ), 0, 8 ) ] = array(
			'type'    => $type,
			'value'   => $value,
			'rule_id' => $rule_id,
		);
	}

	public function apply_free_shipping( $rule_id ) {
		$this->adjustments                   = array();
		$this->is_free_shipping              = true;
		$this->rule_id_applied_free_shipping = $rule_id;
	}

	public function is_free() {
		return $this->is_free_shipping;
	}

	public function get_rule_id_applied_free_shipping() {
		return $this->is_free_shipping ? $this->rule_id_applied_free_shipping : null;
	}

	/**
	 * @return array
	 */
	public function get_items() {
		return $this->adjustments;
	}

	/**
	 * @param $adjustment_id
	 *
	 * @return array
	 */
	public function get_item( $adjustment_id ) {
		return isset( $this->adjustments[ $adjustment_id ] ) ? $this->adjustments[ $adjustment_id ] : null;
	}
}