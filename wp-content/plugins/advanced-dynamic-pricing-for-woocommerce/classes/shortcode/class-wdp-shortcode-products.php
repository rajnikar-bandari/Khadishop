<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Shortcode_Products extends WC_Shortcode_Products {

    const NAME = '';
	const STORAGE_KEY = '';
	
	public static function register() {
		add_shortcode(static::NAME, array(static::class, 'create'));
    }

    public static function create($atts) {

		// apply legacy [sale_products] attributes
		$atts = array_merge( array(
			'limit'        => '12',
			'columns'      => '4',
			'orderby'      => 'title',
			'order'        => 'ASC',
			'category'     => '',
			'cat_operator' => 'IN',
		), (array) $atts );

		$shortcode = new static( $atts, static::NAME );

		return $shortcode->get_content();
    }

    public static function get_cached_products_ids() {

        // Load from cache.
		$product_ids = get_transient( static::STORAGE_KEY );

        // Valid cache found.
        if ( false !== $product_ids ) {
            return $product_ids;
        }

        return static::update_cached_products_ids();
    }

    public static function update_cached_products_ids() {

		$product_ids = static::get_products_ids();

		set_transient( static::STORAGE_KEY, $product_ids, DAY_IN_SECONDS * 30 );

		return $product_ids;
    }

}