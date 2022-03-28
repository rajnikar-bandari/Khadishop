<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait WDP_Comparison {
	/**
	 * @param mixed  $value
	 * @param array  $comparison_list
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_value_with_list( $value, $comparison_list, $comparison_method = 'in_list' ) {
		$result = false;

		if ( 'in_list' === $comparison_method ) {
			$result = in_array( $value, $comparison_list );
		} elseif ( 'not_in_list' === $comparison_method ) {
			$result = ! in_array( $value, $comparison_list );
		}

		return $result;
	}

	/**
	 * @param array  $list
	 * @param array  $comparison_list
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_lists( $list, $comparison_list, $comparison_method = 'in_list' ) {
		$result = false;

//		if ( 'in_list' === $comparison_method ) {
//			$result = count( array_intersect( $list, $comparison_list ) ) == count( $comparison_list );
//		} elseif ( 'not_in_list' === $comparison_method ) {
//			$result = count( array_intersect( $list, $comparison_list ) ) == 0;
		if ( 'at_least_one_any' === $comparison_method ) {
			$result = ! empty( $list );
		} elseif ( 'at_least_one' === $comparison_method OR 'in_list' === $comparison_method ) {
			$result = count( array_intersect( $comparison_list, $list ) ) > 0;
		} elseif ( 'all' === $comparison_method ) {
			$result = count( array_intersect( $comparison_list, $list ) ) == count( $comparison_list );
		} elseif ( 'only' === $comparison_method ) {
			$result = array_diff( $comparison_list, $list ) === array_diff( $list, $comparison_list ) && count($comparison_list) === count($list);
		} elseif ( 'none' === $comparison_method OR 'not_in_list' === $comparison_method ) {
			$result = count( array_intersect( $list, $comparison_list ) ) === 0;
		} elseif ( 'none_at_all' === $comparison_method ) {
			$result = empty( $list );
		}
		return $result;
	}

	/**
	 * @param mixed  $value
	 * @param mixed  $comparison_value
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_values( $value, $comparison_value, $comparison_method = '<' ) {
		if ( $comparison_method === 'in_range' ) {
			$start  = isset( $comparison_value[0] ) ? (float) $comparison_value[0] : null;
			$finish = isset( $comparison_value[1] ) ? (float) $comparison_value[1] : null;

			return $this->value_in_range( $value, $start, $finish );
		}

		$result = false;

		if ( '<' === $comparison_method ) {
			$result = $value < $comparison_value;
		} elseif ( '<=' === $comparison_method ) {
			$result = $value <= $comparison_value;
		} elseif ( '>=' === $comparison_method ) {
			$result = $value >= $comparison_value;
		} elseif ( '>' === $comparison_method ) {
			$result = $value > $comparison_value;
		} elseif ( '=' === $comparison_method ) {
			$result = $value === $comparison_value;
		} elseif ( '!=' === $comparison_method ) {
			$result = $value !== $comparison_value;
		}

		return $result;
	}

	/**
	 * @param int $value
	 * @param int $start
	 * @param int $finish
	 *
	 * @return bool
	 */
	public function value_in_range( $value, $start, $finish ) {
		return $start && $finish && $start <= $value && $finish >= $value;
	}

	/**
	 * @param integer  $value Time in unix format
	 * @param integer  $comparison_value Time in unix format
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function compare_time_unix_format( $value, $comparison_value, $comparison_method = 'later' ) {
		$result = false;

		if ( $comparison_method === 'later' ) {
			$result = $value > $comparison_value;
		} elseif ( $comparison_method === 'earlier' ) {
			$result = $value < $comparison_value;
		} elseif ( $comparison_method === 'from' ) {
			$result = $value >= $comparison_value;
		} elseif ( $comparison_method === 'to' ) {
			$result = $value <= $comparison_value;
		} elseif ( $comparison_method === 'specific_date' ) {
			$result = $value == $comparison_value;
		}

		return $result;
	}
}