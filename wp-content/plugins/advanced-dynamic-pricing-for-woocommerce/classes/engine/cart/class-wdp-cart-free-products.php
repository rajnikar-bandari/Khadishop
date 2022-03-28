<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Free_Products {
	/**
	 * @var array rule_id => product_id => [qty, price]
	 */
	private $products = array();

	/**
	 * @var array product_id => wc_product
	 */
	private $cached_products = array();

	/**
	 * @var array rule_id => coupon(fee?) name
	 */
	private $rule_adj_replacements = array();

	/**
	 * Needs for detecting difference between free products in initial and processed cart
	 *
	 * @var array
	 */
	private $previously_gifted_products = array();

	public function __construct() {}

	/**
	 * @param int $rule_id
	 * @param WC_Product $product
	 * @param int $qty
	 */
	public function add( $rule_id, $product, $qty = 1 ) {
		$this->add_as_gifted( $this->products, $rule_id, $product, $qty );
	}

	public function get_qty( $product_id = null ) {
		if ( ! $product_id ) {
			return 0;
		}

		$qty = 0;
		foreach ( $this->products as $rule_id => $rule_free_products ) {
			foreach ( $rule_free_products as $rule_product_id => $product_qty ) {
				if ( $product_id == $rule_product_id ) {
					$qty += $product_qty;
				}
			}
		}

		return $qty;
	}

	public function get_items() {
		return $this->products;
	}

	public function get_product( $product_id ) {
		return isset( $this->cached_products[ $product_id ] ) ? $this->cached_products[ $product_id ] : false;
	}

	/**
	 * @param $rule_id int
	 * @param $adjustment_name string
	 */
	public function exclude_rule_adjustments( $rule_id, $adjustment_name ) {
		$rule_id                                 = (int) $rule_id;

		// if excluded in rule, global options not able to rewrite
		if ( ! empty( $adjustment_name ) && ! isset( $this->rule_adj_replacements[ $rule_id ] ) ) {
			$this->rule_adj_replacements[ $rule_id ] = $adjustment_name;
		}
	}

	/**
	 * @param $rule_id int
	 *
	 * @return bool|string
	 */
	public function get_rule_adjustment_replacement( $rule_id ) {
		$rule_id = (int) $rule_id;

		return isset( $this->rule_adj_replacements[ $rule_id ] ) ? $this->rule_adj_replacements[ $rule_id ] : false;
	}

	private function add_as_gifted( &$destination, $rule_id, $product, $qty ) {
		if ( ! isset( $destination[ $rule_id ] ) ) {
			$destination[ $rule_id ] = array();
		}

		if ( ! isset( $destination[ $rule_id ][ $product->get_id() ] ) ) {
			$destination[ $rule_id ][ $product->get_id() ] = $qty;
			$this->cached_products[ $product->get_id() ] = $product;
		} else {
			$destination[ $rule_id ][ $product->get_id() ] += $qty;
		}
	}

	public function add_previously_gifted_cart_item( $rule_id, $product, $qty ) {
		$this->add_as_gifted( $this->previously_gifted_products, $rule_id, $product, $qty );
	}

	private function collect_free_products( $source ) {
		$result = array();

		foreach ( $source as $rule_free_products ) {
			foreach ( $rule_free_products as $product_id => $qty ) {
				if ( isset( $result[ $product_id ] ) ) {
					$result[ $product_id ] += $qty;
				} else {
					$result[ $product_id ] = $qty;
				}
			}
		}

		return $result;
	}

	public function get_recently_gifted_products() {
		$previously_gifted = $this->collect_free_products( $this->previously_gifted_products );
		$currently_gifted  = $this->collect_free_products( $this->products );

		$result = array();
		foreach ( $currently_gifted as $product_id => $qty ) {
			if ( ! isset( $result[ $product_id ] ) ) {
				$result[ $product_id ] = 0;
			}

			if ( isset( $previously_gifted[ $product_id ] ) ) {
				if ( $previously_gifted[ $product_id ] < $qty ) {
					$result[ $product_id ] += $qty - $previously_gifted[ $product_id ];
				}
			} else {
				$result[ $product_id ] += $qty;
			}
		}

		return array_filter( $result );
	}
}