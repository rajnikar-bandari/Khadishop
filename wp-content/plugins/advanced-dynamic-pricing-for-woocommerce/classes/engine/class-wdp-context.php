<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Context {
	const IS_SHOP_LOOP = 'is_shop_loop';
	const IS_ADMIN = 'is_admin';
	const IS_AJAX = 'is_ajax';
	const IS_PRODUCT_PAGE = 'is_product_page';

	protected $settings = array();
	protected $props = array();

	public function __construct() {
		$this->settings = WDP_Helpers::get_settings();
	}

	public static function make_context() {
		$context = new self();
		$context->set_props( array(
			self::IS_SHOP_LOOP    => ! empty( $GLOBALS['woocommerce_loop']['name'] ),
			self::IS_ADMIN        => is_admin(),
			self::IS_AJAX         => wp_doing_ajax(),
			self::IS_PRODUCT_PAGE => is_product(),
		) );

		return $context;
	}

	public function set_props( $new_props ) {
		$editable_props = array(
			self::IS_SHOP_LOOP,
			self::IS_ADMIN,
			self::IS_AJAX,
			self::IS_PRODUCT_PAGE,
		);

		foreach ( $new_props as $key => $value ) {
			if ( in_array( $key, $editable_props ) ) {
				$this->props[ $key ] = $value;
			}
		}

		return $this;
	}

	public function get_option( $key, $default = false ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	public function get_prop( $key, $default = false ) {
		return isset( $this->props[ $key ] ) ? $this->props[ $key ] : $default;
	}

	public function is_product_page() {
		return $this->get_prop( self::IS_PRODUCT_PAGE );
	}

	public function is_ajax() {
		return $this->get_prop( self::IS_AJAX );
	}

	public function is_admin() {
		return $this->get_prop( self::IS_ADMIN );
	}

	public function is_allow_to_modify_price() {
		return $this->is_product_page() ? ! $this->get_option( 'do_not_modify_price_at_product_page', false ) : true;
	}

	public function is_allow_to_strikethrough_price() {
		return $this->is_product_page() ? $this->get_option( 'show_striked_prices_product_page', true ) : true;
	}

	public function is_catalog() {
		return ! $this->is_product_page() || $this->get_prop( self::IS_SHOP_LOOP );
	}
}
