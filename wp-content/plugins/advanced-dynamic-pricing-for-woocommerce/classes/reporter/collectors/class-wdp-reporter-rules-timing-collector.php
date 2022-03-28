<?php

class WDP_Reporter_Rules_Timing_Collector {
	/**
	 * @var WDP_Cart_Calculator_Listener
	 */
	protected $listener;

	/**
	 * @var array WDP_Rule[]
	 */
	protected $active_rules;

	/**
	 * WDP_Reporter_Rules_Timing_Collector constructor.
	 *
	 * @param $listener WDP_Cart_Calculator_Listener
	 * @param $active_rules array WDP_Rule[]
	 */
	public function __construct( $listener, $active_rules ) {
		$this->listener = $listener;
		$this->active_rules = $active_rules;
	}

	public function collect() {
		return array(
			'cart'     => $this->collect_cart_timing(),
			'products' => $this->collect_products_timing()
		);
	}

	private function sort_rules_timing( $timing_a, $timing_b ) {
		if ( $timing_a === $timing_b ) {
			return 0;
		}

		return $timing_a > $timing_b ? - 1 : 1;
	}

	/**
	 * @return array
	 */
	public function collect_cart_timing() {
		$wc_cart_rule_report = $this->listener->get_cart_report();

		$rules_timing_cart = isset( $wc_cart_rule_report['timing'] ) ? $wc_cart_rule_report['timing'] : array();
		uasort( $rules_timing_cart, array( $this, 'sort_rules_timing' ) );

		return array_map( function ( $timing, $rule_id ) {
			return array( 'id' => $rule_id, 'timing' => $timing, 'title' => $this->get_rule_title( $rule_id ) );
		}, $rules_timing_cart, array_keys( $rules_timing_cart ) );
	}

	/**
	 * @return array
	 */
	public function collect_products_timing() {
		$products_report_data = $this->listener->get_products_report();

		$rules_timing_product = array();
		foreach ( $products_report_data as $data ) {
			if ( ! empty( $data['timing'] ) ) {
				foreach ( $data['timing'] as $rule_id => $timing ) {
					if ( isset( $rules_timing_product[ $rule_id ] ) ) {
						$rules_timing_product[ $rule_id ] += $timing;
					} else {
						$rules_timing_product[ $rule_id ] = $timing;
					}
				}
			}
		}
		uasort( $rules_timing_product, array( $this, 'sort_rules_timing' ) );

		return array_map( function ( $timing, $rule_id ) {
			return array( 'id' => $rule_id, 'timing' => $timing, 'title' => $this->get_rule_title( $rule_id ) );
		}, $rules_timing_product, array_keys( $rules_timing_product ) );
	}

	protected function get_rule_title( $id ) {
		foreach ( $this->active_rules as $rule ) {
			/**
			 * @var WDP_Rule $rule
			 */
			if ( $id === $rule->get_id() ) {
				return $rule->get_ad_title();
			}
		}

		return '';
	}
}