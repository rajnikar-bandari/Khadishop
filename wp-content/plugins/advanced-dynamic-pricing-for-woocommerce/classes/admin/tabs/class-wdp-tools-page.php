<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Admin_Tools_Page extends WDP_Admin_Abstract_Page {
	public $priority = 60;
	protected $tab = 'tools';
	protected $groups;
	protected $import_data_types;

	const IMPORT_TYPE_OPTIONS = 'options';
	const IMPORT_TYPE_RULES   = 'rules';

	public function __construct() {

	    $this->title = __( 'Tools', 'advanced-dynamic-pricing-for-woocommerce' );

	    add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

	    $this->import_data_types = array(
		self::IMPORT_TYPE_OPTIONS   => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
		self::IMPORT_TYPE_RULES	    => __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
	    );
	}

	public function action() {

		if ( isset( $_POST['wdp-import'] ) && ! empty( $_POST['wdp-import-data'] ) && ! empty( $_POST['wdp-import-type'] ) ) {

			$data = json_decode( str_replace( '\\', '', wp_unslash( $_POST['wdp-import-data'] ) ), true );

			$import_data_type = $_POST['wdp-import-type'];

			$this->action_groups( $data, $import_data_type );

			wp_redirect( $_SERVER['HTTP_REFERER'] );
		}
	}

	public function render() {

		$this->prepare_export_groups();

		$groups = $this->groups;

		$import_data_types = $this->import_data_types;

		$this->render_template(
			WC_ADP_PLUGIN_PATH . 'views/tabs/tools.php',
			compact( 'groups', 'import_data_types' )
		);
	}

	protected function action_groups( $data, $import_data_type ) {
	    $this->action_options_group($data, $import_data_type);
	    $this->action_rules_group($data, $import_data_type);
	}

	protected function action_options_group( $data, $import_data_type ) {

	    if ( $import_data_type !== self::IMPORT_TYPE_OPTIONS ) {
		return;
	    }

	    WDP_Helpers::set_settings( filter_var_array( $data, WDP_Helpers::get_validate_filters() ) );
	}

	protected function prepare_export_groups() {
		$this->prepare_options_group();
		$this->prepare_export_group();
	}

	protected function prepare_options_group(){
		$options = WDP_Helpers::get_settings();

		$options_group = array(
			'label' => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
			'data'  => $options,
		);

		$this->groups['options'] = array(
			'label' => __( 'Options', 'advanced-dynamic-pricing-for-woocommerce' ),
			'items' => array( 'options' => $options_group ),
		);
	}

	public function admin_enqueue_scripts() {
		$is_settings_page = isset( $_GET['page'] ) && $_GET['page'] == 'wdp_settings';
		// Load backend assets conditionally
		if ( ! $is_settings_page ) {
			return;
		}
		wp_enqueue_script( 'wdp-tools', WC_ADP_PLUGIN_URL . '/assets/js/tools.js', array(), WC_ADP_VERSION, true );
	}

	protected function action_rules_group($data, $import_data_type) {

	    if ( $import_data_type !== self::IMPORT_TYPE_RULES ) {
		return;
	    }

	    WDP_Importer::import_rules( $data, $_POST['wdp-import-data-reset-rules'] );
	}

	protected function prepare_export_group() {
		$export_items = array();

		$rules = WDP_Database::get_rules();

		foreach ( $rules as &$rule ) {
			unset( $rule['id'] );

			if ( ! empty( $rule['filters'] ) ) {
				foreach ( $rule['filters'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = $this->convert_elements_from_id_to_name( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['get_products']['value'] ) ) {
				foreach ( $rule['get_products']['value'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = $this->convert_elements_from_id_to_name( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as &$item ) {
					foreach ( $item['options'] as &$option_item ) {
						if ( is_array( $option_item ) ) {
							$converted = null;
							try {
								$converted = $this->convert_elements_from_id_to_name( $option_item, $item['type'] );
							} catch ( Exception $e ) {

							}

							if ( $converted ) {
								$option_item = $converted;
							}
						}
					}
				}
				unset( $item );
			}
		}
		unset( $rule );

		$export_items['all'] = array(
			'label' => __( 'All', 'advanced-dynamic-pricing-for-woocommerce' ),
			'data'  => $rules,
		);

		foreach ( $rules as $rule ) {
			$export_items[] = array(
				'label' => "{$rule['title']}",
				'data'  => array( $rule ),
			);
		}

		$this->groups['rules'] = array(
			'label' => __( 'Rules', 'advanced-dynamic-pricing-for-woocommerce' ),
			'items' => $export_items
		);
	}

	protected function convert_elements_from_id_to_name( $items, $type ) {
		if ( empty( $items ) ) {
			return $items;
		}
		foreach ( $items as &$value ) {
			if ( 'products' === $type ) {
				$value = WDP_Helpers::get_product_title( $value );
			} elseif ( 'product_categories' === $type ) {
				$value = WDP_Helpers::get_category_title( $value );
			} elseif ( 'product_tags' === $type ) {
				$value = WDP_Helpers::get_tag_title( $value );
			} elseif ( 'product_attributes' === $type ) {
				$value = WDP_Helpers::get_attribute_title( $value );
			}
		}

		return $items;
	}
}