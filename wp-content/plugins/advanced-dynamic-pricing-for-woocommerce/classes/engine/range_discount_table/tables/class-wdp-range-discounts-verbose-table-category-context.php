<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Range_Discounts_Verbose_Table_Category_Context extends WDP_Range_Discounts_Table_Abstract {
	/**
	 * @param WDP_Price_Display $price_display
	 * @param integer           $term_id
	 */
	public function load_rule( $price_display, $term_id ) {
		$active_rules = WDP_Rules_Registry::get_instance()->get_active_rules()->with_bulk()->to_array();
		$matched_rule         = null;

		foreach ( $active_rules as $index => $rule ) {
			if ( ! $rule->support_output_discount_table() ) {
				continue;
			}

			$data = $rule->get_bulk_details( $price_display->get_cart()->get_context() );

			if ( ! count( $data['ranges'] ) ) {
				continue;
			}

			$filters = $rule->get_rule_filters();

			foreach ( $filters as $filter ) {
				if ( 'product_categories' === $filter['type'] && in_array( $term_id, $filter['values'] ) ) {
					$matched_rule = $rule;
				}
			}

			if ( $matched_rule ) {
				break;
			}
		}

		if ( $matched_rule ) {
			$this->fill_bulk_details( $matched_rule->get_bulk_details( $price_display->get_cart()->get_context() ) );
			$this->rule               = $matched_rule;
			$this->object_id          = $term_id;

			$calc          = new WDP_Cart_Calculator( new WDP_Rules_Collection( array( $matched_rule ) ) );
			$this->price_display = new WDP_Price_Display();
			$this->price_display->attach_calc( $calc );
			$this->price_display->apply_empty_cart();
		}
	}

	public function get_table_header() {
		$table_header        = array();
		$table_header['qty'] = $this->get_theme_option( 'table_header_text_qty' );

		if ( $this->get_theme_option( 'is_show_discount_value' ) ) {
			if ( $this->is_discount_type_fixed_price() ) {
				$table_header['discount_value'] = $this->get_theme_option( 'table_header_text_for_fixed_price_discount_value' );
			} else {
				$table_header['discount_value'] = $this->get_theme_option( 'table_header_text_discount_value' );
			}
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
							if ( in_array( $this->discount_type, array( 'price__fixed', 'discount__amount' ) ) ) {
								$value = wc_price( $line['value'] );
							} elseif ( 'discount__percentage' === $this->discount_type ) {
								$value = "{$line['value']}%";
							}
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