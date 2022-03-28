<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Discount_Types {
	public static function get_all_types() {
		$types = array(
			self::discount_amount(),
			self::set_discount_amount(),
			self::discount_percentage(),
			self::price_fixed(),
			self::set_price_fixed(),
		);

		return self::format_output( $types );
	}

	public static function get_set_types() {
		$types = array(
			self::set_discount_amount(),
			self::discount_percentage(),
			self::set_price_fixed(),
		);

		return self::format_output( $types );
	}

	public static function get_item_types() {
		$types = array(
			self::discount_amount(),
			self::discount_percentage(),
			self::price_fixed(),
		);

		return self::format_output( $types );
	}

	private static function discount_amount() {
		return array(
			'key'   => 'discount__amount',
			'label' => __( 'Fixed discount for item', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function set_discount_amount() {
		return array(
			'key'   => 'set_discount__amount',
			'label' => __( 'Fixed discount for set', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function discount_percentage() {
		return array(
			'key'   => 'discount__percentage',
			'label' => __( 'Percentage discount', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function price_fixed() {
		return array(
			'key'   => 'price__fixed',
			'label' => __( 'Fixed price for item', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function set_price_fixed() {
		return array(
			'key'   => 'set_price__fixed',
			'label' => __( 'Fixed price for set', 'advanced-dynamic-pricing-for-woocommerce' ),
		);
	}

	private static function format_output( $types ) {
		return array_combine( array_column( $types, 'key' ), array_column( $types, 'label' ) );
	}
}