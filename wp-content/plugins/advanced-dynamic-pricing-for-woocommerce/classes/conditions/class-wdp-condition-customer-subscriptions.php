<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Customer_Subscriptions extends WDP_Condition_Abstract {

	/**
	 * @param WDP_Cart $cart
	 *
	 * @return bool
	 */
	public function check( $cart ) {

		$options           = $this->data['options'];
		$comparison_method = $options[0];
		$comparison_list   = empty($options[1]) ? array() : $options[1];

		if  ( ! function_exists('wcs_get_users_subscriptions') OR !is_user_logged_in() ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions();

		$product_ids = array();
		foreach ( $subscriptions as $subscription_key => $subscription ) {
			if ( $subscription->has_status( 'active' ) ) {
				foreach ( $subscription->get_items() as $item_key => $item ){
					/**
					 * @var $item WC_Order_Item_Product
					 */
					$product_id = $item->get_product_id();
					$product = WDP_Object_Cache::get_instance()->get_wc_product( $product_id );
					if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {
						$product_ids[] = $product_id;
					}
				}
			}
		}
		$product_ids = array_unique($product_ids);

		return $this->compare_lists( $product_ids, $comparison_list, $comparison_method );
	}
}