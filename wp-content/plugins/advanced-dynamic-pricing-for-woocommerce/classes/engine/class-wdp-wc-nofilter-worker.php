<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_WC_NoFilter_Worker {
	/**
	 * @param WC_Cart $wcCart
	 */
	public function calculate_totals( &$wcCart ) {
		try {
			$reflection = new \ReflectionClass( $wcCart );
			$property   = $reflection->getMethod( 'reset_totals' );
			$property->setAccessible( true );
			$property->invoke( $wcCart );
		} catch ( \ReflectionException $e ) {
			return;
		}

		try {
			global $wp_filter;

			$filters     = array(
				'woocommerce_product_get_price',
				'woocommerce_product_variation_get_price'
			);
			$tmp_filters = array();

			foreach ( $filters as $filter ) {
				if ( isset($wp_filter[ $filter ]) ) {
					$tmp_filters[ $filter ] = $wp_filter[ $filter ];
					unset( $wp_filter[ $filter ] );
				}
			}

			new \WC_Cart_Totals( $wcCart );

			foreach ( $tmp_filters as $tag => $hook ) {
				$wp_filter[ $tag ] = $tmp_filters[ $tag ];
			}
		} catch ( \Exception $e ) {
			return;
		}
	}
}