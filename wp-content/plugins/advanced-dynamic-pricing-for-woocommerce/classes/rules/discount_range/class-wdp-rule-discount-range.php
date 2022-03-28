<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WDP_Rule_Discount_Range {
	private $from;
	private $to;
	private $value;

	public function __construct( $from, $to, $value ) {
		$this->from  = $from && $from >= 1 ? (float) $from : 1;
		$this->to    = $to ? (float) $to : PHP_INT_MAX;
		$this->value = (float) $value;
	}

	public function get_value() {
		return $this->value;
	}

	public function get_from() {
		return $this->from;
	}

	public function get_to() {
		return $this->to;
	}

	public function get_qty() {
		return $this->to - $this->from + 1;
	}

	public function is_in( $comparison_value ) {
		return $comparison_value >= $this->from && ( $comparison_value <= $this->to || $this->to == '' );
	}

	public function is_more( $comparison_value ) {
		return $this->to !== '' && $this->to < $comparison_value;
	}

	public function is_less( $comparison_value ) {
		return $this->from > $comparison_value;
	}
}