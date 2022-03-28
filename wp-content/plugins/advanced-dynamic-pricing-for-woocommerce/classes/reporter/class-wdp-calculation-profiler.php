<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WDP_Calculation_Profiler {
	/**
	 * @var WDP_Price_Display $price_display
	 */
	private $price_display;

	/**
	 * @var WDP_Report_Output
	 */
	private $report_display;

	/**
	 * @var string
	 */
	private $import_key = null;

	/**
	 * @var WDP_Cart_Calculator_Listener
	 */
	private $calc_listener;

	private $expiration_time_is_seconds = 1200;

	private static $bounce_back_request_key = 'wdp_bounce_back';
	private static $download_report_import_key_request_key = 'import_key';
	private static $bounce_back_request_value = '1';

	public function __construct() {
		if ( $this->check_permission() ) {
			$this->action_create_import_key();
			$this->action_ajax();
		}
	}

	public function admin_install() {
		if ( ! $this->check_permission() ) {
			return false;
		}

		return true;
	}

	public function install() {
	    if ( ! $this->check_permission() ) {
	        return false;
        }

		if ( isset( $_REQUEST[ $this::$bounce_back_request_key ] ) ) {
			$this->action_bounce_back();
		} else if ( $this->is_show_debug_bar() ) {
			$this->action_show_report_tab();
		} else {
		    return false;
        }

		$this->calc_listener = new WDP_Cart_Calculator_Listener();
		$this->action_collect_report();

		return true;
	}

	public function action_create_import_key() {
		add_action( 'wp_loaded', function () {
			$this->import_key = $this->create_import_key();
		} );
	}

	public function action_show_report_tab() {
		add_action( 'wp_footer', array( $this, 'print_report' ) );

		// must be after action_create_import_key()
		add_action( 'wp_loaded', function () {
			$this->report_display = new WDP_Report_Output( $this->import_key );
		}, 100 );
	}

	public function action_collect_report() {
		add_action( 'wp_footer', array( $this, 'collect_report' ), PHP_INT_MAX ); // do not use shutdown hook
		if ( wp_doing_ajax() ) {
			add_action( 'wdp_after_apply_to_wc_cart', array( $this, 'collect_report' ) );
		}
	}

	public function action_ajax() {
		add_action( 'wp_ajax_get_user_report_data', array( $this, 'get_user_report_data' ) );
		add_action( 'wp_ajax_download_report', array( $this, 'handle_download_report' ) );
	}

	public function action_bounce_back() {
		add_action( "wp_print_scripts", function () {
			$referer = wp_get_referer();
			$referer = $referer ?: admin_url();
			WC()->session->set( "wdp_last_import_key", $this->import_key );

			?>
			<meta http-equiv="refresh" content="0; url=<?php echo $referer;?>">
			<?php
		} );
	}

	public function use_price_display( $price_display ) {
		$this->price_display = $price_display;
	}

	public static function get_bounce_back_url() {
		return add_query_arg( self::$bounce_back_request_key, self::$bounce_back_request_value, get_permalink( wc_get_page_id( 'shop' ) ) );
	}

	public static function get_bounce_back_report_download_url() {
		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		$session       = new $session_class();
		/**
		 * @var WC_Session_Handler $session
		 */
		$session->init();

		if ( isset( $session->wdp_last_import_key ) ) {
			$import_key = $session->wdp_last_import_key;
			unset( $session->wdp_last_import_key );
			$session->save_data();
		} else {
			$import_key = false;
		}

		return ! $import_key ? "" : add_query_arg( array(
			'action'                                      => 'download_report',
			self::$download_report_import_key_request_key => $import_key,
			'reports'                                     => 'all',
		), admin_url( "admin-ajax.php" ) );
	}

	public function collect_report() {
		$active_rules = $this->price_display ? $this->price_display->get_calculator()->get_rule_array() : array();

		$active_rules_as_dict = array();
		foreach ( $active_rules as $wdp_rule ) {
			/**
			 * @var $wdp_rule WDP_Rule
			 */
			$new_data                  = $wdp_rule->get_rule_data();
			$new_data['edit_page_url'] = $wdp_rule->get_edit_page_url();

			$active_rules_as_dict[ $wdp_rule->get_id() ] = $new_data;
		}

		if ( wp_doing_ajax() && $this->price_display ) {
			$prev_processed_products_report = get_transient( $this->get_report_transient_key( 'processed_products' ) );
			foreach ( $prev_processed_products_report as $report ) {
				if ( isset( $report['data']['id'] ) ) {
					$this->price_display->process_product( (int) $report['data']['id'] );
				}
			}
		}

		$reports = array(
			'initial_cart'       => null,
			'processed_cart'     => ( new WDP_Reporter_WC_Cart_Collector( $this->calc_listener ) )->collect(),
			'processed_products' => ( new WDP_Reporter_Products_Collector( $this->price_display, $this->calc_listener ) )->collect(),
			'rules_timing'       => ( new WDP_Reporter_Rules_Timing_Collector( $this->calc_listener, $active_rules ) )->collect(),
			'options'            => ( new WDP_Reporter_Options_Collector() )->collect(),
			'additions'          => ( new WDP_Reporter_Plugins_And_Theme_Collector() )->collect(),
			'active_hooks'       => ( new WDP_Reporter_Active_Hooks_Collector() )->collect(),

			'rules' => $active_rules_as_dict,
		);

		foreach ( $reports as $report_key => $report ) {
			set_transient( $this->get_report_transient_key( $report_key ), $report, $this->expiration_time_is_seconds );
		}
	}

	public function is_show_debug_bar() {
		$options = WDP_Helpers::get_settings();

		return ! empty( $options['show_debug_bar'] );
	}

	public function check_permission() {
		return is_super_admin( get_current_user_id() );
    }

	public function print_report() {
		if ( is_super_admin( get_current_user_id() ) ) {
			$this->report_display->output();
		}
	}

	private function get_report_transient_key( $report_key ) {
		return sprintf( "wdp_profiler_%s_%s", $report_key, $this->import_key );
	}

	private function create_import_key() {
		if ( ! did_action( 'wp_loaded' ) ) {
			_doing_it_wrong( __FUNCTION__, sprintf( __( '%1$s should not be called before the %2$s action.', 'woocommerce' ), 'create_import_key', 'wp_loaded' ), WC_ADP_VERSION );

			return null;
		}

		global $wp;

		return substr( md5( $wp->request . '|' . (string) get_current_user_id() ), 0, 8 );
	}

	/**
	 * @param $calc WDP_Cart_Calculator
	 */
	public function attach_listener( $calc ) {
		$calc->add_subscriber( $this->calc_listener );
	}

	public function get_user_report_data() {
		$import_key = isset( $_REQUEST['import_key'] ) ? $_REQUEST['import_key'] : false;

		if ( $import_key === $this->import_key ) {
			$data = $this->make_response_data();
			if ( $data ) {
				wp_send_json_success( $data );
			} else {
				wp_send_json_error( __( 'Import key not found', 'advanced-dynamic-pricing-for-woocommerce' ) );
			}
		} else {
			wp_send_json_error( __( 'Wrong import key', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}
	}

	private function make_response_data() {
		$required_keys = array(
			'processed_cart',
			'processed_products',
			'rules_timing',
			'rules'
		);
		$data          = array();

		foreach ( $required_keys as $key ) {
			$data[ $key ] = get_transient( $this->get_report_transient_key( $key ) );
		}

		$rules_data = array();
		foreach ( $data['rules'] as $rule ) {
			/**
			 * @var $rule WDP_Rule
			 */
			$rules_data[ $rule['id'] ] = array(
				'title'         => $rule['title'],
				'edit_page_url' => $rule['edit_page_url'],
			);
		}
		$data['rules'] = $rules_data;

		return $data;
	}


	public function handle_download_report() {
		$import_key = isset( $_REQUEST[ self::$download_report_import_key_request_key ] ) ? $_REQUEST[ self::$download_report_import_key_request_key ] : false;

		if ( ! $import_key ) {
			wp_send_json_error( __( 'Import key not provided', 'advanced-dynamic-pricing-for-woocommerce' ) );
        }

		if ( ! is_super_admin( get_current_user_id() ) ) {
			if ( $import_key !== $this->import_key ) {
				wp_send_json_error( __( 'Wrong import key', 'advanced-dynamic-pricing-for-woocommerce' ) );
			}
        }

		if ( empty( $_REQUEST['reports'] ) ) {
			wp_send_json_error( __( 'Wrong value for parameter "reports"', 'advanced-dynamic-pricing-for-woocommerce' ) );
		}
		$reports = explode(',', $_REQUEST['reports']);
		$keys = array(
			'initial_cart',
			'processed_cart',
			'processed_products',
			'rules_timing',
			'options',
			'additions',
			'active_hooks',
			'rules',
		);

		if ( ! in_array( 'all', $reports ) ) {
			$keys = array_intersect( $keys, $reports );
		}

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_transient( $this->get_report_transient_key( $key ) );
		}

		$tmp_dir  = ini_get( 'upload_tmp_dir' ) ? ini_get( 'upload_tmp_dir' ) : sys_get_temp_dir();
		$filepath = @tempnam( $tmp_dir, 'wdp' );
		$handler  = fopen( $filepath, 'a' );
		fwrite( $handler, json_encode( $data, JSON_PRETTY_PRINT ) );
		fclose( $handler );

		$this->kill_buffers();
		header( 'Content-type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filepath ) . '.json' . '"' );
		$this->send_contents_delete_file( $filepath );

		wp_die();
	}

	private function kill_buffers() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	private function send_contents_delete_file( $filename ) {
		if ( ! empty( $filename ) ) {
			if ( ! $this->function_disabled( 'readfile' ) ) {
				readfile( $filename );
			} else {
				// fallback, emulate readfile
				$file = fopen( $filename, 'rb' );
				if ( $file !== false ) {
					while ( ! feof( $file ) ) {
						echo fread( $file, 4096 );
					}
					fclose( $file );
				}
			}
			unlink( $filename );
		}
	}

	private function function_disabled( $function ) {
		$disabled_functions = explode( ',', ini_get( 'disable_functions' ) );

		return in_array( $function, $disabled_functions );
	}

}