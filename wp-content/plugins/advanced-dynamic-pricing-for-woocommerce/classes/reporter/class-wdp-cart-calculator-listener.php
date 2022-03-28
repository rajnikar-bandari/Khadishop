<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Calculator_Listener implements WDP_Cart_Calculator_Subscriber {
	private $tmp_rules_exec_time = array();
	private $tmp_data = array();

	private $cart_report = array();
	private $products_report = array();

	private $verbose = false;

	public function __construct( $args = array() ) {
		$args = array_merge( array(
			'verbose' => false,
		), $args );

		$this->verbose = (boolean) $args['verbose'];
	}

	/**
	 * @param WDP_Rule $rule
	 */
	public function rule_calculated_product( $rule ) {
		$this->rule_calculated( $rule );
	}

	/**
	 * @param WDP_Rule $rule
	 */
	public function rule_calculated_cart( $rule ) {
		$this->rule_calculated( $rule );
	}

	/**
	 * @param WDP_Rule $rule
	 */
	private function store_rule_execution_time( $rule ) {
		$this->tmp_rules_exec_time[ $rule->get_id() ] = $rule->get_last_exec_time();
	}

	/**
	 * @param WDP_Rule $rule
	 */
	public function rule_calculated( $rule ) {
		$this->store_rule_execution_time( $rule );
		$rule_status = $rule->get_last_apply_status();

		if ( isset( $this->tmp_data['statuses'][ $rule_status ] ) ) {
			$this->tmp_data['statuses'][ $rule_status ][] = $rule->get_id();
		}

		$this->tmp_data['statistic'][ $rule_status ] += 1;
	}

	public function start_product_calculation() {
		if ( $this->verbose ) {
			$statuses = array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES    => array(),
				WDP_Rule::$STATUS_OK_ITEM_CHANGES       => array(),
				WDP_Rule::$STATUS_OK_WITH_TMP_CHANGES   => array(),
				WDP_Rule::$STATUS_LIMITS_NOT_PASSED     => array(),
				WDP_Rule::$STATUS_CONDITIONS_NOT_PASSED => array(),
				WDP_Rule::$STATUS_FILTERS_NOT_PASSED    => array(),
			);
		} else {
			$statuses = array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES  => array(),
				WDP_Rule::$STATUS_OK_ITEM_CHANGES     => array(),
				WDP_Rule::$STATUS_OK_WITH_TMP_CHANGES => array(),
			);
		}


		$this->tmp_data = array(
			'statuses'  => $statuses,
			'statistic' => array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES    => 0,
				WDP_Rule::$STATUS_OK_ITEM_CHANGES       => 0,
				WDP_Rule::$STATUS_OK_WITH_TMP_CHANGES   => 0,
				WDP_Rule::$STATUS_LIMITS_NOT_PASSED     => 0,
				WDP_Rule::$STATUS_CONDITIONS_NOT_PASSED => 0,
				WDP_Rule::$STATUS_FILTERS_NOT_PASSED    => 0,
			),
		);
	}

	public function start_cart_calculation() {
		if ( $this->verbose ) {
			$statuses = array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES    => array(),
				WDP_Rule::$STATUS_OK_ITEM_CHANGES       => array(),
				WDP_Rule::$STATUS_LIMITS_NOT_PASSED     => array(),
				WDP_Rule::$STATUS_CONDITIONS_NOT_PASSED => array(),
				WDP_Rule::$STATUS_FILTERS_NOT_PASSED    => array(),
			);
		} else {
			$statuses = array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES => array(),
				WDP_Rule::$STATUS_OK_ITEM_CHANGES    => array(),
			);
		}


		$this->tmp_data = array(
			'statuses'  => $statuses,
			'statistic' => array(
				WDP_Rule::$STATUS_OK_NO_ITEM_CHANGES    => 0,
				WDP_Rule::$STATUS_OK_ITEM_CHANGES       => 0,
				WDP_Rule::$STATUS_LIMITS_NOT_PASSED     => 0,
				WDP_Rule::$STATUS_CONDITIONS_NOT_PASSED => 0,
				WDP_Rule::$STATUS_FILTERS_NOT_PASSED    => 0,
			),
		);
	}

	/**
	 * @param WC_Product    $product
	 * @param integer       $qty
	 * @param WDP_Cart_Item $item
	 */
	public function product_calculated( $product, $qty, $item ) {
		$rule_statuses   = $this->tmp_data['statuses'];
		$rule_stats      = $this->tmp_data['statistic'];
		$rules_exec_time = $this->tmp_rules_exec_time;

		$history = $item->get_history();

		foreach ( $rule_statuses[ WDP_Rule::$STATUS_OK_ITEM_CHANGES ] as $idx => $rule_id ) {
			if ( isset( $history[ $rule_id ] ) ) {
				unset( $rule_statuses[ WDP_Rule::$STATUS_OK_ITEM_CHANGES ][ $idx ] );
				$rule_stats[ WDP_Rule::$STATUS_OK_ITEM_CHANGES ] -= 1;

				$rule_statuses[ WDP_Rule::$STATUS_OK_WITH_TMP_CHANGES ][] = $rule_id;
				$rule_stats[ WDP_Rule::$STATUS_OK_WITH_TMP_CHANGES ]      += 1;
			}
		}

		$this->products_report[] = array(
			'product'  => $product,
			'item'     => $item,
			'qty'      => $qty,
			'statuses' => $rule_statuses,
			'stats'    => $rule_stats,
			'timing'   => $rules_exec_time
		);

		$this->clear_tmp_variables();
	}

	/**
	 * @param WDP_Cart $cart
	 */
	public function cart_calculated( $cart ) {
		$rule_statuses   = $this->tmp_data['statuses'];
		$rule_stats      = $this->tmp_data['statistic'];
		$rules_exec_time = $this->tmp_rules_exec_time;

		$this->cart_report = array(
			'cart'     => $cart,
			'statuses' => $rule_statuses,
			'stats'    => $rule_stats,
			'timing'   => $rules_exec_time,
		);

		$this->clear_tmp_variables();
	}

	private function clear_tmp_variables() {
		$this->tmp_data            = array();
		$this->tmp_rules_exec_time = array();
	}

	public function get_products_report() {
		return $this->products_report;
	}

	public function get_cart_report() {
		return $this->cart_report;
	}
}
