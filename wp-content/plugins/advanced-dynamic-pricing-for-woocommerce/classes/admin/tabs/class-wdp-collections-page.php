<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Admin_Collections_Page extends WDP_Admin_Abstract_Page {
	public $priority = 120;
	protected $tab = 'product_collections';

	public function __construct() {
		$this->title = __( 'Product Collections', 'advanced-dynamic-pricing-for-woocommerce' ) . "&#x1f512;";
	}

	public function action() {
	}

	public function render() {
		$this->render_template(
			WC_ADP_PLUGIN_PATH . 'views/tabs/collections.php'
		);
	}
}