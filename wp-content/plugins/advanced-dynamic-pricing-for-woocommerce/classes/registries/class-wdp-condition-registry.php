<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Registry {
	private $items;

	private static $instance;

	protected function __construct() {
		$this->init();
	}

	public function re_init() {
		$this->items = array();
		$this->init();
	}

	protected function init() {
		$this->init_product_conditions();
		$this->init_cart_conditions();
		$this->init_customer_conditions();
		$this->init_customer_history_conditions();
		$this->init_datetime_conditions();
		$this->init_shipping_conditions();

		$this->items = apply_filters( 'wdp_conditions', $this->items );
	}

	protected function init_product_conditions() {
		$this->items['products'] = array(
			'class'    => 'WDP_Condition_Products',
			'label'    => __( 'Products', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/products.php',
		);

		$this->items['product_categories'] = array(
			'class'    => 'WDP_Condition_Product_Categories',
			'label'    => __( 'Product categories', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-categories.php',
		);

		$this->items['product_category_slug'] = array(
			'class'    => 'WDP_Condition_Product_Category_Slug',
			'label'    => __( 'Product category slug', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-category-slug.php',
		);

		$this->items['product_tags'] = array(
			'class'    => 'WDP_Condition_Product_Tags',
			'label'    => __( 'Product tags', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-tags.php',
		);

		foreach ( WDP_Helpers::get_custom_product_taxonomies(true) as $taxonomy ) {
			$this->items[ $taxonomy->name ] = array(
				'class'         => 'WDP_Condition_Product_Taxonomy',
				'label'         => $taxonomy->labels->menu_name, // localized label
				'group'         => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template'      => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-taxonomy.php',
				'template_data' => array(
					'taxonomy' => $taxonomy,
				),
			);
		}

		$this->items['product_attributes'] = array(
			'class'    => 'WDP_Condition_Product_Attributes',
			'label'    => __( 'Product attributes', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-attributes.php',
		);

		$this->items['product_sku'] = array(
			'class'    => 'WDP_Condition_Product_SKU',
			'label'    => __( 'Product SKU', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-sku.php',
		);
		
		$this->items['product_custom_fields'] = array(
			'class'    => 'WDP_Condition_Product_Custom_Fields',
			'label'    => __( 'Product custom fields', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart items', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/products/product-custom-fields.php',
		);
	}

	protected function init_cart_conditions() {
		$this->items['cart_subtotal'] = array(
			'class'    => 'WDP_Condition_Cart_Subtotal',
			'label'    => __( 'Subtotal', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Cart', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/cart/subtotal.php',
		);
	}

	protected function init_customer_conditions() {
		$this->items['customer_logged'] = array(
			'class'    => 'WDP_Condition_Customer_Logged',
			'label'    => __( 'Is logged in', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Customer', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/customer/is-logged-in.php',
		);
		$this->items['customer_role'] = array(
			'class'    => 'WDP_Condition_Customer_Role',
			'label'    => __( 'Role', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Customer', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/customer/role.php',
		);
		$this->items['customer_order_count'] = array(
			'class'    => 'WDP_Condition_Customer_Order_Count',
			'label'    => __( 'Order count', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Customer', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/customer/order-count.php',
		);
		if ( get_option( 'woocommerce_subscriptions_is_active', false ) ) {
			$this->items['subscriptions'] = array(
				'class'    => 'WDP_Condition_Customer_Subscriptions',
				'label'    => __( 'Active subscriptions', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Customer', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/customer/subscriptions.php',
			);
		}
	}

	protected function init_customer_history_conditions() {
	}

	protected function init_datetime_conditions() {
		$this->items['date'] = array(
			'class'    => 'WDP_Condition_Date',
			'label'    => __( 'Date', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Date & time', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/datetime/date.php',
		);

		$this->items['time'] = array(
			'class'    => 'WDP_Condition_Time',
			'label'    => __( 'Time', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Date & time', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/datetime/time.php',
		);

		$this->items['days_of_week'] = array(
			'class'    => 'WDP_Condition_Days_Of_Week',
			'label'    => __( 'Days of week', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Date & time', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/datetime/days-of-week.php',
		);
	}

	protected function init_shipping_conditions() {
		$this->items['shipping_country'] = array(
			'class'    => 'WDP_Condition_Shipping_Country',
			'label'    => __( 'Country', 'advanced-dynamic-pricing-for-woocommerce' ),
			'group'    => __( 'Shipping', 'advanced-dynamic-pricing-for-woocommerce' ),
			'template' => WC_ADP_PLUGIN_PATH . 'views/conditions/shipping/country.php',
		);
	}

	/**
	 * @return array
	 */
	public function get_conditions_list() {
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
			if ( ! empty( $item['template_data'] ) ) {
				extract( $item['template_data'] );
			}
			include $item['template'];
			$content = ob_get_clean();

			return $content;
		}, $this->items );

		return $templates;
	}

	/**
	 * @param array $data
	 *
	 * @return WDP_Condition
	 * @throws Exception
	 */
	public function create_condition( $data ) {
		$type = $data['type'];
		if ( isset( $this->items[ $type ] ) ) {
			$class     = $this->items[ $type ]['class'];
			$condition = new $class( $data );

			return $condition;
		} else {
			throw new Exception( 'Wrong condition' );
		}
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}