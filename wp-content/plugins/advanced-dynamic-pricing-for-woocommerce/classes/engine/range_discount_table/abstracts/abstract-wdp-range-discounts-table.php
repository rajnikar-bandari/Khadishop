<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WDP_Range_Discounts_Table_Abstract {
	/**
	 * @var WDP_Rule
	 */
	protected $rule;

	/**
	 * @var WDP_Price_Display
	 */
	protected $price_display;

	protected $object_id;

	// Bulk details
	protected $type;
	protected $discount_type;
	protected $table_message;
	protected $ranges;

	protected $theme_options;
	protected $text_domain = 'advanced-dynamic-pricing-for-woocommerce';
	
	protected $filters_formatter;

	public function __construct() {
		$this->filters_formatter = apply_filters( 'wdp_get_range_discounts_filter_formatter', new WDP_Range_Discounts_Filters_Formatter() );
	}

	/**
	 * @param WDP_Price_Display $price_display
	 * @param integer           $object_id
	 */
	public function load_rule( $price_display, $object_id ) {
		return;
	}

	public function load_theme_options( $options ) {
		$this->theme_options = $options;
	}

	/**
	 * @param array             $table_header
	 *
	 * @return array
	 */
	public function get_table_rows( $table_header ) {
		return array();
	}

	public function get_table_header() {
		return array();
	}

	public function is_ready() {
		return ! empty( $this->rule ) && ! empty( $this->object_id );
	}

	protected function get_theme_option( $key ) {
		if ( ! isset( $this->theme_options[ $key ] ) ) {
//			throw new Exception( sprintf( "Theme option '%s' not found", $key ) );
			return null;
		}

		return $this->theme_options[ $key ];
	}

	protected function fill_bulk_details( $bulk_details ) {
		$this->type          = $bulk_details['type'];
		$this->discount_type = $bulk_details['discount'];
		$this->table_message = __( apply_filters( 'wdp_format_bulk_table_message',  $bulk_details['table_message'] ), $this->text_domain );
		$this->ranges        = $bulk_details['ranges'];
	}

	public function get_header_html() {
		$header_title          = "";
		$use_message_as_header = $this->get_theme_option( 'use_message_as_header' );

		if ( $use_message_as_header ) {
			$header_title = $this->table_message;
		} elseif ( WDP_Range_Discounts_Table::TYPE_BULK === $this->type ) {
			$header_title = $this->get_theme_option( 'table_header_for_bulk' );
		} elseif ( WDP_Range_Discounts_Table::TYPE_TIER === $this->type ) {
			$header_title = $this->get_theme_option( 'table_header_for_tier' );
		}

		return $header_title;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function get_footer_html() {
		$is_show_footer = $this->get_theme_option( 'is_show_footer' );

		if ( ! $is_show_footer ) {
			return "";
		}

		$result                = "";
		$use_message_as_header = $this->get_theme_option( 'use_message_as_header' );

		if ( $this->table_message ) {
			if ( ! $use_message_as_header ) {
				$result = "<p>" . $this->table_message . "</p>";
			}
		} else {
			$humanized_filters = $this->filters_formatter->format_rule( $this->rule );
			if ( $humanized_filters ) {
				$result = "<div>" . __( 'Bulk pricing will be applied to package:', $this->text_domain ) . "</div>";
				$result .= "<ul>";
				foreach ( $humanized_filters as $filter_text ) {
					$result .= "<li>" . $filter_text . "</li>";
				}
				$result .= "</ul>";
			}
		}

		return $result;
	}

	protected function is_discount_type_fixed_price() {
		return in_array( $this->discount_type, array( 'set_price__fixed', 'price__fixed' ) );
	}

}
