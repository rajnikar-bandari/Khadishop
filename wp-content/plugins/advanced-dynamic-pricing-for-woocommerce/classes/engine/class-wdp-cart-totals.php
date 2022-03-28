<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Totals {
	/**
	 * @var WDP_Cart_Context
	 */
	private $context;

	/**
	 * @var array
	 */
	private $fees;

	/**
	 * @var array
	 */
	private $coupons;

	/**
	 * @var array [coupon_name] => $coupon_ids[]
	 */
	private $grouped_coupons;

	/**
	 * @var array [coupon_name] => $coupon_id
	 */
	private $single_coupons;

	/**
	 * @var array [coupon_name] => $coupon_ids[]
	 */
	private $item_adjustment_coupons;

	private $applied_coupons;
	private $external_coupons;

	/**
	 * @var WDP_Cart_Adjustments_Shipping
	 */
	private $shipping_adjustments;

	private $initial_totals = array();

	/**
	 * WDP_Cart_Totals constructor.
	 *
	 * @param $cart WDP_Cart
	 * @param $wc_cart WC_Cart
	 */
	public function __construct( $cart, $wc_cart ) {
		if ( is_null( $wc_cart ) ) {
			$wc_cart = WC()->cart;
		}

		$wcNoFilterWorker = new WDP_WC_NoFilter_Worker();

		$cart = apply_filters( 'wdp_cart_before_totals', $cart, $wc_cart );
		/**
		 * @var $cart WDP_Cart
		 */

		$this->context = $cart->get_context();
		$wc_cart->get_customer()->set_is_vat_exempt( $this->context->get_tax_exempt() );

		$this->initial_totals = $cart->get_initial_cart_totals();

		$this->external_coupons = $this->context->get_option('disable_external_coupons') ? array() : $cart->get_external_coupons();

		$cart_adjustments           = $cart->get_adjustments();
		$this->shipping_adjustments = isset( $cart_adjustments['shipping'] ) ? $cart_adjustments['shipping'] : null;
		$this->coupons              = isset( $cart_adjustments['coupons'] ) ? $cart_adjustments['coupons'] : array();
		$this->fees                 = isset( $cart_adjustments['fees'] ) ? $cart_adjustments['fees'] : array();

		add_filter( 'woocommerce_coupon_message', array( $this, 'no_coupon_msg' ), 1000, 3 );
		add_filter( 'woocommerce_coupon_error', array( $this, 'no_coupon_msg' ), 1000, 3 );

		// Store and load 'selected shipping methods'.
		// Need to prevent to drop selected method.
		// Selected shipping method rewrites after conditional shipping method appearing,
		// e.g. free_shipping which available only at certain order total amount.
		//
		// Detailed example below.
		// Before calculate totals 'free_shipping' is available.
		// Before second calculate_totals() our plugin added coupon and order amount decrease to the amount at which
		// free_shipping is not available.
		// After second calculate_totals() 'chosen_shipping_methods' resets to first calculated rate.
		// So, you cannot change shipping method.
		$selected_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		// Calculate totals to handle fee, coupons and shipping which depends on cart contents(totals)
		// e.g. coupon with min/max spend
//		$wc_cart->calculate_totals();
		$wcNoFilterWorker->calculate_totals( WC()->cart );

		$this->install_hooks( $wc_cart );
//		$wc_cart->calculate_totals();
		$wcNoFilterWorker->calculate_totals( WC()->cart );

		WC()->session->set( 'chosen_shipping_methods', $selected_shipping_methods );

		remove_filter( 'woocommerce_coupon_message', array( $this, 'no_coupon_msg' ), 1000 );
		remove_filter( 'woocommerce_coupon_error', array( $this, 'no_coupon_msg' ), 1000 );
		remove_filter( 'woocommerce_package_rates', array( $this, 'woocommerce_package_rates' ), 10 );
		remove_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'woocommerce_cart_shipping_method_full_label' ), 10 );
	}

	public function no_coupon_msg( $msg, $msg_code, $wc_coupon ) {
		return "";
	}

	public function install_hooks( $wc_cart ) {
		// items
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'store_initial_totals' ), 10, 1 );

		// fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'woocommerce_cart_calculate_fees' ), 10, 1 );

		// coupons
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'woocommerce_cart_calculate_coupons' ), 10, 1 );
		add_filter( 'woocommerce_get_shop_coupon_data', array( $this, 'woocommerce_get_shop_coupon_data' ), 10, 3 );
		$this->apply_coupons_to_wc_cart( $wc_cart );
		// delete [Remove] for coupons
		if ( $this->applied_coupons ) {
			add_filter( 'woocommerce_cart_totals_coupon_html', array(
				$this,
				'woocommerce_cart_totals_coupon_html'
			), 10, 3 );
		}

		// Do not apply external coupons to free products
		add_filter( 'woocommerce_coupon_is_valid_for_product', function ( $valid, $product, $coupon, $values ) {
			if ( ! empty( $values['wdp_gifted'] ) && isset( $this->external_coupons[ $coupon->get_code() ] ) ) {
				$valid = false;
			}

			return $valid;
		}, 10, 4 );

		do_action( 'wdp_cart_totals_install_hooks', $this );

		// apply shipping
		add_filter( 'woocommerce_package_rates', array( $this, 'woocommerce_package_rates' ), 10, 2 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array(
			$this,
			'woocommerce_cart_shipping_method_full_label'
		), 10, 2 );


		// To apply shipping we have to clear stored packages in session to allow 'woocommerce_package_rates' filter run
		foreach ( WC()->shipping()->get_packages() as $index => $value ) {
			$key = "shipping_for_package_" . $index;
			unset( WC()->session->$key );
		}
	}

	/**
	 * @param WC_Cart $wc_cart
	 */
	public function woocommerce_cart_calculate_fees( $wc_cart ) {
		$applied_fees         = array();
		$applied_fees_no_rule = array(); // external?
		$fees_tax             = array();
		$fees_tax_no_rule     = array();
		$cart_total           = $this->context->is_prices_includes_tax() ? $wc_cart->get_cart_contents_total() + $wc_cart->get_cart_contents_tax() : $wc_cart->get_cart_contents_total();

		$fees_by_type = array();

		foreach ( $this->fees as $i => $fee ) {
			$fee_amount = 0;
			$fee_type   = $fee['type'];
			$tax_class  = ! empty( $fee['tax_class'] ) ? $fee['tax_class'] : "";
			$taxable    = (boolean) $tax_class;

			if ( 'amount' === $fee_type ) {
				$fee_amount = $fee['value'];
			} elseif ( 'percentage' === $fee_type ) {
				$fee_amount = $cart_total * $fee['value'] / 100;
			} elseif ( 'item_adjustments' === $fee_type ) {
				$fee_amount = $fee['value'];
			}

			if ( ! empty( $fee_amount ) && isset( $fee['rule_id'] ) ) {
				if ( empty( $fee['not_report'] ) ) {
					if ( $this->context->is_combine_multiple_fees() || empty( $fee['name'] ) ) {
						$fee_name = $this->context->get_option( 'default_fee_name' );
					} else {
						$fee_name = $fee['name'];
					}

					$fees_tax[ $fee_name ] = array(
						'taxable'   => $taxable,
						'tax_class' => $tax_class,
					);

					if ( ! isset( $applied_fees[ $fee_name ][ $fee['rule_id'] ] ) ) {
						$applied_fees[ $fee_name ][ $fee['rule_id'] ] = 0;
					}

					$applied_fees[ $fee_name ][ $fee['rule_id'] ] += $fee_amount;

					$fees_by_type[] = array(
						'type'    => $fee_type,
						'name'    => $fee_name,
						'rule_id' => $fee['rule_id'],
						'amount'  => $fee_amount,
					);
				} else {
					$fee_name = $fee['name'];

					$fees_tax_no_rule[ $fee_name ] = array(
						'taxable'   => $taxable,
						'tax_class' => $tax_class,
					);

					if ( ! isset( $applied_fees_no_rule[ $fee_name ] ) ) {
						$applied_fees_no_rule[ $fee_name ] = 0;
					}

					$applied_fees_no_rule[ $fee_name ] += $fee_amount;
				}
			}
		}

		foreach ( $applied_fees as $fee_name => $amount_per_rule ) {
			$fee_data = apply_filters( 'wdp_cart_totals_apply_fee_data', array(
				'name'      => $fee_name,
				'amount'    => array_sum( $amount_per_rule ),
				'taxable'   => $fees_tax[ $fee_name ]['taxable'],
				'tax_class' => $fees_tax[ $fee_name ]['tax_class'],
			), $this );

			$wc_cart->add_fee( $fee_data['name'], $fee_data['amount'], $fee_data['taxable'], $fee_data['tax_class'] );
		}

		foreach ( $applied_fees_no_rule as $fee_name => $fee_amount ) {
			$fee_data = apply_filters( 'wdp_cart_totals_apply_fee_data', array(
				'name'      => $fee_name,
				'amount'    => $fee_amount,
				'taxable'   => $fees_tax_no_rule[ $fee_name ]['taxable'],
				'tax_class' => $fees_tax_no_rule[ $fee_name ]['tax_class'],
			), $this );

			$wc_cart->add_fee( $fee_data['name'], $fee_data['amount'], $fee_data['taxable'], $fee_data['tax_class'] );
		}

		$totals             = $wc_cart->get_totals();
		$totals['wdp_fees'] = $applied_fees;
		$totals['wdp_fees_by_type'] = $fees_by_type;
		$wc_cart->set_totals( $totals );
	}

	/** APPLY COUPONS */


	/**
	 * @param WC_Cart $wc_cart
	 */
	private function apply_coupons_to_wc_cart( $wc_cart ) {
		$this->grouped_coupons         = array();
		$this->single_coupons          = array();
		$this->item_adjustment_coupons = array();

		foreach ( $this->coupons as $coupon_id => $coupon ) {
			if ( empty( $coupon['value'] ) ) // skip zero coupons?
			{
				continue;
			}

			if ( 'amount' === $coupon['type'] ) {
				if ( $this->context->is_combine_multiple_discounts() || empty( $coupon['name'] ) ) {
					$coupon_name = $this->context->get_option( 'default_discount_name' );
				} else {
					$coupon_name = $coupon['name'];
				}
				$coupon_name = wc_format_coupon_code( $coupon_name );

				if ( ! isset( $this->grouped_coupons[ $coupon_name ] ) ) {
					$this->grouped_coupons[ $coupon_name ] = array();
				}
				$this->grouped_coupons[ $coupon_name ][] = $coupon_id;
			} elseif ( 'percentage' === $coupon['type'] ) {
				$template = ! empty( $coupon['name'] ) ? $coupon['name'] : $this->context->get_option( 'default_discount_name' );
				$template = wc_format_coupon_code( $template );

				$count = 1;
				do {
					$coupon_name = "{$template} #{$count}";
					$count ++;
				} while ( isset( $this->single_coupons[ $coupon_name ] ) );

				$this->single_coupons[ $coupon_name ] = $coupon_id;
			} elseif ( 'item_adjustments' === $coupon['type'] ) {
				$coupon_name = wc_format_coupon_code( $coupon['name'] );

				if ( ! isset( $this->item_adjustment_coupons[ $coupon_name ] ) ) {
					$this->item_adjustment_coupons[ $coupon_name ] = array();
				}
				$this->item_adjustment_coupons[ $coupon_name ][] = $coupon_id;
			}
		}

		// remove postfix for single %% discount
		if ( count( $this->single_coupons ) == 1 ) {
			$keys                 = array_keys( $this->single_coupons );
			$values               = array_values( $this->single_coupons );
			$this->single_coupons = array( str_replace( ' #1', '', $keys[0] ) => $values[0] );
		}

		$this->applied_coupons = array();
		add_filter( 'woocommerce_coupon_message', '__return_empty_string', 10, 3 );

		// temporary disable 'woocommerce_applied_coupon' hook
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_applied_coupon'] ) ) {
			$stored_actions = $wp_filter['woocommerce_applied_coupon'];
			unset( $wp_filter['woocommerce_applied_coupon'] );
		} else {
			$stored_actions = array();
		}

		foreach ( array_keys( $this->external_coupons ) as $code ) {
			/**
			 * @var $coupon WC_Coupon
			 */
			$wc_cart->apply_coupon( $code );
		}

		// restore hook
		if ( ! empty( $stored_actions ) ) {
			$wp_filter['woocommerce_applied_coupon'] = $stored_actions;
		}

		foreach ( array_keys( $this->grouped_coupons ) as $coupon_name ) {
			$this->applied_coupons[] = $coupon_name;
			$wc_cart->apply_coupon( $coupon_name );
		}

		foreach ( array_keys( $this->single_coupons ) as $coupon_name ) {
			$this->applied_coupons[] = $coupon_name;
			$wc_cart->apply_coupon( $coupon_name );
		}

		foreach ( array_keys( $this->item_adjustment_coupons ) as $coupon_name ) {
			$this->applied_coupons[] = $coupon_name;
			$wc_cart->apply_coupon( $coupon_name );
		}
		remove_filter( 'woocommerce_coupon_message', '__return_empty_string', 10 );
	}


	/**
	 * Trigger an action to add custom fees.
	 *
	 * @param WC_Cart $wc_cart
	 */
	public function woocommerce_cart_calculate_coupons( $wc_cart ) {
		$applied_grouped_coupons = array();
		foreach ( $this->grouped_coupons as $coupon_name => $coupon_ids ) {
			if ( ! isset( $applied_grouped_coupons[ $coupon_name ] ) ) {
				$applied_grouped_coupons[ $coupon_name ] = array();
			}
			foreach ( $coupon_ids as $coupon_id ) {
				$coupon  = $this->coupons[ $coupon_id ];
				$rule_id = isset( $coupon['rule_id'] ) ? $coupon['rule_id'] : null;
				$amount  = $coupon['value'];

				if ( ! is_null( $rule_id ) ) {
					if ( ! isset( $applied_grouped_coupons[ $coupon_name ][ $rule_id ] ) ) {
						$applied_grouped_coupons[ $coupon_name ][ $rule_id ] = 0;
					}
					$applied_grouped_coupons[ $coupon_name ][ $rule_id ] += $amount;
				}
			}
		}

		$applied_single_coupons = array();
		foreach ( $this->single_coupons as $coupon_name => $coupon_id ) {
			$coupon  = $this->coupons[ $coupon_id ];
			$rule_id = isset( $coupon['rule_id'] ) ? $coupon['rule_id'] : null;

			if ( ! is_null( $rule_id ) ) {
				$applied_single_coupons[ $coupon_name ] = $rule_id;
			}
		}

		$applied_item_adjustment_coupons         = array();
		$applied_free_product_adjustment_coupons = array();
		foreach ( $this->item_adjustment_coupons as $coupon_name => $coupon_ids ) {
			foreach ( $coupon_ids as $coupon_id ) {
				$coupon  = $this->coupons[ $coupon_id ];
				$rule_id = isset( $coupon['rule_id'] ) ? $coupon['rule_id'] : null;
				$amount  = $coupon['value'];

				if ( ! is_null( $rule_id ) ) {
					if ( ! empty( $coupon['is_item_free'] ) ) {
						$apply_obj = &$applied_free_product_adjustment_coupons;
					} else {
						$apply_obj = &$applied_item_adjustment_coupons;
					}

					if ( ! isset( $apply_obj[ $coupon_name ] ) ) {
						$apply_obj[ $coupon_name ] = array();
					}

					if ( ! isset( $apply_obj[ $coupon_name ][ $rule_id ] ) ) {
						$apply_obj[ $coupon_name ][ $rule_id ] = 0;
					}
					$apply_obj[ $coupon_name ][ $rule_id ] += $amount;
				}
			}
		}

		$applied_coupons = array(
			'grouped'                  => $applied_grouped_coupons,
			'single'                   => $applied_single_coupons,
			'item_adjustments'         => $applied_item_adjustment_coupons,
			'free_product_adjustments' => $applied_free_product_adjustment_coupons,
		);

		$totals                = $wc_cart->get_totals();
		$totals['wdp_coupons'] = $applied_coupons;
		$wc_cart->set_totals( $totals );
	}

	/**
	 * This filter allows custom coupon objects to be created on the fly.
	 *
	 * @param mixed $coupon
	 * @param mixed $data Coupon name
	 * @param WC_Coupon $coupon_obj
	 *
	 * @return array|mixed
	 */
	public function woocommerce_get_shop_coupon_data( $coupon, $data, $coupon_obj ) {
		if ( isset( $this->grouped_coupons[ $data ] ) ) {
			$grouped_coupon = array(
				'id'            => rand( 0, 1000 ),
				'discount_type' => 'fixed_cart',
				'amount'        => 0,
			);
			foreach ( $this->grouped_coupons[ $data ] as $coupon_id ) {
				if ( ! empty( $this->coupons[ $coupon_id ] ) ) {
					$coupon = $this->coupons[ $coupon_id ];
					$grouped_coupon['amount']      += (float) $coupon['value'];
					$grouped_coupon['description'] = $coupon['name'];
				}
			}
			if ( ! empty( $grouped_coupon['amount'] ) ) {
				$coupon = $grouped_coupon;
			}
		} elseif ( isset( $this->single_coupons[ $data ] ) ) {
			$coupon_id = $this->single_coupons[ $data ];

			if ( isset( $this->coupons[ $coupon_id ] ) ) {
				$coupon_data   = $this->coupons[ $coupon_id ];
				$coupon_type   = ( 'percentage' === $coupon_data['type'] ? 'percent' : 'fixed_cart' );
				$coupon_amount = (float) $coupon_data['value'];

				if ( ! empty( $coupon_amount ) ) {
					$coupon = array(
						'id'            => $coupon_id + 1,
						'discount_type' => $coupon_type,
						'amount'        => $coupon_amount,
						'description'   => $coupon_data['name'],
					);
				}
			}
		} elseif ( isset( $this->item_adjustment_coupons[ $data ] ) ) {
			$item_adjustment_coupon = array(
				'id'            => rand( 0, 1000 ),
				'discount_type' => 'fixed_cart',
				'amount'        => 0,
			);
			foreach ( $this->item_adjustment_coupons[ $data ] as $coupon_id ) {
				if ( ! empty( $this->coupons[ $coupon_id ] ) ) {
					$coupon = $this->coupons[ $coupon_id ];
					$item_adjustment_coupon['amount']      += (float) $coupon['value'];
					$item_adjustment_coupon['description'] = $coupon['name'];
				}
			}
			if ( ! empty( $item_adjustment_coupon['amount'] ) ) {
				$coupon = $item_adjustment_coupon;
			}
		}

		if ( $coupon ) {
			$coupon_obj->add_meta_data( "is_wdp", true, true );
		}

		return apply_filters( 'wdp_cart_totals_coupon_data', $coupon, $this, $coupon_obj );
	}

	/**
	 * Hide [Remove] link
	 *
	 * @param string    $coupon_html
	 * @param WC_Coupon $coupon
	 * @param string    $discount_amount_html
	 *
	 * @return string
	 */
	public function woocommerce_cart_totals_coupon_html( $coupon_html, $coupon, $discount_amount_html ) {
		if ( in_array( $coupon->get_code(), $this->applied_coupons ) ) {
			$coupon_html = $discount_amount_html;
		}

		return $coupon_html;
	}



	/** APPLY SHIPPING */

	/**
	 * @param WC_Shipping_Rate[] $rates
	 * @param array              $package
	 *
	 * @return WC_Shipping_Rate[]
	 */
	public function woocommerce_package_rates( $rates, $package ) {
		if ( is_null( $this->shipping_adjustments ) ) {
			return $rates;
		}

		$initial_cost_key = '_wdp_initial_cost';
		$initial_tax_key = '_wdp_initial_tax';
		$applied_rules_key = '_wdp_rules';
		$is_free_shipping_key = '_wdp_free_shipping';

		if ( $this->shipping_adjustments->is_free() ) {
			foreach ( $rates as &$rate ) {
				$meta_data = $rate->get_meta_data();
				if ( isset( $meta_data[$initial_cost_key] ) ) {
					$cost = $meta_data[$initial_cost_key];
				} else {
					$cost = $rate->get_cost();
				}
				$cost = (float) $cost;

				$rate->add_meta_data( $initial_cost_key, $cost );

				$rule_id = (int) $this->shipping_adjustments->get_rule_id_applied_free_shipping();
				$amount  = (float) $cost;
				$rules   = array( $rule_id => $amount );

				$rate->add_meta_data( $applied_rules_key, json_encode($rules) );

				$rate->add_meta_data( $is_free_shipping_key, true );
				$rate->set_cost( 0 );
				$rate->set_taxes( array() ); // no taxes
			}
		} else {
			foreach ( $rates as &$rate ) {
				$meta_data = $rate->get_meta_data();

				if ( isset( $meta_data[$initial_cost_key] ) ) {
					$cost = $meta_data[$initial_cost_key];
				} else {
					$cost = $rate->get_cost();
				}
				$cost = (float) $cost;

				$applied_shipping = array();

				foreach ( $this->shipping_adjustments->get_items() as $id => $item ) {
					$amount = 0;

					if ( 'amount' === $item['type'] ) {
						$amount = $item['value'];
					} elseif ( 'percentage' === $item['type'] ) {
						$amount = $cost * $item['value'] / 100;
					} elseif ( 'fixed' === $item['type'] ) {
						$amount = $cost - $item['value'];
					}

					if ( empty( $amount ) ) {
						continue;
					}

					$applied_shipping[ $id ] = (float) $amount;
				}

				if ( ! empty( $applied_shipping ) ) {
					$wdp_rules         = array();
					$shipping_adj_calc = $this->context->get_option( 'shipping_adjustments_calculation' );

					if ( 'max' === $shipping_adj_calc ) {
						$max_adj_id     = null;
						$max_adj_amount = null;

						foreach ( $applied_shipping as $id => $amount ) {
							if ( is_null( $max_adj_amount ) || $amount >= $max_adj_amount ) {
								$max_adj_id = $id;
								$max_adj_amount = $amount;
							}
						}

						$shipping = $this->shipping_adjustments->get_item( $max_adj_id );
						if ( ! is_null( $shipping ) ) {
							$rule_id   = (int) $shipping['rule_id'];
							$wdp_rules = array( $rule_id => $max_adj_amount );
						}
					} elseif ( 'min' === $shipping_adj_calc ) {
						$min_adj_id     = null;
						$min_adj_amount = null;

						foreach ( $applied_shipping as $id => $amount ) {
							if ( is_null( $min_adj_amount ) || $amount <= $min_adj_amount ) {
								$min_adj_id = $id;
								$min_adj_amount = $amount;
							}
						}

						$shipping = $this->shipping_adjustments->get_item( $min_adj_id );
						if ( ! is_null( $shipping ) ) {
							$rule_id   = (int) $shipping['rule_id'];
							$wdp_rules = array( $rule_id => $min_adj_amount );
						}
					} elseif ( 'sum' === $shipping_adj_calc ) {
						foreach ( $applied_shipping as $id => $amount ) {
							$shipping = $this->shipping_adjustments->get_item( $id );
							if ( is_null( $shipping ) ) {
								continue;
							}
							$rule_id = (int) $shipping['rule_id'];

							if ( isset( $wdp_rules[ $rule_id ] ) ) {
								$wdp_rules[ $rule_id ] += $amount;
							} else {
								$wdp_rules[ $rule_id ] = $amount;
							}
						}
					}

					$new_cost = $cost;
					foreach ( $wdp_rules as $rule_id => &$amount ) {
						$new_cost -= $amount;

						if ( $new_cost < 0 ) {
							$amount   = $amount + $new_cost;
							$new_cost = 0;
						}
					}

					$rate->set_cost( $new_cost );

					$rate->add_meta_data( $initial_cost_key, $cost );
					$rate->add_meta_data( $initial_tax_key, is_array( $rate->get_shipping_tax() ) ? array_sum( $rate->get_shipping_tax() ) : $rate->get_shipping_tax() );
					$rate->add_meta_data( $applied_rules_key, json_encode( $wdp_rules ) );
					$rate->add_meta_data( $is_free_shipping_key, false );

					// recalc taxes
					if ( $cost > 0 ) {
						$perc  = $new_cost / $cost;
						$taxes = $rate->get_taxes();
						foreach ( $taxes as $k => $v ) {
							$taxes[ $k ] = $v * $perc;
						}
						$rate->set_taxes( $taxes );
					} else {
						$rate->set_taxes( array() );
					}
				}
			}//each not free shipping!
		}

		return $rates;
	}


	/**
	 * @param string           $label
	 * @param WC_Shipping_Rate $method
	 *
	 * @return mixed
	 */
	public function woocommerce_cart_shipping_method_full_label( $label, $method ) {
		if ( false !== strpos( $label, 'wdp-amount' ) ) {
			return $label;
		}

		$initial_cost_key = '_wdp_initial_cost';
		$initial_tax_key = '_wdp_initial_tax';

		$meta_data = $method->get_meta_data();
		if ( ! isset( $meta_data[$initial_cost_key] ) ) {
			return $label;
		}

		$initial_cost = $meta_data[$initial_cost_key];
		$initial_tax  = 0.0;

		if ( isset( $meta_data[$initial_tax_key] ) ) {
			$initial_tax = $meta_data[$initial_tax_key];
		}

		if ( WC()->cart->display_prices_including_tax() ) {
			$initial_cost_html = '<del>' . wc_price( $initial_cost + $initial_tax ) . '</del>';
		} else {
			$initial_cost_html = '<del>' . wc_price( $initial_cost ) . '</del>';
		}
		$initial_cost_html = preg_replace( '/\samount/is', 'wdp-amount', $initial_cost_html );

//		if ( $method->get_cost() > 0 ) {
		$label = preg_replace( '/(<span[^>]*>)/is', $initial_cost_html . ' $1', $label, 1 );
//		} else {
//			$label .= ': ' . $initial_cost_html . ' ' . wc_price( 0 );
//		}

		return $label;
	}

	/**
	 * @param $wc_cart WC_Cart
	 */
	public function store_initial_totals( $wc_cart ) {
		$totals                       = $wc_cart->get_totals();
		$totals['wdp_initial_totals'] = $this->initial_totals;
		$wc_cart->set_totals( $totals );
	}

	public function get_context() {
		return $this->context;
	}

	public function get_external_coupons() {
		return $this->external_coupons;
	}
}