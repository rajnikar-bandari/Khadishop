<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Tier_Calculator implements WDP_Rule_Discount_Range_Calculator {
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

		foreach ( $this->ranges as $range ) {
			/**
			 * @var $range WDP_Rule_Discount_Range
			 */

			if ( ! is_null( $custom_qty ) && $range->is_in( $custom_qty ) ) {
				$from  = $range->get_from();
				$to    = $custom_qty;
				$value = $range->get_value();
				$range = new WDP_Rule_Discount_Range( $from, $to, $value );
				$items = $this->process_range( $items, $range );
				break;
			}

			$items = $this->process_range( $items, $range );
		}

		return $items;
	}

	/**
	 * @param $items WDP_Rule_Discount_Range_Calculation_Item[]
	 * @param $range WDP_Rule_Discount_Range
	 *
	 * @return array
	 */
	private function process_range( $items, $range ) {
		$processed_qty             = 1;
		$new_items                 = array();
		$index_of_items_to_process = array();

		foreach ( $items as $item ) {
			if ( $item->is_processed() ) {
				$new_items[]   = $item;
				$processed_qty += $item->get_qty();
				continue;
			}

			/** @var $item WDP_Rule_Discount_Range_Calculation_Item */
			if ( $range->is_less( $processed_qty ) ) {
				if ( $range->is_in( $processed_qty + $item->get_qty() ) ) {
					$require_qty = $processed_qty + $item->get_qty() - $range->get_from();

					if ( $require_qty > 0 ) {
						$new_items[]                 = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(), $require_qty );
						$index_of_items_to_process[] = count( $new_items ) - 1;
						$processed_qty               += $require_qty;
					}

					if ( ( $item->get_qty() - $require_qty ) > 0 ) {
						$new_items[] = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(), $item->get_qty() - $require_qty );
						$processed_qty += $item->get_qty() - $require_qty;
					}
				} elseif ( $range->is_more( $processed_qty + $item->get_qty() ) ) {
					$require_qty = $range->get_qty();

					if ( $require_qty > 0 ) {
						$new_items[]                 = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(), $require_qty );
						$index_of_items_to_process[] = count( $new_items ) - 1;
						$processed_qty               += $require_qty;
					}

					if ( ( $item->get_qty() - $require_qty ) > 0 ) {
						$new_items[] = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(),  $item->get_qty() - $require_qty );
						$processed_qty += $item->get_qty() - $require_qty;
					}

				} else {
					$new_items[]   = $item;
					$processed_qty += $item->get_qty();
				}
			} elseif ( $range->is_in( $processed_qty ) ) {
				$require_qty = $range->get_to() + 1 - $processed_qty;
				$require_qty = $require_qty < $item->get_qty() ? $require_qty : $item->get_qty();

				if ( $require_qty > 0 ) {
					$new_items[]                 = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(),  $require_qty );
					$index_of_items_to_process[] = count( $new_items ) - 1;
					$processed_qty               += $require_qty;
				}

				if ( ( $item->get_qty() - $require_qty ) > 0 ) {
					$new_items[] = new WDP_Rule_Discount_Range_Calculation_Item( $item->get_initial_hash(), $item->get_qty() - $require_qty );
					$processed_qty += $item->get_qty() - $require_qty;
				}

			} elseif ( $range->is_more( $processed_qty ) ) {
				$new_items[] = $item;
				$processed_qty += $item->get_qty();
			}
		}

		foreach ( $index_of_items_to_process as $index ) {
			$item = $new_items[ $index ];
			$item->set_discount( $this->discount_type, $range->get_value() );
			$item->mark_as_processed();
		}

		return $new_items;
	}

}