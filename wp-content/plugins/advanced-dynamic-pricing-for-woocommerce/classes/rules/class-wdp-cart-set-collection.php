<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Set_Collection {
	/**
	 * @var WDP_Cart_Set[]
	 */
	private $sets = array();

	public function __clone() {
		$new_sets = array();
		foreach ( $this->sets as $set ) {
			$new_sets[] = clone $set;
		}

		$this->sets = $new_sets;
	}

	public function __construct() {}

	/**
	 * @param $set_to_add WDP_Cart_Set
	 *
	 * @return boolean
	 */
	public function add( $set_to_add ) {
		$added = false;
		foreach ( $this->sets as &$set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			if ( $set->get_hash() === $set_to_add->get_hash() ) {
				$set->inc_qty( $set_to_add->get_qty() );
				$added = true;
				break;
			}
		}

		if ( ! $added ) {
			$this->sets[] = $set_to_add;
		}

		/**
		 * Do use sorting here!
		 * It breaks positional discounts like 'Tier discount'.
		 */

		return true;
	}

	public function is_empty() {
		return empty( $this->sets );
	}

	/**
	 * @return WDP_Cart_Set[]
	 */
	public function get_sets() {
		return $this->sets;
	}

	public function get_hash() {
		$sets = array();
		foreach ( $this->sets as $set ) {
			$sets[] = clone $set;
		}

		usort( $sets, function ( $set_a, $set_b ) {
			/**
			 * @var $set_a WDP_Cart_Set
			 * @var $set_b WDP_Cart_Set
			 */
			return strnatcmp( $set_a->get_hash(), $set_b->get_hash() );
		} );

		$sets_hashes = array_map( function ( $set ) {
			/**
			 * @var $set WDP_Cart_Set
			 */
			return $set->get_hash();
		}, $sets );
		$encoded     = json_encode( $sets_hashes );
		$hash        = md5( $encoded );

		return $hash;
	}

	public function purge() {
		$this->sets = array();
	}

	public function get_total_sets_qty() {
		$count = 0;

		foreach ( $this->sets as $set ) {
			$count += $set->get_qty();
		}

		return $count;
	}

	public function get_set_by_hash( $hash ) {
		foreach ( $this->sets as $set ) {
			if ( $set->get_hash() === $hash ) {
				$new_set = clone $set;
				return $new_set;
			}
		}

		return null;
	}


}