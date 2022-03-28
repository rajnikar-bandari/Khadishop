<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Items_Collection {
	/**
	 * @var WDP_Cart_Item[]
	 */
	private $items = array();

	/**
	 * @var int
	 */
	private $rule_id;

	public function __construct( $rule_id ) {
		$this->rule_id = $rule_id;
	}

	/**
	 * @param $item_to_add WDP_Cart_Item
	 *
	 * @return boolean
	 */
	public function add( $item_to_add ) {
		$added = false;
		foreach ( $this->items as $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			if ( $item->get_hash() === $item_to_add->get_hash() && ( $item->get_price() === $item_to_add->get_price() )) {
				$item->inc_qty( $item_to_add->get_qty() );
				$added = true;
				break;
			}
		}

		if ( ! $added ) {
			$this->items[] = $item_to_add;
		}

		$this->sort_items();

		return true;
	}

	private function sort_items() {
		return;
		usort( $this->items, function ( $item_a, $item_b ) {
			/**
			 * @var $item_a WDP_Cart_Item
			 * @var $item_b WDP_Cart_Item
			 */
			if ( ! $item_a->is_temporary() && $item_b->is_temporary() ) {
				return - 1;
			}

			if ( $item_a->is_temporary() && ! $item_b->is_temporary() ) {
				return 1;
			}

			return 0;
		} );

	}

	public function is_empty() {
		return empty( $this->items );
	}

	/**
	 * @return array WDP_Cart_Item[]
	 */
	public function get_items() {
		return $this->items;
	}

	public function get_hash() {
		return md5( json_encode( $this->items ) );
	}

	public function purge() {
		$this->items = array();
	}

	public function get_count() {
		return count( $this->items );
	}

	public function get_item_by_hash( $hash ) {
		foreach ( $this->items as $item ) {
			if ( $item->get_hash() === $hash ) {
				$new_item = clone $item;
				return $new_item;
			}
		}

		return null;
	}

	public function get_not_empty_item_with_reference_by_hash( $hash ) {
		foreach ( $this->items as $item ) {
			if ( $item->get_hash() === $hash && $item->get_qty() > 0 ) {
				return $item;
			}
		}

		return null;
	}

	public function remove_item_by_hash( $hash ) {
		foreach ( $this->items as $index => $item ) {
			if ( $item->get_hash() === $hash ) {
				unset( $this->items[ $index ] );
				$this->items = array_values( $this->items );
				return true;
			}
		}

		return false;
	}

	public function set_price_for_item( $hash, $price, $qty = null ) {
		foreach ( $this->items as &$item ) {
			if ( $item->get_hash() === $hash ) {
				if ( $qty && $item->get_qty() > $qty ) {
					$new_item        = clone $item;
					$new_item->set_qty($qty);
					$new_item->set_price( $this->rule_id, $price );
					$this->items[]   = $new_item;

					$item->dec_qty( $qty );
					$this->sort_items();
				} else {
					$item->set_price( $this->rule_id, $price );
				}

				$this->get_hash();

				return;
			}
		}
	}

	public function make_items_immutable() {
		foreach ( $this->items as &$item ) {
			$item->make_immutable();
		}
	}


}