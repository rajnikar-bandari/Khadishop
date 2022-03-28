<?php

class WDP_Reporter_Options_Collector {
	public function collect() {
		return array(
			'wdp' => $this->get_wdp_options(),
			'wc'  => $this->get_wc_options(),
		);
	}

	public function get_wdp_options() {
		return WDP_Helpers::get_settings();
	}

	public function get_wc_options() {
		return array(
			'woocommerce_calc_taxes'            => wc_tax_enabled(),
			'woocommerce_ship_to_countries'     => wc_shipping_enabled(),
			'woocommerce_prices_include_tax'    => wc_prices_include_tax(),
			'woocommerce_enable_coupons'        => wc_coupons_enabled(),
			'woocommerce_tax_round_at_subtotal' => get_option( 'woocommerce_tax_round_at_subtotal' )
		);
	}

}