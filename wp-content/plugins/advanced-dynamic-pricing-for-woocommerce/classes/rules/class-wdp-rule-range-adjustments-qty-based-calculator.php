<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Range_Adjustments_Qty_Based_Calculator {
	private static $calculators = array(
		'bulk' => array(
			'all'                         => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_All_Matched_Products',
			'total_qty_in_cart'           => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Total_Qty_In_Cart',
			'product_categories'          => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Product_Categories',
			'product_selected_categories' => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Product_Selected_Categories',
			'selected_products'			  => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Selected_Products',
			'sets'                        => 'WDP_Rule_Range_Adjustments_Bulk_Qty_Based_Sets',
			'product'                     => 'WDP_Rule_Range_Adjustments_Qty_Based_Product',
			'variation'                   => 'WDP_Rule_Range_Adjustments_Qty_Based_Variation',
			'cart_position'				  => 'WDP_Rule_Range_Adjustments_Qty_Based_Cart_Position'
		),
		'tier' => array(
			'all'                         => 'WDP_Rule_Range_Adjustments_Tier_Qty_Based_All_Matched_Products',
			'product_selected_categories' => 'WDP_Rule_Range_Adjustments_Tier_Qty_Based_Product_Selected_Categories',
			'selected_products'			  => 'WDP_Rule_Range_Adjustments_Tier_Qty_Based_Selected_Products',
			'sets'                        => 'WDP_Rule_Range_Adjustments_Tier_Qty_Based_Sets',
			'product'                     => 'WDP_Rule_Range_Adjustments_Qty_Based_Product',
			'variation'                   => 'WDP_Rule_Range_Adjustments_Qty_Based_Variation',
		),
	);

	/**
	 * @var WDP_Rule_Range_Adjustments_Qty_Based
	 */
	private $instance;

	/**
	 * WDP_Rule_Range_Adjustments_Qty_Based_Calculator constructor.
	 *
	 * @param $rule_id int
	 * @param $adj_type
	 * @param $qty_based
	 *
	 * @throws Exception
	 */
	public function __construct( $rule_id, $adj_type, $qty_based ) {
		if ( isset( self::$calculators[ $adj_type ][ $qty_based ] ) ) {
			$class_name = self::$calculators[ $adj_type ][ $qty_based ];
		} else {
			$class_name = self::$calculators[ $adj_type ]['all'];
		}

		if ( ! class_exists( $class_name ) ) {
			throw new Exception( __( 'Invalid quantity based parameter.', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}

		$this->instance = new $class_name( $rule_id );
	}

	public function check_instance() {
		return (boolean) $this->instance;
	}

	public function apply_additional_data( $data ) {
		return $this->instance->apply_additional_data( $data );
	}

	/**
	 * @param $bulk_calc WDP_Rule_Discount_Range_Calculation
	 * @param $cart WDP_Cart
	 * @param $set_collection WDP_Cart_Set_Collection
	 *
	 * @return WDP_Cart_Set_Collection
	 */
	public function process( $bulk_calc, $cart, $set_collection ) {
		return $this->instance->process( $bulk_calc, $cart, $set_collection );
	}

	public function translate( $language_code ) {
		if ( method_exists( $this->instance, 'translate' ) ) {
			call_user_func( array( $this->instance, 'translate' ), $language_code );
		}
	}

	public function check_discount_type( $discount_type ) {
		return array_key_exists( $discount_type, $this->instance->get_available_discount_types() );
	}

	public static function get_all_available_types() {
		$all_types = array();

		foreach (self::$calculators as $adj_type => $qty_based_calcs) {
			foreach ( $qty_based_calcs as $qty_based => $qty_based_calc ) {
				/**
				 * @var $qty_based_calc WDP_Rule_Range_Adjustments_Qty_Based
				 */
				$all_types[$adj_type][$qty_based] = array(
					'label' => $qty_based_calc::get_label(),
					'items' => $qty_based_calc::get_available_discount_types(),
				);

			}
		}

		return $all_types;
	}
}