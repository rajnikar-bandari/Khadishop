<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Price_Calculator {

	private $type;
	private $value;
	private $discount_total_limit;
	private $apply_type = 'total';


	public function __construct() {
		$this->discount_total_limit = null;
	}

	public function set_type( $type ) {
		if ( $type === 'fixed' ) {
			$type = 'price__fixed';
		} elseif ( substr( $type, 0, strlen( "set_" ) ) === "set_" ) {
			$type = substr( $type, strlen( "set_" ) );
		} elseif ( $type !== 'discount__percentage' ) {
			$this->apply_to_items();
		}

		$this->type = $type;

		return $this;
	}

	public function apply_to_items() {
		$this->apply_type = 'items';

		return $this;
	}

	public function apply_to_set() {
		$this->apply_type = 'total';

		return $this;
	}

	public function is_apply_to_total() {
		return $this->apply_type === 'total';
	}

	public function is_apply_to_items() {
		return $this->apply_type === 'items';
	}

	public function set_value( $value ) {
		$this->value = $value;

		return $this;
	}

	public function set_discount_total_limit( $value ) {
		if ( is_numeric( $value ) ) {
			$this->discount_total_limit = (float) $value;
		}

		return $this;
	}

	private function check_adjustment_total( $adjustment_total ) {
		// check only for discount
		if ( $this->discount_total_limit === null || $adjustment_total < 0 ) {
			return $adjustment_total;
		}

		return $adjustment_total > $this->discount_total_limit ? $this->discount_total_limit : $adjustment_total;
	}

	/**
	 * @param $price float
	 *
	 * @return float
	 */
	public function calculate_single_price( $price ) {
		$old_price = floatval( $price );

		$operation_type  = $this->type;
		$operation_value = $this->value;

		if ( 'free' === $operation_type ) {
			$new_price = $this->make_free();
		} elseif ( 'discount__amount' === $operation_type ) {
			$new_price = $this->make_discount_amount( $old_price, $operation_value );
		} elseif ( 'discount__percentage' === $operation_type ) {
			$new_price = $this->make_discount_percentage( $old_price, $operation_value );
		} elseif ( 'price__fixed' === $operation_type ) {
			$new_price = $this->make_price_fixed( $old_price, $operation_value );
		} else {
			$new_price = $old_price;
		}

		return (float) $new_price;
	}

	/**
	 * @param $item WDP_Cart_Item
	 *
	 * @return float
	 */
	public function calculate_price_for_item( $item ) {
		$price       = $item->get_price();
		$adjustments = $item->get_additional_adjustments();

		$price = $this->calculate_single_price( $price );

		if ( 'price__fixed' === $this->type ) {
			$price += $adjustments;
		}

		return $price;
	}

	/**
	 * @param $list_of_items WDP_Cart_Item[]|WDP_Cart_Set|WDP_Cart_Items_Collection
	 *
	 * @return float|int
	 */
	private function calculate_adjustments_left( $list_of_items ) {
		$items = array();
		if ( is_array( $list_of_items ) ) {
			foreach ( $list_of_items as $item ) {
				if ( $item instanceof WDP_Cart_Item ) {
					$items[] = $item;
				}
			}
		} elseif ( $list_of_items instanceof WDP_Cart_Set || $list_of_items instanceof WDP_Cart_Items_Collection ) {
			$items = $list_of_items->get_items();
		}

		$price_total = 0.0;
		foreach ( $items as $item ) {
			$price_total += $item->get_total_price();
		}

		$third_party_adjustments = 0.0;
		foreach ( $items as $item ) {
			$third_party_adjustments += $item->get_additional_adjustments();
		}

		$adjustments_left = 0.0;
		if ( 'discount__percentage' === $this->type ) {
			foreach ( $items as $item ) {
				/**
				 * @var $item WDP_Cart_Item
				 */
				if ( $item->is_readonly_price() ) {
					continue;
				}
				$new_price        = $this->make_discount_percentage( $item->get_total_price(), $this->value );
				$adjustments_left += $item->get_total_price() - $new_price;
			}
		} elseif ( 'price__fixed' === $this->type || 'discount__amount' === $this->type ) {
			if ( ! empty( $price_total ) ) {
				if ( 'price__fixed' === $this->type ) {
					$adjustments_left = $price_total - (float) $this->value - $third_party_adjustments;
				} else {
					$adjustments_left = (float) $this->value;
				}
			}
		}

		return $adjustments_left;
	}

	/**
	 * @param int             $rule_id
	 * @param WDP_Cart_Item[] $list_of_items
	 *
	 * @return false|WDP_Cart_Item[]
	 */
	public function calculate_price_for_items( $rule_id, $list_of_items ) {
		if ( ! $list_of_items ) {
			return false;
		}

		$items = array();
		$price_total = 0.0;
		foreach ( $list_of_items as $item ) {
			if ( ! $item->is_readonly_price() ) {
				$items[] = clone $item;
				$price_total += $item->get_total_price();
			}
		}

		$adjustments_left = $this->check_adjustment_total( $this->calculate_adjustments_left( $items ) );

		$overprice        = $adjustments_left < 0;
		$adjustments_left = $overprice ? - $adjustments_left : $adjustments_left;
		$diff             = 0.0;
		if ( $adjustments_left > 0 && $price_total > 0 ) {
			$diff = $adjustments_left / $price_total;
		}

		foreach ( $items as $item ) {
			if ( $item->is_readonly_price() ) {
				continue;
			}
			$price             = $item->get_price();
			$adjustment_amount = min( $price * $diff, $adjustments_left );
			if ( $overprice ) {
				$new_price = $this->make_overprice_amount( $price, $adjustment_amount );
			} else {
				$new_price = $this->make_discount_amount( $price, $adjustment_amount );
			}

			$item->set_price( $rule_id, $new_price );

			$adjustments_left -= $adjustment_amount;
			if ( $adjustments_left <= 0 ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * @param $set WDP_Cart_Set
	 *
	 * @return WDP_Cart_Set
	 */
	public function calculate_price_for_set( $set ) {
		$price_total = 0;
		foreach ( $set->get_items() as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			if ( ! $item->is_readonly_price() ) {
				$price_total += $item->get_total_price();
			}
		}


		if ( $this->is_apply_to_items() ) {
			foreach ( $set->get_positions() as $position ) {
				foreach ( $set->get_items_by_position( $position ) as $item ) {
					/**
					 * @var $item WDP_Cart_Item
					 */

					if ( $item->is_readonly_price() ) {
						continue;
					}

					if ( 'price__fixed' === $this->type ) {
						$adjustments_left = $item->get_price() - $item->get_additional_adjustments() - (float) $this->value;
					} else {
						$adjustments_left = (float) $this->value;
					}

					if ( $adjustments_left > 0 ) {
						$new_price = $this->make_discount_amount( $item->get_price(), $adjustments_left );
					} else {
						$new_price = $this->make_overprice_amount( $item->get_price(), ( - 1 ) * $adjustments_left );
					}

					$set->set_price_for_item( $item->get_hash(), $new_price, null, $position );
				}
			}

			return $set;
		}

		$adjustments_left = $this->check_adjustment_total( $this->calculate_adjustments_left( $set->get_items() ) );

		$overprice        = $adjustments_left < 0;
		$adjustments_left = $overprice ? - $adjustments_left : $adjustments_left;
		$diff = 0.0;
		if ( $adjustments_left > 0 && $price_total > 0 ) {
			$diff = $adjustments_left / $price_total;
		}

		foreach ( $set->get_positions() as $position ) {
			foreach ( $set->get_items_by_position( $position ) as $item ) {
				/**
				 * @var $item WDP_Cart_Item
				 */

				if ( $item->is_readonly_price() ) {
					continue;
				}

				$price             = $item->get_price();
				$adjustment_amount = min( $price * $diff, $adjustments_left );
				if ( $overprice ) {
					$new_price = $this->make_overprice_amount( $price, $adjustment_amount );
				} else {
					$new_price = $this->make_discount_amount( $price, $adjustment_amount );
				}

				$set->set_price_for_item( $item->get_hash(), $new_price, null, $position );

				$adjustments_left -= $adjustment_amount;
				if ( $adjustments_left <= 0 ) {
					break;
				}
			}
		}

		return $set;
	}

	/**
	 * @param float $price
	 * @param float $percentage
	 *
	 * @return float
	 */
	protected function make_discount_percentage( $price, $percentage ) {
		if ( $percentage < 0 ) {
			return $this->check_overprice( $price, (float) $price * ( 1 - (float) $percentage / 100 ) );
		}

		return $this->check_discount( $price, (float) $price * ( 1 - (float) $percentage / 100 ) );
	}

	/**
	 * @param float $price
	 * @param float $percentage
	 *
	 * @return float
	 */
	protected function make_overprice_percentage( $price, $percentage ) {
		return $this->check_overprice( $price, (float) $price * ( 1 + (float) $percentage / 100 ) );
	}

	/**
	 * @param float $price
	 * @param float $discount_amount
	 *
	 * @return float
	 */
	private function make_discount_amount( $price, $discount_amount ) {
		return $this->check_discount( $price, (float) $price - (float) $discount_amount );
	}

	private function make_overprice_amount( $price, $overprice_amount ) {
		return $this->check_overprice( $price, (float) $price + (float) $overprice_amount );
	}

	/**
	 * @param float $price
	 * @param float $value
	 *
	 * @return float
	 */
	protected function make_price_fixed( $price, $value ) {
		$value = floatval( $value );
		if ( $price < $value ) {
			return $this->check_overprice( $price, $value );
		}

		return $this->check_discount( $price, $value );
	}

	/**
	 * @return float
	 */
	protected function make_free() {
		return 0.0;
	}

	/**
	 * @param float $old_price
	 * @param float $new_price
	 *
	 * @return float
	 */
	private function check_discount( $old_price, $new_price ) {
		$new_price = max( $new_price, 0.0 );
		$new_price = min( $new_price, $old_price );

		return (float) $new_price;
	}

	/**
	 * @param float $old_price
	 * @param float $new_price
	 *
	 * @return float
	 */
	private function check_overprice( $old_price, $new_price ) {
		$new_price = max( $new_price, 0.0 );
		$new_price = max( $new_price, $old_price );

		return (float) $new_price;
	}
}