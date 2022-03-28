<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_WC_Cart_Translator {
	/**
	 * @return float
	 */
	public function get_amount_saved() {
		$cart_items   = WC()->cart->cart_contents;
		$amount_saved = floatval( 0 );

		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['wdp_rules'] ) ) {
				continue;
			}

			foreach ( $cart_item['wdp_rules'] as $id => $amount_saved_by_rule ) {
				$amount_saved += (float) $amount_saved_by_rule * (float) $cart_item['quantity'];
			}
		}

		foreach ( $this->get_segmented_wdp_coupons() as $coupon_data ) {
			if ( $coupon_data['type'] !== 'item_adj' && $coupon_data['type'] !== 'free_item_adj' ) {
				$amount_saved += floatval( $coupon_data['amount'] );
			}
		}

		foreach ( $this->get_segmented_wdp_fees() as $fee_data ) {
			if ( $fee_data['type'] !== 'item_adjustments' ) {
				$amount_saved -= $fee_data['amount'];
			}
		}

		return (float) apply_filters( 'wdp_amount_saved', $amount_saved, $cart_items );
	}

	public function get_external_keys( $wc_cart_item ) {
		$external_keys = array();

		$default_keys = array(
			'key',
			'product_id',
			'variation_id',
			'variation',
			'quantity',
			'data',
			'data_hash',
			'line_tax_data',
			'line_subtotal',
			'line_subtotal_tax',
			'line_total',
			'line_tax',
		);

		$wdp_keys = array(
			'wdp_gifted',
			'wdp_original_price',
			'wdp_history',
			'wdp_rules',
			'rules',
			'wdp_rules_for_singular',
		);

		foreach ( $wc_cart_item as $key => $value ) {
			if ( ! in_array( $key, array_merge( $default_keys, $wdp_keys ) ) ) {
				$external_keys[] = $key;
			}
		}

		return $external_keys;
	}

	/**
	 * @return array
	 */
	protected function get_segmented_wdp_fees() {
		$totals       = WC()->cart->get_totals();
		$applied_fees = isset( $totals['wdp_fees_by_type'] ) ? $totals['wdp_fees_by_type'] : array();

		return $applied_fees;
	}

	/**
	 * @return WC_Coupon[]
	 */
	protected function get_segmented_wdp_coupons() {
		$totals          = WC()->cart->get_totals();
		$applied_coupons = isset( $totals['wdp_coupons'] ) ? $totals['wdp_coupons'] : array();
		$single_coupons_totals = isset( $applied_coupons['single'] ) ? $applied_coupons['single'] : array(); // e.g. percentage
		$grouped_coupons_totals = isset( $applied_coupons['grouped'] ) ? $applied_coupons['grouped'] : array();
		$item_adjustment_coupons_totals = isset( $applied_coupons['item_adjustments'] ) ? $applied_coupons['item_adjustments'] : array();
		$free_product_adjustments_coupons_totals = isset( $applied_coupons['free_product_adjustments'] ) ? $applied_coupons['free_product_adjustments'] : array();

		$coupons = array();
		foreach ( array( 'grouped'       => $grouped_coupons_totals,
		                 'item_adj'      => $item_adjustment_coupons_totals,
		                 'free_item_adj' => $free_product_adjustments_coupons_totals ) as $type => $coupon_totals ) {
			foreach ( $coupon_totals as $coup_code => $rule_amounts ) {
				foreach ( $rule_amounts as $rule_id => $amount ) {
					$coupons[] = array(
						'code'    => $coup_code,
						'rule_id' => $rule_id,
						'amount'  => $amount,
						'type'    => $type,
					);
				}
			}
		}

		foreach ( WC()->cart->get_coupons() as $coupon ) {
			/**
			 * @var $coupon WC_Coupon
			 */
			$amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );

			if ( isset( $single_coupons_totals[ $coupon->get_code() ] ) ) {
				$rule_id = $single_coupons_totals[ $coupon->get_code() ];

				$coupons[] = array(
					'code'    => $coupon->get_code(),
					'rule_id' => $rule_id,
					'amount'  => $amount,
					'type'    => 'single',
				);
			}
		}

		return $coupons;
	}
}