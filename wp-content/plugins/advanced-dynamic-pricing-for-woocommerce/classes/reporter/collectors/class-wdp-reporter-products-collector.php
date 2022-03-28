<?php

class WDP_Reporter_Products_Collector {
	/**
	 * @var WDP_Cart_Calculator_Listener
	 */
	protected $listener;

	/**
	 * @var WDP_Price_Display
	 */
	protected $price_display;

	/**
	 * WDP_Reporter_Rules_Timing_Collector constructor.
	 *
	 * @param $price_display WDP_Price_Display
	 * @param $listener WDP_Cart_Calculator_Listener
	 */
	public function __construct( $price_display, $listener ) {
		$this->price_display = $price_display;
		$this->listener = $listener;
	}

	/**
	 *
	 * @return array
	 */
	public function collect() {
		if ( ! $this->price_display ) {
			return array();
		}

		$reports = $this->listener->get_products_report();

		$result = array();

		foreach ( $reports as $report ) {
			$qty = $report['qty'];

			$product = $report['product'];
			/**
			 * @var WC_Product $product
			 */

			$item = $report['item'];
			/**
			 * @var WDP_Cart_Item $item
			 */

			$is_on_sale               = array();
			$is_on_sale['full']       = $product->is_on_sale();
			$is_on_sale['no_filters'] = $product->is_on_sale( 'nofilters' );

			$regular_price               = array();
			$regular_price['full']       = $product->get_regular_price();
			$regular_price['no_filters'] = $product->get_regular_price( 'nofilters' );

			$sale_price               = array();
			$sale_price['full']       = $product->get_sale_price();
			$sale_price['no_filters'] = $product->get_sale_price( 'nofilters' );

			$this->price_display->remove_price_hooks();
//				$result['price_html_no_wdp'] =  $wdp_product->get_wc_product()->get_price_html();
			$is_on_sale['no_wdp']    = $product->is_on_sale();
			$regular_price['no_wdp'] = $product->get_regular_price();
			$sale_price['no_wdp']    = $product->get_sale_price();
			$this->price_display->restore_hooks();

			$reflection = new ReflectionClass( $product );
			$property   = $reflection->getProperty( 'changes' );
			$property->setAccessible( true );
			$changes = $property->getValue( $product );

			/**
			 * Save the ordination of applied discounts
			 */
			$history = array();
			foreach ( $item->get_history() as $rule_id => $amount ) {
				$history[] = array(
					'rule_id' => $rule_id,
					'amount'  => (float) array_sum( $amount ),
				);
			}

			$result[] = array(
				'data'    => array(
					'id'               => $product->get_id(),
					'parent_id'        => $product->get_parent_id(),
					'name'             => $product->get_name(),
//					'edit_page_url'    => $this->get_edit_post_link( $product ),
					'page_url'         => $product->get_permalink(),
					'original_price'   => $item->get_initial_price(),
					'discounted_price' => $item->get_calculated_price(),
					'qty'              => $item->get_qty(), // not $qty

					'is_on_sale'    => $is_on_sale,
					'sale_price'    => $sale_price,
					'regular_price' => $regular_price,
					'changes'       => $changes,
				),
//				'statuses' => $report['statuses'],
//				'stats'    => $report['stats'],
//				'timing'   => $report['timing'],
				'history' => $history,
			);
		}

		return $result;
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return string
	 */
	private function get_edit_post_link( $product ) {
		$edit_url = get_edit_post_link( $product->get_id() );
		$edit_url = ! $edit_url ? get_edit_post_link( $product->get_parent_id() ) : $edit_url;

		return ! is_null( $edit_url ) ? $edit_url : '';
	}
}