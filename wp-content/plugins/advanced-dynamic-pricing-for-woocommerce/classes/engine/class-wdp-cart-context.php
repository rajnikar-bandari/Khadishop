<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Cart_Context {
	/**
	 * @var WDP_User
	 */
	private $customer;
	/**
	 * @var array
	 */
	private $environment;
	/**
	 * @var array
	 */
	private $settings;

	/**
	 * @param WDP_User $customer
	 * @param array    $environment
	 * @param array    $settings
	 */
	public function __construct( $customer, $environment, $settings ) {
		$this->customer    = $customer;
		$this->environment = $environment;
		$this->settings    = $settings;
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	public function datetime( $format ) {
		return date( $format, $this->environment['timestamp'] );
	}

	/**
	 * @return int
	 */
	public function time() {
		return $this->environment['timestamp'];
	}

	public function get_price_mode() {
		return $this->get_option( 'discount_for_onsale' );
	}

	public function is_combine_multiple_discounts() {
		return $this->get_option( 'combine_discounts' );
	}

	public function is_combine_multiple_fees() {
		return $this->get_option( 'combine_fees' );
	}

	public function get_shipping_country() {
		return $this->customer->get_shipping_country();
	}

	public function get_shipping_state() {
		return $this->customer->get_shipping_state();
	}

	public function get_payment_method() {
		return $this->customer->get_payment_method();
	}

	public function get_shipping_methods() {
		return $this->customer->get_shipping_methods();
	}

	public function is_customer_logged_in() {
		return $this->customer->is_logged_in();
	}

	public function get_customer_id() {
		return $this->customer->get_id();
	}

	public function get_customer_roles() {
		// all non registered users have a dummy 'wdp_guest' role
		return $this->customer->get_roles() ? $this->customer->get_roles() : array( 'wdp_guest' );
	}

	public function get_customer_order_count_after( $time_range ) {
		return $this->customer->get_order_count_after( $this->convert_for_strtotime( $time_range ) );
	}

	public function get_count_of_rule_usages( $rule_id ) {
		return WDP_Database::get_count_of_rule_usages( $rule_id );
	}

	public function get_count_of_rule_usages_per_customer( $rule_id, $customer_id ) {
		return WDP_Database::get_count_of_rule_usages_per_customer($rule_id, $customer_id);
	}

	public function is_tax_enabled() {
		return isset( $this->environment['tab_enabled'] ) ? $this->environment['tab_enabled'] : false;
	}

	public function is_prices_includes_tax() {
		return isset( $this->environment['prices_includes_tax'] ) ? $this->environment['prices_includes_tax'] : false;
	}

	public function get_tax_display_shop() {
		return isset( $this->environment['tax_display_shop'] ) ? $this->environment['tax_display_shop'] : '';
	}

	public function get_option( $key, $default = false ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	public function set_is_tax_exempt( $tax_exempt ) {
		$this->customer->set_is_vat_exempt( $tax_exempt );
	}

	public function get_tax_exempt() {
		return $this->customer->get_tax_exempt();
	}

	public function convert_for_strtotime( $time ) {
		if ( ! $time OR ! is_string( $time ) ) {
			return false;
		}

		if ( 'all_time' == $time ) {
			$time = 0;
		} elseif ( 'now' == $time ) {
			$time = 'today';
		} elseif ( 'this week' == $time ) {
			$time = 'last monday';
		} elseif ( 'this month' == $time ) {
			$time = 'first day of ' . date( 'F Y', current_time( 'timestamp' ) );
		} elseif ( 'this year' == $time ) {
			$time = 'first day of January ' . date( 'Y', current_time( 'timestamp' ) );
		}

		return $time;
	}

	public function get_customer_avg_spend_amount() {
		return $this->customer->get_avg_spend_amount();
	}

	public function get_customer_total_spend_amount( $time_range ) {
		return $this->customer->get_total_spend_amount( $this->convert_for_strtotime( $time_range ) );
	}

	public function get_customer_last_paid_order() {
		return $this->customer->get_last_paid_order();
	}
}