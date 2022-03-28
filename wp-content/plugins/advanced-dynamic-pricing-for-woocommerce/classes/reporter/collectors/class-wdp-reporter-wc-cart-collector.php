<?php

class WDP_Reporter_WC_Cart_Collector {
	/**
	 * @var WDP_Cart_Calculator_Listener
	 */
	protected $listener;

	/**
	 * WDP_Reporter_Rules_Timing_Collector constructor.
	 *
	 * @param $listener WDP_Cart_Calculator_Listener
	 */
	public function __construct( $listener ) {
		$this->listener = $listener;
	}

	/**
	 * @return array
	 */
	public function collect() {
		$wc_cart_rule_report = $this->listener->get_cart_report();

		return array(
			'items'             => $this->collect_items( $wc_cart_rule_report ),
			'coupons'           => $this->collect_coupons( $wc_cart_rule_report ),
			'fees'              => $this->collect_fees( $wc_cart_rule_report ),
			'shipping_packages' => $this->collect_shipping_packages( $wc_cart_rule_report ),
			'statuses'          => isset( $wc_cart_rule_report['statuses'] ) ? $wc_cart_rule_report['statuses'] : array(),
			'stats'             => isset( $wc_cart_rule_report['stats'] ) ? $wc_cart_rule_report['stats'] : array(),
			'timing'            => isset( $wc_cart_rule_report['timing'] ) ? $wc_cart_rule_report['timing'] : array(),
		);
	}

	protected function collect_items( $wc_cart_rule_report ) {
		$coupons                            = $this->get_coupons_data_from_totals();
		$wdp_item_adjustment_coupons_totals = $coupons['item_adjustments'];
		$wdp_free_product_adjustment_coupons_totals = $coupons['free_product_adjustments'];

		if ( isset( $wc_cart_rule_report['cart'] ) ) {
			/**
			 * @var $wdp_cart WDP_Cart
			 */
			$wdp_cart = $wc_cart_rule_report['cart'];
			unset( $wc_cart_rule_report['cart'] );
		} else {
			$wdp_cart = null;
		}

		$cart_items_data = array();
		foreach ( WC()->cart->cart_contents as $cart_item_hash => $cart_content ) {
			/**
			 * @var WC_Product $product
			 *
			 */
			$product = $cart_content['data'];

			$qty          = $cart_content['quantity'];
			$single_price = $product->get_price( '' );

			$history = isset( $cart_content['wdp_rules'] ) ? $cart_content['wdp_rules'] : array();

			$calculation_history = $history;
			if ( ! is_null( $wdp_cart ) ) {
				if ( ! is_null( $cart_calc_history = $wdp_cart->get_calculation_history( $cart_item_hash ) ) ) {
					$calculation_history = $cart_calc_history;
				}
			}

			$replacements       = array();
			$item_coupon_totals = ! empty( $cart_content['wdp_gifted'] ) ? $wdp_free_product_adjustment_coupons_totals : $wdp_item_adjustment_coupons_totals;

			foreach ( $item_coupon_totals as $coupon_code => $coupons_total ) {
				foreach ( $coupons_total as $rule_id => $amount ) {
					if ( isset( $calculation_history[ $rule_id ] ) && ! in_array( $rule_id, $replacements ) ) {
						$replacements[] = $rule_id;
					}
				}
			}

			$cart_items_meta = $cart_content;
			$default_keys    = array(
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
				'wdp_original_price',
				'wdp_rules',
				'wdp_gifted',
			);
			foreach ( array_merge( $default_keys, $wdp_keys ) as $key ) {
				unset( $cart_items_meta[ $key ] );
			}

			/**
			 * Save the ordination of applied discounts
			 */
			$ordered_calculation_history = array();
			foreach ( $calculation_history as $rule_id => $amount ) {
				$ordered_calculation_history[] = array(
					'rule_id' => $rule_id,
					'amount'  => (float) $amount,
				);
			}

			$cart_items_data[ $cart_item_hash ] = array(
				'quantity'       => $qty,
				'title'          => $product->get_name(),
				'history'        => $ordered_calculation_history,
				'price'          => $single_price,
				'original_price' => isset( $cart_content['wdp_original_price'] ) ? $cart_content['wdp_original_price'] : $single_price,
				'is_on_wdp_sale' => ! empty( $cart_content['wdp_rules'] ),
				'is_wdp_gifted'  => ! empty( $cart_content['wdp_gifted'] ),
				'replacements'   => $replacements,
				'meta'           => $cart_items_meta,
				'product'        => array(
					'id'        => $product->get_id(),
					'parent_id' => $product->get_parent_id(),
					'type'      => $product->get_type(),
				),
				'product_id'     => isset( $cart_content['product_id'] ) ? $cart_content['product_id'] : "",
				'variation_id'   => isset( $cart_content['variation_id'] ) ? $cart_content['variation_id'] : "",
				'variation'      => $cart_content['variation'],
			);
		}

		return $cart_items_data;
	}

	protected function get_coupons_data_from_totals() {
		$totals = WC()->cart->get_totals();

		return array(
			'single'                   => isset( $totals['wdp_coupons']['single'] ) ? $totals['wdp_coupons']['single'] : array(),
			'grouped'                  => isset( $totals['wdp_coupons']['grouped'] ) ? $totals['wdp_coupons']['grouped'] : array(),
			'item_adjustments'         => isset( $totals['wdp_coupons']['item_adjustments'] ) ? $totals['wdp_coupons']['item_adjustments'] : array(),
			'free_product_adjustments' => isset( $totals['wdp_coupons']['free_product_adjustments'] ) ? $totals['wdp_coupons']['free_product_adjustments'] : array(),
		);
	}

	protected function collect_coupons( $wc_cart_rule_report ) {
		$coupons                                     = $this->get_coupons_data_from_totals();
		$wdp_single_coupons_totals                   = $coupons['single'];
		$wdp_grouped_coupons_totals                  = $coupons['grouped'];
		$wdp_item_adjustment_coupons_totals          = $coupons['item_adjustments'];
		$wdp_free_product_adjustments_coupons_totals = $coupons['free_product_adjustments'];

		$coupons = array();
		foreach ( WC()->cart->get_coupons() as $coupon ) {
			/**
			 * @var WC_Coupon $coupon
			 */
			$coupon_amount = WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax );
			$rules  = array();

			if ( isset( $wdp_single_coupons_totals[ $coupon->get_code() ] ) ) {
				$rule_id = $wdp_single_coupons_totals[ $coupon->get_code() ];

				$rules = array( $rule_id => $coupon_amount );
			}

			if ( isset( $wdp_grouped_coupons_totals[ $coupon->get_code() ] ) ) {
				foreach ( $wdp_grouped_coupons_totals[ $coupon->get_code() ] as $rule_id => $amount ) {
					$rules[ $rule_id ] = isset( $rules[ $rule_id ] ) ? $rules[ $rule_id ] + $amount : $amount;
				}
			}

			if ( isset( $wdp_item_adjustment_coupons_totals[ $coupon->get_code() ] ) ) {
				foreach ( $wdp_item_adjustment_coupons_totals[ $coupon->get_code() ] as $rule_id => $amount ) {
					$rules[ $rule_id ] = isset( $rules[ $rule_id ] ) ? $rules[ $rule_id ] + $amount : $amount;
				}
			}

			if ( isset( $wdp_free_product_adjustments_coupons_totals[ $coupon->get_code() ] ) ) {
				foreach ( $wdp_free_product_adjustments_coupons_totals[ $coupon->get_code() ] as $rule_id => $amount ) {
					$rules[ $rule_id ] = isset( $rules[ $rule_id ] ) ? $rules[ $rule_id ] + $amount : $amount;
				}
			}

			$coupons[] = array(
				'code'   => $coupon->get_code(),
				'amount' => $coupon_amount,
				'rules'  => $rules,
			);
		}

		return $coupons;
	}

	protected function collect_fees( $wc_cart_rule_report ) {
		$totals          = WC()->cart->get_totals();
		$wdp_fees_totals = isset( $totals['wdp_fees'] ) ? $totals['wdp_fees'] : array();

		$fees = array();
		foreach ( WC()->cart->get_fees() as $fee ) {
			/**
			 * @var stdClass $fee
			 */
			$rules = array();

			if ( isset( $wdp_fees_totals[ $fee->name ] ) ) {
				$rules = $wdp_fees_totals[ $fee->name ];
			}

			$fees[ $fee->name ] = array(
				'id'     => $fee->id,
				'name'   => $fee->name,
				'amount' => $fee->total,
				'rules'  => $rules,
			);
		}

		return $fees;
	}

	protected function collect_shipping_packages( $wc_cart_rule_report ) {
		$shipping_packages = array();
		$packages          = WC()->shipping()->get_packages();

		foreach ( $packages as $index => $package ) {
			$shipping_list = array();
			foreach ( $package['rates'] as $rate ) {
				/**
				 * @var WC_Shipping_Rate $rate
				 */
				$cost          = (float) $rate->get_cost();
				$meta          = $rate->get_meta_data();
				$original_cost = $cost;
				$is_on_sale    = false;
				$rules         = array();
				$is_free       = false;

				if ( isset( $meta['_wdp_initial_cost'] ) ) {
					$original_cost = (float) $meta['_wdp_initial_cost'];
					$is_on_sale    = true;
				}

				if ( isset( $meta['_wdp_rules'] ) ) {
					$rules = json_decode( $meta['_wdp_rules'], true );
				}

				if ( isset( $meta['_wdp_free_shipping'] ) ) {
					$is_free = wc_string_to_bool( $meta['_wdp_free_shipping'] );
				}

				$shipping_list[ $rate->get_id() ] = array(
					'label'          => $rate->get_label(),
					'cost'           => $cost,
					'original_cost'  => $original_cost,
					'is_on_wdp_sale' => $is_on_sale,
					'rules'          => $rules,
					'is_wdp_free'    => $is_free,
				);
			}

			$package_title                       = __( 'Package', 'advanced-dynamic-pricing-for-woocommerce' ) . ' ' . ( $index + 1 );
			$shipping_packages[ $package_title ] = $shipping_list;
		}

		return $shipping_packages;
	}

}