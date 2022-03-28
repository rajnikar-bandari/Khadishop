<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Cart_Adjustment implements WDP_Cart_Adjustment_Interface {
	protected $data;
	protected $amount_indexes;

	/**
	 * @param float $rate
	 */
	public function multiply_amounts( $rate ) {
		foreach ( $this->amount_indexes as $index ) {
			if ( isset( $this->data['options'][ $index ] ) ) {
				$amount = (float) $this->data['options'][ $index ];
				$this->data['options'][ $index ] = $amount * $rate;
			}
		}
	}
}