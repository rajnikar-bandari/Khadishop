<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_User_Impl implements WDP_User {
	/** @var WP_User|null */
	private $wp_user;
	private $shipping_country;
	private $shipping_state;
	private $payment_method;
	private $shipping_methods;
	private $is_vat_exempt;

	public function __construct( $wp_user = null ) {
		$this->wp_user = $wp_user;
	}

	public function is_logged_in() {
		return is_user_logged_in() && get_current_user_id() === $this->get_id();
	}

	public function get_id() {
		return ! $this->is_empty_wp_user() ? $this->wp_user->ID : null;
	}

	public function get_roles() {
		if ( $this->is_empty_wp_user() ) {
			return array();
		}
		$roles = ( array ) $this->wp_user->roles;

		return apply_filters( 'wdp_current_user_roles', $roles, $this );
	}

	/**
	 * @param $time
	 *
	 * @return int
	 */
	public function get_order_count_after( $time ) {
		if ( $time === false ) {
			return floatval( 0 );
		}

		$args = array(
			'post_status' => array_keys( wc_get_order_statuses() ),
		);

		if ( ! empty( $time ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => $time,
				),
			);
		}

		return count( $this->get_order_ids( $args ) );
	}

	/**
	 * @return false|WC_Order
	 */
	public function get_last_paid_order() {
		$order_ids = $this->get_order_ids( array(
			'post_status'    => array( 'wc-completed' ),
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		return wc_get_order( array_pop( $order_ids ) );
	}

	/**
	 * @param $time
	 *
	 * @return float
	 */
	public function get_total_spend_amount( $time ) {
		if ( $time === false ) {
			return floatval( 0 );
		}

		$args = array(
			'post_status' => array( 'wc-completed' ),
		);

		if ( ! empty( $time ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_date',
					'after'  => $time,
				),
			);
		}

		$order_ids = $this->get_order_ids( $args );

		$orders = array_filter( array_map( 'wc_get_order', $order_ids ) );

		if ( ! count( $orders ) ) {
			return floatval( 0 );
		}

		return array_sum( array_map( function ( $order ) {
			/**
			 * @var $order WC_Order
			 */
			return $order->get_total();
		}, $orders ) );
	}

	/**
	 * @return float
	 */
	public function get_avg_spend_amount() {
		$order_ids = $this->get_order_ids( array(
			'statuses'    => array( 'wc-completed' ),
		) );

		$orders = array_filter( array_map( 'wc_get_order', $order_ids ) );

		if ( ! count( $orders ) ) {
			return floatval( 0 );
		}

		return array_sum( array_map( array( reset( $orders ), 'get_total' ), $orders ) ) / count( $orders );
	}

	/**
	 * @param array $args
	 *
	 * @return int[]
	 */
	protected function get_order_ids( $args = array() ) {
		if ( $this->is_empty_wp_user() ) {
			return array();
		}

		$args = array_merge( array(
			'numberposts' => - 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_key'    => '_customer_user',
			'meta_value'  => $this->wp_user->ID,
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'fields'      => 'ids',

		), $args );

		return get_posts( $args );
	}

	public function is_empty_wp_user() {
		return ! $this->wp_user instanceof WP_User || empty( $this->wp_user->ID );
	}

	public function get_shipping_country() {
		return $this->shipping_country;
	}

	public function set_shipping_country( $country ) {
		$this->shipping_country = $country;
	}

	public function get_shipping_state() {
		return $this->shipping_state;
	}

	public function set_shipping_state( $state ) {
		$this->shipping_state = $state;
	}

	public function get_payment_method() {
		return $this->payment_method;
	}

	public function set_payment_method( $method ) {
		$this->payment_method = $method;
	}

	public function get_shipping_methods() {
		return $this->shipping_methods;
	}

	public function set_shipping_methods( $method ) {
		$this->shipping_methods = $method;
	}

	public function set_is_vat_exempt( $is_vat_exempt ) {
		$this->is_vat_exempt = wc_string_to_bool( $is_vat_exempt );
	}

	public function get_tax_exempt() {
		return $this->is_vat_exempt;
	}

	/**
	 * @param $customer WC_Customer
	 *
	 */
	public function apply_wc_customer( $customer ) {
		$this->shipping_country = $customer->get_shipping_country( '' );
		$this->shipping_state   = $customer->get_shipping_state( '' );
	}

	/**
	 * @param $session WC_Session
	 *
	 */
	public function apply_wc_session( $session ) {
		if ( is_checkout() ) {
			$this->payment_method = $session->get( 'chosen_payment_method' );
		}

		if ( is_checkout() OR ! WDP_Frontend::is_catalog_view() ) {
			$this->shipping_methods = $session->get( 'chosen_shipping_methods' );
		};
	}
}