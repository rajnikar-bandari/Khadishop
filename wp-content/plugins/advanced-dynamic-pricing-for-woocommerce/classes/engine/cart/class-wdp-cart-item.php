<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Item {
	/**
	 * @var int
	 */
	private $qty;

	/**
	 * @var float
	 */
	private $initial_price;

	/**
	 * @var float
	 */
	private $price;

	/**
	 * @var string
	 */
	private $hash;

	/**
	 * @var string
	 */
	private $calc_hash;

	/**
	 * @var boolean
	 */
	private $immutable;

	/**
	 * @var boolean
	 */
	private $readonly_price;

	/**
	 * @var boolean
	 */
	private $apply_to_initial_price;

	/**
	 * @var boolean
	 */
	private $temporary = false;

	private $history = array();

	/**
	 * For convert exact rule discount to coupon/fee and revert discount from price
	 *
	 * @var array
	 */
	private $rules_to_replace = array();

	/**
	 * @var float
	 */
	private $additional_adjustments;

	/**
	 * @var int|null
	 */
	private $first_range_discount_rule_id = null;

	/**
	 * @var
	 */
	private $pos;

	public function __clone() {
		$this->recalculate_hash();
	}

	public function __construct( $hash, $price, $pos, $qty = 1 ) {
		$this->hash  = (string)$hash;
		$this->initial_price = (float)$price;
		$this->price = (float)$price;
		$this->qty = (float)$qty;
		$this->immutable = false;
		$this->readonly_price = false;
		$this->apply_to_initial_price = false;
		$this->pos                    = (int) $pos;
		$this->additional_adjustments = floatval(0);
	}

	/**
	 * @param float $adj
	 */
	public function set_additional_adjustments( $adj ) {
		$this->additional_adjustments = $adj;
	}

	/**
	 * @return float
	 */
	public function get_additional_adjustments() {
		return $this->additional_adjustments;
	}

	public function inc_qty( $qty ) {
		$this->qty += $qty;
		$this->recalculate_hash();
	}

	/**
	 * @return int
	 */
	public function get_pos() {
		return $this->pos;
	}

	public function set_qty( $qty ) {
		$this->qty = $qty;
		$this->recalculate_hash();
	}

	public function dec_qty( $qty ) {
		if ( $this->qty < $qty ) {
			throw new Exception( 'Negative item quantity.' );
		}

		$this->qty -= $qty;
		$this->recalculate_hash();
	}

	public function is_enough_qty( $qty ) {
		return $this->qty >= $qty;
	}

	public function make_immutable() {
		$this->immutable = true;
		$this->recalculate_hash();
	}

	public function make_readonly_price() {
		$this->readonly_price = true;
		$this->recalculate_hash();
	}

	public function adjust_initial_price() {
		$this->apply_to_initial_price = true;
	}

	public function mark_as_temporary() {
		$this->temporary = true;
		$this->recalculate_hash();
	}

	public function is_immutable() {
		return $this->immutable;
	}

	public function is_readonly_price() {
		return $this->readonly_price;
	}

	public function is_temporary() {
		return $this->temporary;
	}

	public function get_price() {
		return $this->get_price_to_adjust();
	}

	protected function get_price_to_adjust() {
		return $this->apply_to_initial_price ? $this->initial_price : $this->price;
	}

	public function get_calculated_price() {
		return $this->price;
	}

	public function get_total_price() {
		return $this->get_price_to_adjust() * $this->qty;
	}

	public function get_hash() {
		return $this->hash;
	}

	public function get_calc_hash() {
		return $this->calc_hash;
	}

	private function recalculate_hash() {
		$data = array(
			'prototype_hash' => $this->hash,
			'initial_price'  => $this->initial_price,
			'price'          => $this->price,
			'qty'            => $this->qty,
			'immutable'      => $this->immutable,
			'temporary'      => $this->temporary,
			'adjust'         => $this->apply_to_initial_price,
		);

		$this->calc_hash = md5( json_encode( $data ) );
	}

	public function calc_no_price_hash() {
		$data = array(
			'prototype_hash' => $this->hash,
			'initial_price'  => $this->initial_price,
//			'price'          => $this->price,
			'qty'            => $this->qty,
			'immutable'      => $this->immutable,
			'temporary'      => $this->temporary,
			'adjust'         => $this->apply_to_initial_price,
		);

		return md5( json_encode( $data ) );
	}

	public function get_qty() {
		return $this->qty;
	}

	public function get_initial_price() {
		return $this->initial_price;
	}

	public function set_price( $rule_id, $price ) {
		if ( $this->readonly_price ) {
			return;
		}

		if ( ! isset( $this->history[ $rule_id ] ) ) {
			$this->history[ $rule_id ] = array();
		}
		$this->history[$rule_id][] = $this->get_price_to_adjust() - $price;

		/**
		 * Recalculate price here
		 * Of course, we must sum all discounts, get price to adjust (depends ot $this->apply_to_initial_price)
		 * and subtract them every time, but if price to adjust equals $this->price we sure that new price do not
		 * requiring recalculate.
		 */
		if ( $this->apply_to_initial_price ) {
			$discount = floatval( 0 );

			foreach ( $this->history as $rule_id => $amounts ) {
				$discount += array_sum( $amounts );
			}

			$this->price = $this->initial_price - $discount;
		} else {
			$this->price = $price;
		}

		$this->recalculate_hash();
	}

	public function get_history() {
		return $this->history;
	}

	public function exclude_rule_adjustments( $rule_id, $adjustment_name ) {
		// if excluded in rule, global options not able to rewrite
		if ( ! empty( $adjustment_name ) && ! isset( $this->rules_to_replace[ $rule_id ] ) ) {
			$this->rules_to_replace[ $rule_id ] = $adjustment_name;
		}
	}

	public function get_exclude_rules_hash() {
		return md5( json_encode( $this->rules_to_replace ) );
	}

	/**
	 * @param bool $oblivion
	 * @param null|array $rule_ids_to_obliviation
	 *
	 * @return array
	 */
	public function replace_rules_adjustments( $oblivion = false, $rule_ids_to_obliviation = null ) {
		$list_of_coupons_data = array();

		foreach ( $this->rules_to_replace as $rule_id => $coupon_name ) {
			if ( ! isset( $this->history[ $rule_id ] ) ) {
				continue;
			}

			$discount_amount = array_sum( $this->history[ $rule_id ] );
			$this->price     += $discount_amount;
			$list_of_coupons_data[$rule_id] =array(
				'amount' => $discount_amount * $this->qty,
				'name' => $coupon_name,
			);

			if ( $oblivion ) {
				if ( is_null( $rule_ids_to_obliviation ) || ( is_array( $rule_ids_to_obliviation ) && in_array( $rule_id, $rule_ids_to_obliviation ) ) ) {
					unset( $this->history[ $rule_id ] );
				}
			}
		}

		return $list_of_coupons_data;
	}

	public function is_at_least_one_rule_changed_price() {
		$history_rule_ids     = array_keys( $this->history );
		$rules_to_replace_ids = array_keys( $this->rules_to_replace );

		return count( array_diff( $history_rule_ids, $rules_to_replace_ids ) );
	}

	public function are_rules_applied() {
		return count( $this->history );
	}

	public function is_price_changed() {
		foreach ( $this->history as $rule_id => $amounts ) {
			if ( floatval( array_sum( $amounts ) ) !== floatval( 0 ) ) {
				return true;
			}
		}

		return false;
	}

	public function set_first_discount_range_rule( $rule_id ) {
		if ( is_null( $this->first_range_discount_rule_id ) ) {
			$this->first_range_discount_rule_id = $rule_id;
			$this->recalculate_hash();
		}
	}

	public function get_first_discount_range_rule() {
		return $this->first_range_discount_rule_id;
	}
}