<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait WDP_Time_Comparison {
	/**
	 * @param int    $time
	 * @param int    $comparison_time
	 * @param string $comparison_method
	 *
	 * @return bool
	 */
	public function check_time( $time, $comparison_time, $comparison_method ) {
		$result = false;

		if ( $comparison_method === 'later' ) {
			$result = $time > $comparison_time;
		} elseif ( $comparison_method === 'earlier' ) {
			$result = $time < $comparison_time;
		} elseif ( $comparison_method === 'from' ) {
			$result = $time >= $comparison_time;
		} elseif ( $comparison_method === 'to' ) {
			$result = $time <= $comparison_time;
		} elseif ( $comparison_method === 'specific_date' ) {
			$result = $time == $comparison_time;
		}

		return $result;
	}
}