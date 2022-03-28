<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Selected_Products extends WDP_Rule_Range_Adjustments_Qty_Based_Selected_Products {
	public static function get_available_discount_types() {
		return WDP_Rule_Range_Discount_Types::get_all_types();
	}
}