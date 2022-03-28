<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Adj_Registry {
	private $items;

	private static $instance;

	protected function __construct() {
		$items = array(
			'discount__amount' => array(
				'class'    => 'WDP_Cart_Adjustment_Discount_Amount',
				'label'    => __( 'Fixed discount, once', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Discount', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/discount.php',
			),
			'discount_repeatable__amount' => array(
				'class'    => 'WDP_Cart_Adjustment_Discount_Amount_Repeatable',
				'label'    => __( 'Add fixed discount on each rule execution', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Discount', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/discount.php',
			),
			'discount_repeatable_sets_count__amount' => array(
				'class'    => 'WDP_Cart_Adjustment_Discount_Amount_Repeatable_Sets_Count',
				'label'    => __( 'Add fixed discount to each item line affected by rule', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Discount', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/discount.php',
			),
			'fee__amount'      => array(
				'class'    => 'WDP_Cart_Adjustment_Fee_Amount',
				'label'    => __( 'Fixed fee, once', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Fee', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/fee.php',
			),
			'fee_repeatable__amount'      => array(
				'class'    => 'WDP_Cart_Adjustment_Fee_Amount_Repeatable',
				'label'    => __( 'Add fixed fee on each rule execution', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Fee', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/fee.php',
			),
			'fee_repeatable_sets_count__amount'      => array(
				'class'    => 'WDP_Cart_Adjustment_Fee_Amount_Repeatable_Sets_Count',
				'label'    => __( 'Add fixed fee to each item line affected by rule', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Fee', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/fee.php',
			),
			'free__shipping'   => array(
				'class'    => 'WDP_Cart_Adjustment_Free_Shipping',
				'label'    => __( 'Free shipping', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Shipping', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/cart_adjustments/empty.php',
			),
		);

		$this->items = apply_filters( 'wdp_cart_adjustments', $items );
	}

	/**
	 * @return array
	 */
	public function get_adjustments_list() {
		return $this->items;
	}

	/**
	 * @return array
	 */
	public function get_titles() {
		$ret = array();

		foreach ( $this->items as $k => $item ) {
			$title = $item['label'];
			$group = $item['group'];

			$ret[ $group ][ $k ] = $title;
		}

		return $ret;
	}

	/**
	 * @return array
	 */
	public function get_templates_content() {
		$templates = array_map( function ( $item ) {
			ob_start();
			include $item['template'];
			$content = ob_get_clean();

			return $content;
		}, $this->items );

		return $templates;
	}

	/**
	 * @param array $data
	 *
	 * @return WDP_Cart_Adjustment
	 * @throws Exception
	 */
	public function create_adj( $data ) {
		$adj_type = $data['type'];
		if ( isset( $this->items[ $adj_type ] ) ) {
			$adj_class = $this->items[ $adj_type ]['class'];
			$adj       = new $adj_class( $data );

			return $adj;
		} else {
			throw new Exception( 'Wrong cart adjustment' );
		}
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}