<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range_Discounts_Verbose_Table_Product_Context extends WDP_Range_Discounts_Table_Product_Context_Abstract {
	public function get_table_header() {
		$table_header        = array();
		$table_header['qty'] = $this->get_theme_option( 'table_header_text_qty' );

		if ( $this->get_theme_option( 'is_show_discount_value' ) ) {
			if ( $this->is_discount_type_fixed_price() ) {
				$table_header['discounted_price'] = $this->get_theme_option( 'table_header_text_for_fixed_price_discount_value' );
			} else {
				$table_header['discount_value'] = $this->get_theme_option( 'table_header_text_discount_value' );
			}
		}

		if ( ! $this->is_discount_type_fixed_price() && $this->get_theme_option( 'is_show_discounted_price' ) ) {
			$table_header['discounted_price'] = $this->get_theme_option( 'header_text_discount_price' );
		}

		return $table_header;
	}

	/**
	 * @param array $table_header
	 *
	 * @return array
	 */
	public function get_table_rows( $table_header ) {
		$rows = array();

		foreach ( $this->ranges as $line ) {
			$row = array();
			foreach ( array_keys( $table_header ) as $key ) {
				$value = null;

				switch ( $key ) {
					case 'qty':
						if ( $line['from'] == $line['to'] ) {
							$value = $line['from'];
						} else {
							if ( empty( $line['to'] ) ) {
								$value = $line['from'] . ' +';
							} else {
								$value = $line['from'] . ' - ' . $line['to'];
							}
						}

						$value = apply_filters( 'wdp_format_bulk_record', $value, $line );
						break;
					case 'discount_value':
						if ( isset( $line['value'] ) ) {
							if ( 'discount__percentage' === $this->discount_type ) {
								$value = "{$line['value']}%";
							} else {
								$value = wc_price( $line['value'] );
							}
						}
						break;
					case 'discounted_price':
						$product = WDP_Object_Cache::get_instance()->get_wc_product( $this->object_id );

						$wdp_product = $this->price_display->process_product( $product, (float) $line['from'] );
						if ( ! is_null( $wdp_product ) ) {
							if ( $product->is_type( 'variable' ) ) {
								if ( $wdp_product->are_all_children_having_save_new_price() ) {
									$value = wc_price( $wdp_product->get_new_price() );
								} else {
									break;
								}
							} else {
								$value = wc_price( $wdp_product->get_new_price() );
							}
						} else {
							$value = null;
						}

						break;
				}

				$row[ $key ] = ! is_null( $value ) ? $value : "-";
			}

			$rows[] = $row;
		}

		return $rows;
	}
}