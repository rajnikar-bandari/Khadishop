<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Admin_Help_Page extends WDP_Admin_Abstract_Page {
	public $priority = 80;
	protected $tab = 'help';

	public function __construct() {
		$this->title = __( 'Help', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public function action() {
	}

	public function render() {
		$this->render_template(
			WC_ADP_PLUGIN_PATH . 'views/tabs/help.php'
		);
	}
}