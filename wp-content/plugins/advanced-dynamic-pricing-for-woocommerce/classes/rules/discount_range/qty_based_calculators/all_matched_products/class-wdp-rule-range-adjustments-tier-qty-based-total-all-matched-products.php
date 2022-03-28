<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Tier_Qty_Based_All_Matched_Products extends WDP_Rule_Range_Adjustments_Qty_Based_All_Matched_Products {
	public static function get_available_discount_types() {
		return WDP_Rule_Range_Discount_Types::get_item_types();
	}
}