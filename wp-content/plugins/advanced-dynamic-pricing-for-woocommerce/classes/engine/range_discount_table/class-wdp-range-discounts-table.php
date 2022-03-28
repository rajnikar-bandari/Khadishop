<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range_Discounts_Table {
	public static $text_domain = 'advanced-dynamic-pricing-for-woocommerce';

	const TYPE_BULK = 'bulk';
	const TYPE_TIER = 'tier';

	const CONTEXT_PRODUCT_PAGE = 'product';
	const CONTEXT_CATEGORY_PAGE = 'category';

	private $available_context = array(
		self::CONTEXT_PRODUCT_PAGE,
		self::CONTEXT_CATEGORY_PAGE,
	);

	const LAYOUT_SIMPLE = 'simple';
	const LAYOUT_VERBOSE = 'verbose';

	private $available_layout = array(
		self::LAYOUT_SIMPLE,
		self::LAYOUT_VERBOSE,
	);

	/**
	 * @var array
	 */
	private $theme_options;

	/**
	 * @var array
	 */
	private $options;

	/**
	 * @var string
	 */
	private $context;

	/**
	 * @var string
	 */
	private $layout;

	/**
	 * @var WDP_Price_Display|null
	 */
	private $price_display;

	private $tables = array(
		self::CONTEXT_PRODUCT_PAGE  => array(
			self::LAYOUT_VERBOSE => 'WDP_Range_Discounts_Verbose_Table_Product_Context',
			self::LAYOUT_SIMPLE  => 'WDP_Range_Discounts_Simple_Table_Product_Context',
		),
		self::CONTEXT_CATEGORY_PAGE => array(
			self::LAYOUT_VERBOSE => 'WDP_Range_Discounts_Verbose_Table_Category_Context',
		),
	);

	/**
	 * @var WDP_Range_Discounts_Table_Abstract
	 */
	private $instance;

	public function __construct( $price_display ) {
		$this->options       = WDP_Helpers::get_settings();
		if ( $price_display instanceof WDP_Price_Display && $price_display->is_enabled() ) {
			$this->price_display = $price_display;
		}

		add_action( "wp_ajax_nopriv_get_table_with_product_bulk_table", array(
			$this,
			"ajax_get_table_with_product_bulk_table"
		) );
		add_action( "wp_ajax_get_table_with_product_bulk_table", array(
			$this,
			"ajax_get_table_with_product_bulk_table"
		) );

		//SHORTCODES
		add_shortcode( 'adp_product_bulk_rules_table', function () {
			ob_start();
			$this->render_product_context_table();
			$content = ob_get_clean();

			return $content;
		} );

		add_shortcode( 'adp_category_bulk_rules_table', function () {
			ob_start();
			$this->render_category_context_table();
			$content = ob_get_clean();

			return $content;
		} );

	}

	public function get_option( $option, $default = false ) {
		return isset( $this->options[ $option ] ) ? $this->options[ $option ] : $default;
	}

	public function load( $object_id = null ) {
		$theme_options = $this->get_theme_options();
		$this->set_layout( $theme_options['table_layout'] );

		if ( ! isset( $this->tables[ $this->context ][ $this->layout ] ) ) {
			throw new Exception( sprintf( 'Table for context "%s" with layout "%s" does not exists', $this->context, $this->layout ) );
		}

		$object_id = $this->get_object_id_depends_on_context( $object_id );

		if ( ! $object_id ) {
			return false;
		}

		$classname      = $this->tables[ $this->context ][ $this->layout ];
		$this->instance = new $classname();
		$this->instance->load_theme_options( $this->get_theme_options() );
		if ( $this->is_price_calculation_available() ) {
			$this->instance->load_rule( $this->price_display, $object_id );
		}

		return $this->instance->is_ready();
	}

	public function ajax_get_table_with_product_bulk_table() {
		$product_id = ! empty( $_REQUEST['product_id'] ) ? $_REQUEST['product_id'] : false;
		if ( ! $product_id ) {
			wp_send_json_error();
		}

		$this->set_context( $this::CONTEXT_PRODUCT_PAGE );
		$content       = null;
		if ( $this->load( $product_id ) ) {
			$content = WDP_Frontend::wdp_get_template( $this->get_template_name(), $this->get_template_args() );
		}

		if ( $content ) {
			wp_send_json_success( $content );
		} else {
			wp_send_json_error( "" );
		}
	}

	public function render_product_context_table() {
		$this->set_context( $this::CONTEXT_PRODUCT_PAGE );

		global $product;
		/**
		 * @var $product WC_Product
		 */

		if ( empty( $product ) ) {
			return;
		}

		if ( $this->is_price_calculation_available() ) {
			$this->price_display->remove_price_hooks();
		}

		if ( $this->load( $product->get_id() ) ) {
			$available_products_ids = array_merge( array( $product->get_id() ), $product->get_children() );
			echo '<span class="wdp_bulk_table_content" data-available-ids="' . json_encode( $available_products_ids ) . '">';
			echo WDP_Frontend::wdp_get_template( $this->get_template_name(), $this->get_template_args() );
			echo '</span>';
		}

		if ( $this->is_price_calculation_available() ) {
			$this->price_display->restore_hooks();
		}
	}

	public function render_category_context_table() {
		if ( ! is_tax() ) {
			return;
		}

		$this->set_context( $this::CONTEXT_CATEGORY_PAGE );

		global $wp_query;
		if ( isset( $wp_query->queried_object->term_id ) ) {
			$term_id = $wp_query->queried_object->term_id;
		} else {
			return;
		}

		if ( $this->is_price_calculation_available() ) {
			$this->price_display->remove_price_hooks();
		}

		try {
			$is_show = $this->load( $term_id );
		} catch ( Exception $e ) {
			$is_show = false;
		}

		if ( $this->is_price_calculation_available() ) {
			$this->price_display->restore_hooks();
		}

		if ( $is_show ) {
			echo '<span class="wdp_bulk_table_content">';
			echo WDP_Frontend::wdp_get_template( $this->get_template_name(), $this->get_template_args() );
			echo '</span>';
		}
	}

	/**
	 * @param $context
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function set_context( $context ) {
		if ( in_array( $context, $this->available_context ) ) {
			$this->context = $context;
		} else {
			throw new Exception( sprintf( 'Incorrect context %s', $context ) );
		}

		return $this;
	}

	/**
	 * @param string $layout
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function set_layout( $layout ) {
		if ( in_array( $layout, $this->available_layout ) ) {
			$this->layout = $layout;
		} else {
			throw new Exception( sprintf( 'Incorrect layout %s', $layout ) );
		}

		return $this;
	}

	protected function get_theme_options() {
		if ( ! isset( $this->theme_options[ $this->context ] ) ) {
			throw new Exception( sprintf( 'Missing theme options for context %s', $this->context ) );
		}

		return $this->theme_options[ $this->context ];
	}

	/**
	 * @param $customizer WDP_Customizer
	 */
	public function set_theme_options( $customizer ) {
		// wait until filling get_theme_mod()
		add_action( 'wp_loaded', function () use ( $customizer ) {
			$theme_options = $customizer->get_theme_options();

			$context_options                                   = $theme_options[ self::CONTEXT_PRODUCT_PAGE ];
			$this->theme_options[ self::CONTEXT_PRODUCT_PAGE ] = array(
				'table_layout'                                     => $context_options['table']['table_layout'],
				'is_show_discounted_price'                         => $context_options['table']['show_discounted_price'],
				'is_show_discount_value'                           => $context_options['table']['show_discount_column'],
				'is_show_footer'                                   => $context_options['table']['show_footer'],
				'use_message_as_header'                            => $context_options['table_header']['use_message_as_title'],
				'table_header_for_bulk'                            => $context_options['table_header']['bulk_title'],
				'table_header_for_tier'                            => $context_options['table_header']['tier_title'],
				'header_text_discount_price'                       => $context_options['table_columns']['discounted_price_title'],
				'table_header_text_discount_value'                 => $context_options['table_columns']['discount_column_title'],
				'table_header_text_for_fixed_price_discount_value' => $context_options['table_columns']['discount_column_title_for_fixed_price'],
				'table_header_text_qty'                            => $context_options['table_columns']['qty_column_title'],
			);

			if ( $this->get_option( 'show_matched_bulk_table' ) ) {
				$product_bulk_table_actions = (array) apply_filters( 'wdp_product_bulk_table_action', array( $context_options['table']['product_bulk_table_action'] ) );
				foreach ( $product_bulk_table_actions as $action ) {
					add_action( $action, array( $this, 'render_product_context_table' ), 50, 2 );
				}
			}

			$context_options                                    = $theme_options[ self::CONTEXT_CATEGORY_PAGE ];
			$this->theme_options[ self::CONTEXT_CATEGORY_PAGE ] = array(
				'table_layout'                                     => $context_options['table']['table_layout'],
				'is_show_discount_value'                           => $context_options['table']['show_discount_column'],
				'is_show_footer'                                   => $context_options['table']['show_footer'],
				'use_message_as_header'                            => $context_options['table_header']['use_message_as_title'],
				'table_header_for_bulk'                            => $context_options['table_header']['bulk_title'],
				'table_header_for_tier'                            => $context_options['table_header']['tier_title'],
				'table_header_text_discount_value'                 => $context_options['table_columns']['discount_column_title'],
				'table_header_text_for_fixed_price_discount_value' => $context_options['table_columns']['discount_column_title_for_fixed_price'],
				'table_header_text_qty'                            => $context_options['table_columns']['qty_column_title'],
			);

			if ( $this->get_option( 'show_category_bulk_table' ) ) {
				$category_bulk_table_action = (array) apply_filters( 'wdp_category_bulk_table_action', array( $context_options['table']['category_bulk_table_action'] ) );
				foreach ( $category_bulk_table_action as $action ) {
					add_action( $action, array( $this, 'render_category_context_table' ), 50, 2 );
				}
			}
		}, 10 );
	}

	private function get_object_id_depends_on_context( $object_id = null ) {
		if ( $object_id ) {
			return $object_id;
		}

		if ( $this->context === $this::CONTEXT_PRODUCT_PAGE ) {
			global $product;
			/**
			 * @var $product WC_Product
			 */
			$object_id = $product->get_id();
		} elseif ( $this->context === $this::CONTEXT_CATEGORY_PAGE ) {
			if ( is_tax() ) {
				global $wp_query;
				if ( isset( $wp_query->queried_object->term_id ) ) {
					$object_id = $wp_query->queried_object->term_id;
				}
			}
		}

		return $object_id;
	}

	public function get_template_args() {
		$table_header = $this->instance->get_table_header();

		return array(
			'header_html'  => $this->instance->get_header_html(),
			'table_header' => $table_header,
			'rows'         => $this->instance->get_table_rows( $table_header ),
			'footer_html'  => $this->instance->get_footer_html(),
		);
	}

	public function get_template_name() {
		return "bulk-table.php";
	}

	protected function is_price_calculation_available() {
		return ! is_null( $this->price_display );
	}
}

