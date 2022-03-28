<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Discount_Message {
	/**
	 * @var array
	 */
	protected $options;

	const PANEL_KEY = 'discount_message';

	const CONTEXT_CART = 'cart';
	const CONTEXT_MINI_CART = 'mini-cart';
	const CONTEXT_CHECKOUT = 'checkout';

	/**
	 * @var WDP_WC_Cart_Translator
	 */
	protected $cart_helper;

	/**
	 * @var WDP_WC_Order_Translator
	 */
	protected $order_helper;

	protected $amount_saved_label;

	public function __construct() {
		$this->options            = WDP_Helpers::get_settings();
		$this->cart_helper        = new WDP_WC_Cart_Translator();
		$this->order_helper       = new WDP_WC_Order_Translator();
		$this->amount_saved_label = __( "Amount Saved", 'advanced-dynamic-pricing-for-woocommerce' );
	}

	/**
	 * @param $customizer WDP_Customizer
	 */
	public function set_theme_options_email( $customizer ) {
		return;
	}

	/**
	 * @param $customizer WDP_Customizer
	 */
	public function set_theme_options( $customizer ) {
		// wait until filling get_theme_mod()
		add_action( 'wp_loaded', function () use ( $customizer ) {
			$contexts = array(
				self::CONTEXT_CART      => array( $this, 'output_cart_amount_saved' ),
				self::CONTEXT_MINI_CART => array( $this, 'output_mini_cart_amount_saved' ),
				self::CONTEXT_CHECKOUT  => array( $this, 'output_checkout_amount_saved' ),
			);

			$this->install_message_hooks( $customizer, $contexts );
		} );
	}

	/**
	 * @param WDP_Customizer $customizer
	 * @param array          $contexts
	 *
	 */
	protected function install_message_hooks( $customizer, $contexts ) {
		$theme_options = $customizer->get_theme_options();

		if ( ! isset( $theme_options[ self::PANEL_KEY ] ) ) {
			return;
		}

		$theme_options = $theme_options[ self::PANEL_KEY ];

		if ( isset( $theme_options['global']['amount_saved_label'] ) ) {
			$this->amount_saved_label = $theme_options['global']['amount_saved_label'];
		}

		foreach ( $contexts as $context => $callback ) {
			if ( ! isset( $theme_options[ $context ]['enable'], $theme_options[ $context ]['position'] ) ) {
				continue;
			}

			if ( $theme_options[ $context ]['enable'] ) {
				if ( has_action( "wdp_{$context}_discount_message_install" ) ) {
					do_action( "wdp_{$context}_discount_message_install", $this, $theme_options[ $context ]['position'] );
				} else {
					add_action( $theme_options[ $context ]['position'], $callback, 10 );
				}
			}
		}
	}

	public function get_option( $option, $default = false ) {
		return isset( $this->options[ $option ] ) ? $this->options[ $option ] : $default;
	}

	public function output_cart_amount_saved() {
		$amount_saved = $this->cart_helper->get_amount_saved();

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_CART, $amount_saved );
		}
	}

	public function output_mini_cart_amount_saved() {
		$amount_saved = $this->cart_helper->get_amount_saved();

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_MINI_CART, $amount_saved );
		}
	}

	public function output_checkout_amount_saved() {
		$amount_saved = $this->cart_helper->get_amount_saved();

		if ( $amount_saved > 0 ) {
			$this->output_amount_saved( self::CONTEXT_CHECKOUT, $amount_saved );
		}
	}

	public function output_amount_saved( $context, $amount_saved ) {
		switch ( $context ) {
			case self::CONTEXT_CART:
				$template = 'cart-totals.php';
				break;
			case self::CONTEXT_MINI_CART:
				$template = 'mini-cart.php';
				break;
			case self::CONTEXT_CHECKOUT:
				$template = 'cart-totals-checkout.php';
				break;
			default:
				$template = null;
				break;
		}

		if ( is_null( $template ) ) {
			return;
		}

		echo WDP_Frontend::wdp_get_template( $template, array(
			'amount_saved' => $amount_saved,
			'title'        => $this->amount_saved_label,
		), 'amount-saved' );
	}

}