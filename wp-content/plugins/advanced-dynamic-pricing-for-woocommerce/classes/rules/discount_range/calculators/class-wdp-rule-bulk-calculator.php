<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Bulk_Calculator implements WDP_Rule_Discount_Range_Calculator {
	/**
	 * @var array WDP_Rule_Discount_Range[]
	 */
	private $ranges = array();

	/**
	 * @var string
	 */
	private $discount_type;

	public function set_discount_type( $discount_type ) {
		$this->discount_type = $discount_type;
	}

	public function get_discount_type() {
		return $this->discount_type;
	}

	/**
	 * @var $ranges WDP_Rule_Discount_Range[]
	 */
	public function set_ranges( $ranges ) {
		$this->ranges = $ranges;
	}

	/**
	 * @raturn $ranges WDP_Rule_Discount_Range[]
	 */
	public function get_ranges() {
		return $this->ranges;
	}

	/**
	 * @param $items WDP_Rule_Discount_Range_Calculation_Item[]
	 * @param $custom_qty int
	 *
	 * @return array
	 */
	public function calculate_items_discounts( $items, $custom_qty = null ) {
		if ( ! $items ) {
			return array();
		}

		$total_qty = 0;
		foreach ( $items as $item ) {
			/** @var $item WDP_Rule_Discount_Range_Calculation_Item */
			$total_qty                   += $item->get_qty();
		}

		$qty = ! is_null( $custom_qty ) ? $custom_qty : $total_qty;

		foreach ( $this->ranges as $range ) {
			if ( $range->is_in( $qty ) ) {
				foreach ( $items as &$item ) {
					$item->set_discount( $this->discount_type, $range->get_value() );
				}
				break;
			}
		}

		return $items;
	}

}