<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_WC_Order_Translator {
	/**
	 * @param int $order_id
	 *
	 * @return float
	 */
	public function get_amount_saved( $order_id ) {
		$rules = WDP_Database::get_applied_rules_for_order( $order_id );

		$saved = floatval( 0 );

		foreach ( $rules as $row ) {
			$saved += floatval( $row->amount + $row->extra + $row->gifted_amount );
		}

		return (float) $saved;
	}
}