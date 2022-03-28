<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Object_Cache {
	const WC_PROD_GROUP = 'wc_product';
	const VARIAT_PROD_DATA_GROUP = 'variation_product_data';
	const WDP_PROD_GROUP = 'wdp_product';

	protected static $instance = false;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private $cache = array();
	private $loaded_child_meta_posts = array();

	private function add( $key_or_path, $group, $value ) {
		if ( is_array( $key_or_path ) ) {
			$path = $key_or_path;
		} else {
			$path = array( $key_or_path );
		}

		$path = array_reverse( $path );

		if ( ! isset( $this->cache[ $group ] ) ) {
			$this->cache[ $group ] = array();
		}

		$current = &$this->cache[ $group ];

		while ( count( $path ) ) {
			$key = array_pop( $path );
			if ( ! isset( $current[ $key ] ) ) {
				$current[ $key ] = array();
			}

			$current = &$current[ $key ];
		}

		$current = $value;
	}

	private function get( $key_or_path, $group ) {
		if ( is_array( $key_or_path ) ) {
			$path = $key_or_path;
		} else {
			$path = array( $key_or_path );
		}

		if ( ! count( $path ) ) {
			return false;
		}

		if ( isset($this->cache[$group]) ) {
			$current = &$this->cache[$group];
		} else {
			return false;
		}

		$path = array_reverse( $path );

		while ( count( $path ) ) {
			$key = array_pop( $path );
			if ( isset( $current[ $key ] ) ) {
				$current = &$current[ $key ];
			} else {
				return false;
			}
		}

		return $current;
	}

	/**
	 * @param $the_product int|WC_Product
	 *
	 * @return false|WC_Product
	 */
	public function get_wc_product( $the_product ) {
		if ( $the_product instanceof WC_Product ) {
			if ( $product = $this->get( $the_product->get_id(), $this::WC_PROD_GROUP ) ) {
				return $product;
			}

			$product = clone $the_product;

			try {
				$reflection = new ReflectionClass( $product );
				$property   = $reflection->getProperty( 'changes' );
				$property->setAccessible( true );
				$property->setValue( $product, array() );
			} catch ( Exception $e ) {

			}


			$this->add( $product->get_id(), $this::WC_PROD_GROUP, $product );

			return $product;
		} elseif ( ! is_numeric( $the_product ) ) {
			return false;
		}

		$product_id = $the_product;
		$product    = $this->get( $product_id, $this::WC_PROD_GROUP );

		if ( ! $product ) {
			$product = wc_get_product( $product_id );
			$this->add( $product_id, $this::WC_PROD_GROUP, $product );
		}

		return $product;
	}

	/**
	 * @param $product_id int
	 *
	 * @return stdClass
	 */
	public function get_variation_product_data( $product_id ) {
		$product_meta    = $this->get( $product_id, $this::VARIAT_PROD_DATA_GROUP );

		if ( false === $product_meta ) {
			$product_meta = get_post_meta( $product_id );
			array_walk($product_meta, function(&$item) {
				if ( is_array($item) ) {
					$item = reset($item);
				}

				$item = maybe_unserialize( $item );

				return $item;
			});

			$this->add( $product_id, $this::VARIAT_PROD_DATA_GROUP, $product_meta );
		}

		return $product_meta;
	}


	/**
	 * @param $product_id int
	 *
	 * @return array
	 */
	public function get_variation_product_meta( $product_id ) {
		$product_data = $this->get_variation_product_data( $product_id );

		return $product_data ? $product_data->meta : array();
	}

	public function load_variation_post_meta( $parent_id ) {
		if ( in_array($parent_id, $this->loaded_child_meta_posts) ) {
			return;
		}

		$product_meta = WDP_Database::get_only_required_child_post_meta_data($parent_id);
		foreach ( $product_meta as $product_id => $item ) {
			$this->add( $product_id, $this::VARIAT_PROD_DATA_GROUP, $item );
		}

		$this->loaded_child_meta_posts[] = $parent_id;
	}

	public function get_wdp_product( $price_display, $the_product, $qty = 1 ) {
		if ( $the_product instanceof WDP_Product ) {
			$product = clone $the_product;
			$this->add( array( $price_display->hash(), $product->get_id(), $qty ), $this::WDP_PROD_GROUP, $product );

			return $the_product;
		} elseif ( ! is_numeric( $the_product ) ) {
			return false;
		}

		$product_id = $the_product;
		$product = $this->get( array( $price_display->hash(), $product_id, $qty ), $this::WDP_PROD_GROUP );

		return $product instanceof WDP_Product ? clone $product : false;
	}

	public function get_collection( $id ) {

	}
}
