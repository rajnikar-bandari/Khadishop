<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Condition_Cart_Items_Abstract extends WDP_Condition_Abstract {
	protected $used_items;
	protected $has_product_dependency = true;
	protected $filter_type = '';

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {
		$this->used_items = array();

		$options                      = $this->data['options'];
		$comparison_qty               = (float) $options[0];
		$comparison_qty_finish_exists = isset( $options[3] ) ? "" !== $options[3] : false;
		$comparison_qty_finish        = $comparison_qty_finish_exists ? (float) $options[3] : 0.0;
		$comparison_method            = isset( $options[1] ) ? $options[1] : 'in_list';
		$comparison_list              = isset( $options[2] ) ? (array) $options[2] : array();

		if ( empty( $comparison_qty ) ) {
			return true;
		}

		$invert_filtering = false;
		if ( $comparison_method === "not_containing" ) {
			$invert_filtering = true;
			$comparison_method = 'in_list';
		}

		$qty   = 0;
		$product_filtering = WDP_Loader::get_product_filtering_class();
		$product_filtering->prepare( $this->filter_type, $comparison_list, $comparison_method );

		foreach ( $cart->get_items() as $item_key => $item ) {
			/**
			 * @var $item WDP_Cart_Item
			 */
			$item_data = $cart->get_item_data_by_hash( $item->get_hash() );
			$checked   = $product_filtering->check_product_suitability( $item_data['data'] );

			if ( $checked ) {
				$qty                += $item->get_qty();
//				$this->used_items[] = $item_key;
			}
		}

		$result = $comparison_qty_finish_exists ? ( $comparison_qty <= $qty ) && ( $qty <= $comparison_qty_finish ) : $comparison_qty <= $qty;
		return $invert_filtering ? ! $result : $result;
	}

	public function get_involved_cart_items() {
		return $this->used_items;
	}

	public function match( $cart ) {
		return $this->check($cart);
	}

	public function get_product_dependency() {
		return array(
			'qty'    => $this->data['options'][0],
			'type'   => $this->filter_type,
			'method' => $this->data['options'][1],
			'value'  => (array) $this->data['options'][2],
		);
	}

	public function translate( $language_code ) {
		parent::translate( $language_code );

		$options           = $this->data['options'];
		$comparison_list   = (array) $options[2];

		$comparison_list = ( new WDP_Rule_Filter_Translator() )->translate_by_type( $this->filter_type, $comparison_list, $language_code );

		$this->data['options'][2] = $comparison_list;
	}
}