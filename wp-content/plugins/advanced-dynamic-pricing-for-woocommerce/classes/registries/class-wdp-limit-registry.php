<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Limit_Registry {
	private $items;

	private static $instance;

	protected function __construct() {
		$items = array(
			'max_usage' => array(
				'class'    => 'WDP_Limit_Max_Usage',
				'label'    => __( 'Max usage', 'advanced-dynamic-pricing-for-woocommerce' ),
				'group'    => __( 'Usage restrictions', 'advanced-dynamic-pricing-for-woocommerce' ),
				'template' => WC_ADP_PLUGIN_PATH . 'views/limits/max-usage.php',
			),
		);

		$this->items = apply_filters( 'wdp_limits', $items );
	}

	/**
	 * @return array
	 */
	public function get_limits_list() {
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
	 * @return WDP_Limit
	 * @throws Exception
	 */
	public function create_limit( $data ) {
		$type = $data['type'];
		if ( isset( $this->items[ $type ] ) ) {
			$class = $this->items[ $type ]['class'];
			$limit = new $class( $data );

			return $limit;
		} else {
			throw new Exception( 'Wrong limit' );
		}
	}

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}