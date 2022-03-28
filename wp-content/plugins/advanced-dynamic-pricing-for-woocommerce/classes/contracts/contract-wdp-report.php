<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface WDP_Report {

	/**
	 * @return string
	 */
	public function get_title();

	/**
	 * @return string
	 */
	public function get_subtitle();

	/**
	 * @return string
	 */
	public function get_type();

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	public function get_data( $params );
}