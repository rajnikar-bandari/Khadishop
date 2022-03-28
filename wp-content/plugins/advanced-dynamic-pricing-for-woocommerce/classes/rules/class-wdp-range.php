<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range {
	private $from;
	private $to;
	private $range_data;
	/**
	 * 1 - standard
	 * 2 - grab as much quantity as possible
	 * 3 - grab as soon as can
	 *
	 * @var integer
	 */
	private $mode = 1;

	public function __construct( $from, $to, $range_data ) {
		$this->from       = is_numeric( $from ) && $from >= 0 ? (float) $from : 0.0;
		$this->to         = $to !== '' ? (float) $to : INF;

		$this->range_data = $range_data;

		if ( 0 === $this->get_qty() ) {
			$this->mode = 3;
		} elseif ( 0 < $this->get_qty() ) {
			$this->mode = 2;
		}
	}

	public function is_range_valid() {
		return $this->lte_end( $this->from );
	}

	/**
	 * Less than finish value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function lt_end( $value ) {
		return $value < $this->to;
	}

	/**
	 * Less than or equal finish value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function lte_end( $value ) {
		return $value <= $this->to;
	}

	/**
	 * Equal finish value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function is_equal_end( $value ) {
		return $value === $this->to;
	}

	/**
	 * Greater than finish value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function gt_end( $value ) {
		return $this->to < $value;
	}

	/**
	 * Greater than or equal finish value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function gte_end( $value ) {
		return $this->to <= $value;
	}

	/**
	 * Less than start value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function lt_start( $value ) {
		return $value < $this->from;
	}

	/**
	 * Less than or equal start value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function lte_start( $value ) {
		return $value <= $this->from;
	}

	/**
	 * Equal start value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function is_equal_start( $value ) {
		return $value === $this->from;
	}

	/**
	 * Greater than start value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function gt_start( $value ) {
		return $this->from < $value;
	}

	/**
	 * Greater than or equal start value of the interval
	 *
	 * @param integer|float $value
	 *
	 * @return bool
	 */
	private function gte_start( $value ) {
		return $this->from <= $value;
	}

	/**
	 * Is value in interval?
	 *
	 * @param integer|float $value
	 * @param boolean $with_mode
	 *
	 * @return bool
	 */
	public function is_in( $value, $with_mode = true ) {
		$result = false;

		if ( $with_mode ) {
			if ( 1 === $this->mode ) {
				$result = $this->from <= $value && $this->lte_end( $value );
			} elseif ( 2 === $this->mode ) {
				$result = $this->is_equal_end( $value );
			} elseif ( 3 === $this->mode ) {
				$result = $this->is_equal_start( $value );
			}
		} else {
			$result = $this->from <= $value && $this->lte_end( $value );
		}

		return $result;
	}

	/**
	 * Is value greater than finish value of the interval?
	 *
	 * @param integer|float $value
	 * @param boolean $with_mode
	 *
	 * @return bool
	 */
	public function is_greater( $value, $with_mode = true ) {
		$result = false;

		if ( $with_mode ) {
			if ( 1 === $this->mode || 2 === $this->mode ) {
				$result = $this->gt_end( $value );
			} elseif ( 3 === $this->mode ) {
				$result = $this->gt_start( $value );
			}
		} else {
			$result = $this->gt_end( $value );
		}


		return $result;
	}

	/**
	 * Is value greater than finish value of the interval inclusively?
	 *
	 * @param integer|float $value
	 * @param boolean $with_mode
	 *
	 * @return bool
	 */
	public function is_greater_inc( $value, $with_mode = true ) {
		$result = false;

		if ( $with_mode ) {
			if ( 1 === $this->mode ) {
				$result = $this->gte_end( $value );
			} elseif ( 3 === $this->mode ) {
				$result = $this->gt_start( $value );
			} elseif ( 2 === $this->mode ) {
				$result = $this->gt_end( $value );
			}
		} else {
			$result = $this->gte_end( $value );
		}


		return $result;
	}

	/**
	 * Is value less than start value of the interval?
	 *
	 * @param integer|float $value
	 * @param boolean $with_mode
	 *
	 * @return bool
	 */
	public function is_less( $value, $with_mode = true ) {
		$result = false;

		if ( $with_mode ) {
			if ( 1 === $this->mode || 3 === $this->mode ) {
				$result = $this->lt_start( $value );
			} elseif ( 2 === $this->mode ) {
				$result = $this->lt_end( $value );
			}
		} else {
			$result = $this->lt_start( $value );
		}


		return $result;
	}

	/**
	 * Is value less than start value of the interval inclusively?
	 *
	 * @param integer|float $value
	 * @param boolean       $with_mode
	 *
	 * @return bool
	 */
	public function is_less_inc( $value, $with_mode = true ) {
		$result = false;

		if ( $with_mode ) {
			if ( 1 === $this->mode ) {
				return $this->lte_start( $value );
			} elseif ( 2 === $this->mode ) {
				return $this->lt_end( $value );
			} elseif ( 3 === $this->mode ) {
				return $this->lt_start( $value );
			}
		} else {
			$result = $this->lt_start( $value );
		}

		return $result;
	}

	public function get_value() {
		return $this->range_data;
	}

	public function get_from() {
		return $this->from;
	}

	public function get_to() {
		return $this->to;
	}

	public function get_qty() {
		return $this->to - $this->from;
	}

	public function get_qty_inc() {
		return $this->to - $this->from + 1;
	}

	public function get_mode_value_to() {
		if ( 1 === $this->mode || 2 === $this->mode ) {
			return $this->get_to();
		} elseif ( 3 === $this->mode ) {
			return $this->get_from();
		}

		return false;
	}
}