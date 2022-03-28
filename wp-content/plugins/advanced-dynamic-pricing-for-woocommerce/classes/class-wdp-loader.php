<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Loader {
	const db_version = "wdp_db_version";

	public static function install() {
		WDP_Database::create_database();
		do_action('wdp_install');
	}

	public static function deactivate() {
		delete_option( WDP_Settings::$activation_notice_option );
	}

	/**
	 * Method required for tests
	 */
	public static function uninstall() {
		$file = WC_ADP_PLUGIN_PATH . 'uninstall.php';
		if ( file_exists( $file ) ) {
			include_once $file;
		}
	}

	public function __construct() {
		include_once WC_ADP_PLUGIN_PATH . 'classes/admin/class-wdp-settings.php';

		$extension_file = WC_ADP_PLUGIN_PATH . 'pro_version/loader.php';
		if ( file_exists( $extension_file ) ) {
			include_once $extension_file;
		}

		//should wait a bit
		add_action( 'init', array( $this, 'init_plugin' ) );
	}

	public function init_plugin() {
		if ( ! self::check_requirements() ) {
			return;
		}

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/contracts/contract-*.php' ) as $filename ) {
			include_once $filename;
		}

		load_plugin_textdomain( 'advanced-dynamic-pricing-for-woocommerce', FALSE, basename( dirname( dirname( __FILE__ ) ) ) . '/languages/' );

		self::check_db_version();
		
		include_once WC_ADP_PLUGIN_PATH . 'classes/common/class-wdp-helpers.php';
		include_once WC_ADP_PLUGIN_PATH . 'classes/admin/class-wdp-importer.php';

		include_once WC_ADP_PLUGIN_PATH . 'classes/class-wdp-frontend.php';
		include_once WC_ADP_PLUGIN_PATH . 'classes/class-wdp-functions.php';

		// core wc helpers, load as soon as possible
		$this->load_wc_translators();

		$this->load_customizer();
		$this->load_profiler();

		$discount_message = self::get_discount_message_class(); // load before customizer !
		$profiler         = new WDP_Calculation_Profiler();
		$customizer       = self::get_customizer_class();
		$customizer->install();

		if ( is_admin() ) {
			new WDP_Settings();// it will load core on demand
			$profiler->admin_install();
		}

		$options = WDP_Helpers::get_settings();
		if ( ! is_admin() || $options['load_in_backend'] || WDP_Frontend::is_ajax_processing() ) {
			$non_admin_side = new WDP_Frontend();
			$non_admin_side->install_bulk_range_table( $customizer ); // it will load core on demand
			$non_admin_side->install_profiler( $profiler );
			$discount_message->set_theme_options( $customizer );
		}

		$discount_message->set_theme_options_email( $customizer );
	}

	private function load_range_discount_table_display() {
		include_once WC_ADP_PLUGIN_PATH . 'classes/engine/range_discount_table/abstracts/abstract-wdp-range-discounts-table.php';
		include_once WC_ADP_PLUGIN_PATH . 'classes/engine/range_discount_table/abstracts/abstract-wdp-range-discounts-table-product-context.php';
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/range_discount_table/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/range_discount_table/tables/class-*.php' ) as $filename ) {
			include_once $filename;
		}
	}

	private function load_wc_translators() {
		include_once WC_ADP_PLUGIN_PATH . 'classes/engine/class-wdp-wc-cart-translator.php';
		include_once WC_ADP_PLUGIN_PATH . 'classes/engine/class-wdp-wc-order-translator.php';
	}

	private function load_discount_message_display() {
		include_once WC_ADP_PLUGIN_PATH . 'classes/engine/class-wdp-discount-message.php';
	}

	private function load_customizer() {
		$this->load_range_discount_table_display();
		$this->load_discount_message_display();

		include_once WC_ADP_PLUGIN_PATH . 'classes/admin/class-wdp-customizer.php';
	}

	private function load_profiler() {
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/reporter/class-*.php' ) as $filename ) {
			include_once $filename;
		}

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/reporter/collectors/class-*.php' ) as $filename ) {
			include_once $filename;
		}
	}

	public static function check_db_version() {
		$version = get_option( self::db_version, "" );
		if( $version != WC_ADP_VERSION ) {
			//upgrade db
			WDP_Database::create_database();
			do_action('wdp_upgrade_db');
			update_option( self::db_version, WC_ADP_VERSION, false );
		}
	}

	public static function check_requirements() {
		$state = true;
		if ( version_compare( phpversion(), WC_ADP_MIN_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( '<strong>Advanced Dynamic Pricing for WooCommerce</strong> requires PHP version %s or later.',
						'advanced-dynamic-pricing-for-woocommerce' ), WC_ADP_MIN_PHP_VERSION ) . '</p></div>';
			} );
			$state = false;
		} elseif ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible"><p>' . __( '<strong>Advanced Dynamic Pricing for WooCommerce</strong> requires active WooCommerce!',
						'advanced-dynamic-pricing-for-woocommerce' ) . '</p></div>';
			} );
			$state = false;
		} elseif ( version_compare( WC_VERSION, WC_ADP_MIN_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( '<strong>Advanced Dynamic Pricing for WooCommerce</strong> requires WooCommerce version %s or later.',
						'advanced-dynamic-pricing-for-woocommerce' ), WC_ADP_MIN_WC_VERSION ) . '</p></div>';
			} );
			$state = false;
		}

		return $state;
	}

	public static function load_core() {
		do_action( 'wdp_load_core' );

		//Traits
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/traits/trait-*.php' ) as $filename ) {
			include_once $filename;
		}

		// Engine
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/cart/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/price_formatter/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/engine/product/class-*.php' ) as $filename ) {
			include_once $filename;
		}

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/shortcode/class-*.php' ) as $filename ) {
			include_once $filename;
		}

		//Rules
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/class-wdp-rule-*.php' ) as $filename ) {
			include_once $filename;
		}

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/calculators/class-wdp-rule-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/abstract/abstract-class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/product/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/product_categories/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/product_selected_categories/class-*.php' ) as $filename )
			include_once $filename;

			foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/selected_products/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/sets/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/total_qty_in_cart/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/variation/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/cart_position/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/discount_range/qty_based_calculators/all_matched_products/class-*.php' ) as $filename )
			include_once $filename;

		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/rules/exceptions/class-*.php' ) as $filename )
			include_once $filename;

		do_action( 'wdp_include_core_classes' );

		//Limits
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/limits/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		do_action( 'wdp_include_limits' );

		//Conditions
		include_once WC_ADP_PLUGIN_PATH . 'classes/conditions/abstract-wdp-condition.php';
		include_once WC_ADP_PLUGIN_PATH . 'classes/conditions/abstract-wdp-condition-cart-items.php';
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/conditions/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		do_action( 'wdp_include_conditions' );

		//Cart adjustments
		include_once WC_ADP_PLUGIN_PATH . 'classes/cart_adjustments/abstract-wdp-cart-adj.php';
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/cart_adjustments/class-*.php' ) as $filename ) {
			include_once $filename;
		}
		do_action( 'wdp_include_cart_adjustments' );

		//Registries
		foreach ( glob( WC_ADP_PLUGIN_PATH . 'classes/registries/class-*.php' ) as $filename ) {
			include_once $filename;
		}

		include_once WC_ADP_PLUGIN_PATH . 'classes/class-wdp-standalone-cart.php';
	}

	public static function is_request_to_rest_api() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$wordpress = ( false !== strpos( $request_uri, $rest_prefix ) );
		return $wordpress;
	}

	public static function is_pro_version() {
		return defined('WC_ADP_PRO_VERSION_PATH');
	}

	public static function get_product_filtering_class() {
		return apply_filters('wdp_get_product_filtering_class', new WDP_Product_Filtering());
	}
	
	public static function get_discount_message_class() {
		return apply_filters('wdp_get_discount_message_class', new WDP_Discount_Message());
	}

	public static function get_customizer_class() {
		return apply_filters( 'wdp_get_customizer_class', new WDP_Customizer() );
	}

	public static function get_rule_sql_generator_class() {
		return apply_filters('wdp_get_rule_sql_generator_class', new WDP_Rule_SQL_Generator());
	}

	public static function get_context_class_name() {
		return apply_filters( 'wdp_get_context_class_name', "WDP_Context" );
	}

	public static function create_formatter( $context = null ) {
		if ( empty( $context ) ) {
			$context_classname = WDP_Loader::get_context_class_name();
			$context = $context_classname::make_context();
		}
		
		$classname = apply_filters( 'wdp_get_price_formatter_class_name', "WDP_Price_Formatter" );

		return new $classname( $context );
	}

	/**
	 * @param WDP_Rules_Collection $rule_collection
	 *
	 * @return mixed|void
	 */
	public static function get_cart_calculator_class( $rule_collection ) {
		return apply_filters( 'wdp_cart_calculator_class', new WDP_Cart_Calculator( $rule_collection ), $rule_collection );
	}
}