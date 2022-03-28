<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Report_Output {
	/**
	 * @var string
	 */
	private $import_key = '';

	public function __construct( $import_key ) {
		$this->import_key = $import_key;

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_action( 'wp_head', function () {
			echo "<div style='display: none;'>";
			$this->add_templates();
			echo "</div>";
		} );

		//add_action( 'admin_bar_menu', array( $this, 'add_toolbar_items' ), 100 );
		add_action( 'wp_head', array( $this, 'add_iframe' ) );
	}

	/**
	 * @param WP_Admin_Bar $admin_bar
	 */
	public function add_toolbar_items( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id'    => 'wdp-report',
			'title' => 'WDP report',
			'href'  => '#',
			'meta'  => array(
				'title' => __( 'WDP report', 'advanced-dynamic-pricing-for-woocommerce' ),
				'class' => 'wdp-report-visibility-control'
			),
		) );
	}

	public function output() {
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/main.php';
	}

	public function register_assets() {
		wp_enqueue_script( 'wdp_user_report', WC_ADP_PLUGIN_URL . '/assets/js/user-report.js', array( 'jquery' ), WC_ADP_VERSION );
		$user_report_data = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i' => array(
				'cart'               => __( 'Cart', 'advanced-dynamic-pricing-for-woocommerce' ),
				'products'           => __( 'Products', 'advanced-dynamic-pricing-for-woocommerce' ),
				'rules'              => __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
				'items'              => __( 'Items', 'advanced-dynamic-pricing-for-woocommerce' ),
				'coupons'            => __( 'Coupons', 'advanced-dynamic-pricing-for-woocommerce' ),
				'fees'               => __( 'Fees', 'advanced-dynamic-pricing-for-woocommerce' ),
				'replaced_by_coupon' => __( 'Replaced by coupon', 'advanced-dynamic-pricing-for-woocommerce' ),
				'replaced_by_fee'    => __( 'Replaced by fee', 'advanced-dynamic-pricing-for-woocommerce' ),
				'rule_id'            => __( 'Rule ID', 'advanced-dynamic-pricing-for-woocommerce' ),
				'rule'               => __( 'Rule', 'advanced-dynamic-pricing-for-woocommerce' ),
				'shipping'           => __( 'Shipping', 'advanced-dynamic-pricing-for-woocommerce' ),
				'get_system_report'  => __( 'Get system report', 'advanced-dynamic-pricing-for-woocommerce' ),
			),
			'classes' => array(
				'replaced_by_coupon' => 'replaced-by-coupon',
				'replaced_by_fee' => 'replaced-by-fee',
			),
			'import_key' => $this->import_key,
		);

		wp_localize_script( 'wdp_user_report', 'user_report_data', $user_report_data );

		wp_enqueue_style( 'wdp_user_report', WC_ADP_PLUGIN_URL . '/assets/css/user-report.css', array(), WC_ADP_VERSION );
	}

	public function add_templates() {
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/tabs/base.php';
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/tabs/cart.php';
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/tabs/products.php';
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/tabs/rules.php';
		include_once WC_ADP_PLUGIN_PATH . 'templates/reporter/tabs/reports.php';
	}

	public function add_iframe() {
		echo "<iframe id='wdp_export_new_window_frame' width=0 height=0 style='display:none'></iframe>";
	}

}