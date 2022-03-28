<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Admin_Options_Page extends WDP_Admin_Abstract_Page {
	public $priority = 50;
	protected $tab = 'options';

	public function __construct() {
		$this->title = __( 'Settings', 'advanced-dynamic-pricing-for-woocommerce' );
	}

	public function action() {
		if ( isset( $_POST['save-options'] ) ) {
			WDP_Helpers::set_settings( filter_input_array( INPUT_POST, WDP_Helpers::get_validate_filters() ) );

			wp_redirect( $_SERVER['HTTP_REFERER'] );
		}
	}

	protected function get_sections() {
		$sections = array(
			"rules" => array(
				'title'     => __( "Rules", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"rules_per_page",
					"rule_max_exec_time",
					"limit_results_in_autocomplete",
					"allow_to_exclude_products",
					"support_shortcode_products_on_sale",
					"support_shortcode_products_bogo",
				),
			),
			"category_page" => array(
				'title'     => __( "Category page", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
				),
			),
			"product_page" => array(
				'title'     => __( "Product page", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"do_not_modify_price_at_product_page",
					"show_onsale_badge",
					"use_first_range_as_min_qty",
				),
			),
			"price_templates" => array(
				'title'     => __( "Product price", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					1  => "replace_price_with_min_bulk_price",
					10 => "product_price_html",
				),
			),
			"bulk_table"      => array(
				'title'     => __( "Bulk table", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"show_category_bulk_table",
					"show_matched_bulk_table",
					"discount_table_ignores_conditions",
					"bulk_table_calculation_mode",
				),
			),
			"cart" => array(
				'title'     => __( "Cart", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"show_striked_prices",
					"show_cross_out_subtotal_in_cart_totals",
					"amount_saved_url_to_customizer",
					"message_after_add_free_product",
				),
			),
			"coupons"     => array(
				'title'     => __( "Coupons", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"disable_external_coupons",
					"disable_external_coupons_only_if_items_updated",
					"hide_coupon_word_in_totals",
				),
			),
			"calculation" => array(
				'title'     => __( "Calculation", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"apply_discount_for_onsale_products",
					"initial_price_context",
					"combine_discounts",
					"default_discount_name",
					"combine_fees",
					"default_fee_name",
					"default_fee_tax_class",
					"override_cents",
					"is_calculate_based_on_wc_precision",
				),
			),
			"system"      => array(
				'title'     => __( "System", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"suppress_other_pricing_plugins",
					"load_in_backend",
					"allow_to_edit_prices_in_po",
					"update_prices_while_doing_cron",
					"update_prices_while_doing_rest_api",
					"uninstall_remove_data",
				),
			),
			"debug"      => array(
				'title'     => __( "Debug", 'advanced-dynamic-pricing-for-woocommerce' ),
				'templates' => array(
					"show_debug_bar",
				),
			),
		);

		return $sections;
	}

	public function render() {
		$options = WDP_Helpers::get_settings();

		$data = compact( 'options' );

		list( $product, $category ) = $this->calculate_customizer_urls();
		$data['product_bulk_table_customizer_url']  = $product;
		$data['category_bulk_table_customizer_url'] = $category;
		$data['amount_saved_customer_url']          = $this->make_customer_url( 'discount_message' );

		$data['sections'] = $this->get_sections();

		$this->render_template( WC_ADP_PLUGIN_PATH . 'views/tabs/options.php', $data );
	}

	protected function render_options_template( $template, $data ) {
		$this->render_template( WC_ADP_PLUGIN_PATH . "views/tabs/options/{$template}.php", $data );
	}

	/**
	 * Making urls for simple redirect to customizer page with expanded panel and opened url with bulk table
	 *
	 */
	private function calculate_customizer_urls() {
		$active_rules = WDP_Rules_Registry::get_instance()->get_active_rules()->with_bulk()->to_array();
		$category_id  = 0;
		$product_id   = 0;

		foreach ( $active_rules as $index => $rule ) {
			$dependencies = $rule->get_rule_filters();

			foreach ( $dependencies as $dependency ) {
				if ( 'product_categories' === $dependency['type'] && ! $category_id ) {
					$category_id = is_array( $dependency['values'] ) ? reset( $dependency['values'] ) : 0;
				}

				if ( 'products' === $dependency['type'] && ! $product_id ) {
					$product_id = is_array( $dependency['values'] ) ? reset( $dependency['values'] ) : 0;
				}

				if ( 'product_sku' === $dependency['type'] && ! $product_id ) {
					$sku = is_array( $dependency['values'] ) ? reset( $dependency['values'] ) : 0;
					$product_id = wc_get_product_id_by_sku( $sku );
				}

				if ( $category_id && $product_id ) {
					break;
				}
			}

			if ( $category_id && $product_id ) {
				break;
			}
		}

		return array( $this->make_url( $product_id, 'product' ), $this->make_url( $category_id, 'category' ) );
	}

	private function make_url( $id, $type ) {
		$customizer_url = $this->make_customer_url( $type );


		if ( ! in_array( $type, array( 'product', 'category' ) ) ) {
			return $customizer_url;
		}

		$query_args = array(
			'autofocus[panel]' => "wdp_{$type}_bulk_table",
		);

		if ( $id ) {
			if ( 'product' == $type ) {
				$query_args['url'] = get_permalink( (int) $id );
			} elseif ( 'category' == $type ) {
				$query_args['url'] = get_term_link( (int) $id, 'product_cat' );
			}
		}

		return add_query_arg( $query_args, $customizer_url );
	}

	private function make_customer_url( $type ) {
		/**
		 * @see WDP_Customizer::init()
		 */
		if ( $type === 'product' || $type === 'category' ) {
			$panel = "wdp_{$type}_bulk_table";
		} elseif ( $type === 'discount_message' ) {
			$panel = "wdp_discount_message";
		} else {
			return '';
		}

		$query_args = array(
			'return'           => admin_url( 'themes.php' ),
			'autofocus[panel]' => $panel,
		);

		return add_query_arg( $query_args, admin_url( 'customize.php' ) );
	}
}