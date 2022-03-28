<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range_Discounts_Simple_Table_Product_Context extends WDP_Range_Discounts_Table_Product_Context_Abstract {
	public function get_table_header() {
		$table_header = array();

		foreach ( $this->ranges as $index => $line ) {
			if ( $line['from'] == $line['to'] ) {
				$value = $line['from'];
			} else {
				if ( empty( $line['to'] ) ) {
					$value = $line['from'] . ' +';
				} else {
					$value = $line['from'] . ' - ' . $line['to'];
				}
			}

			$table_header[ $index ] = apply_filters( 'wdp_format_bulk_record', $value, $line );
		}

		return $table_header;
	}

	/**
	 * @param array             $table_header
	 *
	 * @return array
	 */
	public function get_table_rows( $table_header ) {
		$row = array();
		foreach ( array_keys( $table_header ) as $index ) {
			$line = $this->ranges[ $index ];

			$product = WDP_Object_Cache::get_instance()->get_wc_product( $this->object_id );
			$wdp_product = $this->price_display->process_product( $product, (float) $line['from'] );

			if ( ! is_null( $wdp_product ) ) {
				if ( $product->is_type( 'variable' ) ) {
					if ( $wdp_product->are_all_children_having_save_new_price() ) {
						$value = wc_price( $wdp_product->get_new_price() );
					} else {
						$value = "-";
					}
				} else {
					$value = wc_price( $wdp_product->get_new_price() );
				}
			} else {
				$value = null;
			}

			$row[ $index ] = $value;
		}

		return array( $row );
	}
}