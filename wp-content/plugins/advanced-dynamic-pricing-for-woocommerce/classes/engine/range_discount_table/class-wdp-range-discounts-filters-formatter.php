<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range_Discounts_Filters_Formatter {
	protected $text_domain = 'advanced-dynamic-pricing-for-woocommerce';

	public function format_filter( $rule_filter ) {
		$filter_type    = $rule_filter['type'];
		$filter_method  = $rule_filter['method'];

		$filter_qty_label = $this->get_filter_qty_label($rule_filter);

		if ( 'any' === $filter_method ) {
			return sprintf( '<a href="%s">%s</a>', get_permalink( wc_get_page_id( 'shop' ) ), sprintf( __( '%s of any product(s)', $this->text_domain ), $filter_qty_label ) );
		}

		$templates = array_merge( array(
			'products' => array(
				'in_list'     => __( '%s product(s) from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from list: %s', $this->text_domain ),
			),

			'product_sku' => array(
				'in_list'     => __( '%s product(s) with SKUs from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with SKUs not from list: %s', $this->text_domain ),
			),

			'product_categories' => array(
				'in_list'     => __( '%s product(s) from categories: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from categories: %s', $this->text_domain ),
			),

			'product_category_slug' => array(
				'in_list'     => __( '%s product(s) from categories with slug: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) not from categories with slug: %s', $this->text_domain ),
			),

			'product_tags' => array(
				'in_list'     => __( '%s product(s) with tags from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with tags not from list: %s', $this->text_domain ),
			),

			'product_attributes' => array(
				'in_list'     => __( '%s product(s) with attributes from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with attributes not from list: %s', $this->text_domain ),
			),

			'product_custom_fields' => array(
				'in_list'     => __( '%s product(s) with custom fields: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) without custom fields: %s', $this->text_domain ),
			),
		), array_combine( array_keys( WDP_Helpers::get_custom_product_taxonomies() ), array_map( function ( $tmp_filter_type ) {
			return array(
				'in_list'     => __( '%s product(s) with ' . $tmp_filter_type . ' from list: %s', $this->text_domain ),
				'not_in_list' => __( '%s product(s) with ' . $tmp_filter_type . ' not from list: %s', $this->text_domain ),
			);
		}, array_keys( WDP_Helpers::get_custom_product_taxonomies() ) ) ) );

		if ( ! isset( $templates[ $filter_type ][ $filter_method ] ) ) {
			return "";
		}

		$humanized_values = array();
		foreach ( $rule_filter['values'] as $id ) {
			$name = WDP_Helpers::get_title_by_type( $id, $filter_type );
			$link = WDP_Helpers::get_permalink_by_type( $id, $filter_type );

			if ( ! empty( $link ) ) {
				$humanized_value = "<a href='{$link}'>{$name}</a>";
			} else {
				$humanized_value = "'{$name}'";
			}

			$humanized_values[ $id ] = $humanized_value;
		}

		return sprintf( $templates[ $filter_type ][ $filter_method ], $filter_qty_label, implode( ", ", $humanized_values ) );
	}

	/**
	 * @param WDP_Rule $rule
	 *
	 * @return array
	 */
	public function format_rule($rule) {
		$humanized_filters = array();

		foreach ( $rule->get_rule_filters() as $rule_filter ) {
			$humanized_filters[] = $this->format_filter($rule_filter);
		}

		return $humanized_filters;
	}

	protected function get_filter_qty_label( $rule_filter ) {
		$filter_qty     = $rule_filter['qty'];
		$filter_qty_end = $rule_filter['qty_end'];

		return $filter_qty_end !== $filter_qty ? sprintf( "%d-%d", $filter_qty, $filter_qty_end ) : (string) $filter_qty;
	}



}
