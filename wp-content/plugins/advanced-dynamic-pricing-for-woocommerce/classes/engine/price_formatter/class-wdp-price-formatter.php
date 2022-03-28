<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Price_Formatter {
	/**
	 * @var WDP_Context
	 */
	protected $context;

	/**
	 * WDP_Price_Formatter constructor.
	 *
	 * @param WDP_Context $context
	 */
	public function __construct($context) {
		$this->context = $context;
	}

	/**
	 * @param WC_Product|WDP_Product $product
	 *
	 * @return boolean
	 */
	public function are_modifications_needed( $product ) {
		return $this->context->is_allow_to_modify_price();
	}

	/**
	 * @param WDP_Product $wdp_product
	 *
	 * @return boolean
	 */
	public function is_replacement_with_bulk_price_needed( $wdp_product ) {
		if ( count( $wdp_product->get_children() ) > 0 ) {
			// check if we apply bulk price replacements to the product with children if not all children changed by bulk
			$apply_not_fully_affected = (boolean) apply_filters( 'wdp_is_apply_bulk_price_replacements_to_not_fully_affected_product', true );
			$apply_not_fully_affected = ! $apply_not_fully_affected ? $wdp_product->are_all_children_affected_by_bulk() : $apply_not_fully_affected;
		} else {
			$apply_not_fully_affected = true;
		}

		return $this->context->is_catalog() 
		       && $this->context->get_option( 'replace_price_with_min_bulk_price' ) 
		       && $wdp_product->is_affected_by_bulk() 
		       && $apply_not_fully_affected;
	}

	/**
	 * @param WDP_Product $wdp_product
	 *
	 * @return string
	 */
	public function format_bulk_price( $wdp_product ) {
		$product    = $wdp_product->get_wc_product();
		$price_html = htmlspecialchars_decode( $this->context->get_option( 'replace_price_with_min_bulk_price_template' ) );

		$min_bulk_price = $wdp_product->get_min_bulk_price();

		if ( count( $wdp_product->get_children() ) > 0 ) {
			$initial_price = $wdp_product->get_child_initial_price_for_min_bulk_price();
		} else {
			$initial_price = $wdp_product->get_price();
		}

		$replacements = array(
			'{{price}}'         => false !== $min_bulk_price ? wc_price( $min_bulk_price ) : "",
			'{{price_suffix}}'  => $product->get_price_suffix(),
			'{{price_striked}}' => false !== $initial_price ? '<del>' . wc_price( $initial_price ) . '</del>' : "",
			'{{initial_price}}' => false !== $initial_price ? wc_price( $initial_price ) : "",
			'{{Nth_item}}'  => $wdp_product->get_index_number() ? $wdp_product->get_index_number() : "",
		);

		foreach ( $replacements as $search => $replace ) {
			$price_html = str_replace( $search, $replace, $price_html );
		}

		return $price_html;
	}

	/**
	 * @param $price_html
	 * @param WDP_Product $wdp_product
	 *
	 * @return string
	 */
	public function format_price( $price_html, $wdp_product ) {
		$result = $price_html;

		if ( $this->context->get_option( "enable_product_html_template", false ) && floatval( $wdp_product->get_index_number() ) > 1 ) {
			$result = $this->context->get_option( "price_html_template", "{{price_html}}" );

			$replacements = array(
				'{{price_html}}'          => $price_html,
				'{{Nth_item}}'        => $this->add_suffix_of( $wdp_product->get_index_number() ),
				'{{qty_already_in_cart}}' => $wdp_product->get_qty_already_in_cart(),
			);

			foreach ( $replacements as $search => $replace ) {
				$result = str_replace( $search, $replace, $result );
			}
		}

		return $result;
	}

	/**
	 * @param string      $price_html
	 * @param WDP_product $wdp_product
	 *
	 * @return string
	 */
	public function maybe_add_subscription_tail( $price_html, $wdp_product ) {
		if ( class_exists( "WC_Product_Subscription" ) && ( $wdp_product->get_wc_product() instanceof WC_Product_Subscription ) ) {
			$subs_price_html = WC_Subscriptions_Product::get_price_string( $wdp_product->get_wc_product(), array( 'price' => $price_html ) );
			$price_html      = ! is_null( $subs_price_html ) ? $subs_price_html : $price_html;
		}

		return $price_html;
	}

	/**
	 * @param WDP_Product $product
	 * @param float|int $qty
	 *
	 * @return string|false
	 */
	public function format_wdp_product_price( $product, $qty = 1 ) {
		if ( ! ( $product instanceof WDP_Product ) ) {
			return false;
		}

		if ( ! $product->are_rules_applied() || ! $this->are_modifications_needed( $product ) ) {
			return $product->get_wc_price_html( $qty );
		}

		if ( $product->is_variable() || $product->is_grouped() ) {
			// has children

			if ( ! $product->is_range_defined() ) {
				return false;
			}

			if ( $product->is_range_correct() ) {
				if (  apply_filters( 'wdp_allow_to_strike_out_variable_range', false ) && $this->context->is_allow_to_strikethrough_price() ) {
					$price_html = wc_format_sale_price(
						wc_format_price_range( $product->get_child_initial_price_for_min_price() * $qty, $product->get_child_initial_price_for_max_price() * $qty ),
						wc_format_price_range( $product->get_min_price() * $qty, $product->get_max_price() * $qty ) ) . $product->get_price_suffix();
				} else {
					$price_html = wc_format_price_range( $product->get_min_price() * $qty, $product->get_max_price() * $qty ) . $product->get_price_suffix();
				}
			} elseif ( $product->is_all_children_have_equal_price() ) {
				if ( $product->is_on_wdp_sale() ) {

					if ( $this->context->is_allow_to_strikethrough_price() ) {
						$price_html = wc_format_sale_price( wc_price( $product->get_price() * $qty ), wc_price( $product->get_min_price() * $qty ) ) . $product->get_price_suffix();
					} else {
						$price_html = wc_price( $product->get_min_price() * $qty ) . $product->get_price_suffix();
					}
				} else {
					$price_html = wc_price( $product->get_new_price() * $qty ) . $product->get_price_suffix();
				}
			} else {
				// min price greater than max price
				return false;
			}
			$price_html = apply_filters( 'wdp_woocommerce_variable_price_html', $price_html, $product, $product->get_wc_product(), $qty );
		} else {
			if ( $product->is_on_wdp_sale() && $this->context->is_allow_to_strikethrough_price() ) {
				$price_html = wc_format_sale_price( wc_price( $product->get_price() * $qty ), wc_price( $product->get_new_price() * $qty ) ) . $product->get_price_suffix();
			} else {
				$price_html = wc_price( $product->get_new_price() * $qty ) . $product->get_price_suffix();
			}
			$price_html = apply_filters( 'wdp_woocommerce_discounted_price_html', $price_html, $product, $qty );
		}

		return $price_html;
	}

	/**
	 * Add ordinal indicator
	 *
	 * @param $value integer|float
	 *
	 * @return string
	 */
	protected function add_suffix_of( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $value;
		}

		$value = (string) $value;

		$mod10  = $value % 10;
		$mod100 = $value % 100;

		if ( $mod10 === 1 && $mod100 !== 11 ) {
			return $value . "st";
		}

		if ( $mod10 === 2 && $mod100 !== 12 ) {
			return $value . "nd";
		}

		if ( $mod10 === 3 && $mod100 !== 13 ) {
			return $value . "rd";
		}

		return $value . "th";
	}

}