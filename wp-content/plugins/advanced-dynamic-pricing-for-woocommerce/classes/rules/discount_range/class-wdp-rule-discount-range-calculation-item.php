<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WDP_Rule_Discount_Range_Calculation_Item {
	private $hash;
	private $qty;
	private $processed = false;

	private $discounts = array();

	public function __construct( $hash, $qty = 1 ) {
		$this->hash  = $hash;
		$this->qty   = (float) $qty;
	}

	public function get_hash() {
		return $this->hash;
	}

	public function get_initial_hash() {
		return $this->hash;
	}

	public function get_qty() {
		return $this->qty;
	}

	public function get_as_flat() {
		return array(
			'hash'  => $this->hash,
			'qty'   => $this->qty,
		);
	}

	public function set_discount( $type, $value ) {
		$this->discounts[] = array(
			'type'=> $type,
			'value'=> $value,
		);
	}

	public function get_discount_type() {
		return count( $this->discounts ) ? reset( $this->discounts)['type'] : null;
	}

	public function get_discount_value() {
		return count( $this->discounts ) ? reset( $this->discounts)['value']  : null;
	}

	public function has_discount() {
		return count( $this->discounts );
	}

	public function mark_as_processed() {
		$this->processed = true;
	}

	public function is_processed() {
		return $this->processed;
	}

	public function dec_qty( $qty ) {
		$this->qty -= $qty;
	}

	public function enough_qty( $qty ) {
		return $this->qty >= $qty;
	}

}