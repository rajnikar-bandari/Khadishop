<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Admin_Rules_Page extends WDP_Admin_Abstract_Page {
	public $title;
	public $priority = 10;
	protected $tab = 'rules';

	public function __construct() {
		$this->title = __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	protected function get_template_path() {
		return WC_ADP_PLUGIN_PATH . 'views/tabs/rules.php';
	}

	public function render() {
		$this->render_template( $this->get_template_path(), $this->get_template_variables() );
	}

	protected function get_template_variables() {
		$condition_registry   = WDP_Condition_Registry::get_instance();
		$conditions_templates = $condition_registry->get_templates_content();
		$conditions_titles    = $condition_registry->get_titles();

		$limit_registry   = WDP_Limit_Registry::get_instance();
		$limits_templates = $limit_registry->get_templates_content();
		$limits_titles    = $limit_registry->get_titles();

		$cart_registry  = WDP_Cart_Adj_Registry::get_instance();
		$cart_templates = $cart_registry->get_templates_content();
		$cart_titles    = $cart_registry->get_titles();

		$options = WDP_Helpers::get_settings();

		$pagination    = $this->make_pagination_html();
		$page          = 'wdp_settings';
		$tab           = $this->tab;
		$hide_inactive = $this->get_is_hide_inactive();

		return compact(
			'conditions_templates',
			'conditions_titles',
			'limits_templates',
			'limits_titles',
			'cart_templates',
			'cart_titles',
			'options',
			'pagination',
			'page',
			'hide_inactive',
			'tab'
		);
	}

	protected function get_is_hide_inactive() {
		return ! empty( $_GET['hide_inactive'] );
	}

	protected function make_get_rules_args() {
		$args           = array();

		if( ! empty( $_GET['product'] ) ) {
			$args['product'] = (int)$_GET['product'];

			return $args;
		}

		if ( ! empty( $_GET['rule_id'] ) ) {
			$args = array( 'id' => (int) $_GET['rule_id'] );

			return $args;
		}

		if ( $this->get_is_hide_inactive() ) {
			$args['active_only'] = true;
		}

		$page = $this->get_pagenum();
		if ( $page < 1 ) {
			return array();
		}

		$options        = WDP_Helpers::get_settings();
		$rules_per_page = $options['rules_per_page'];
		$args['limit']  = array( ( $page - 1 ) * $rules_per_page, $rules_per_page );

		$args['exclusive'] = 0;

		return $args;
	}

	public function get_tab_rules() {
		return WDP_Database::get_rules( $this->make_get_rules_args() );
	}

	protected function get_pagination_args() {
		$options        = WDP_Helpers::get_settings();
		$rules_per_page = $options['rules_per_page'];
		$rules_count = WDP_Database::get_rules_count( $this->make_get_rules_args() );
		$total_pages = (int)ceil( $rules_count / $rules_per_page );

		$pagination_args                = array();
		$pagination_args['total_items'] = $rules_count;
		$pagination_args['total_pages'] = $total_pages;

		return $pagination_args;
	}

}