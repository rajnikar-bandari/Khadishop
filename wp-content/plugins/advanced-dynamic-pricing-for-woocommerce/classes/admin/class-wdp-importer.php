<?php

class WDP_Importer {

	public static function import_rules( $data, $reset_rules ) {
		if ( $reset_rules ) {
			WDP_Database::delete_all_rules();
		}
		$imported = array();

		foreach ( $data as $rule ) {
			unset( $rule['id'] );

			$rule['enabled'] = ( isset( $rule['enabled'] ) && $rule['enabled'] === 'on' ) ? 1 : 0;

			if ( ! empty( $rule['filters'] ) ) {
				foreach ( $rule['filters'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = self::convert_elements_from_name_to_id( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['get_products']['value'] ) ) {
				foreach ( $rule['get_products']['value'] as &$item ) {
					$item['value'] = isset( $item['value'] ) ? $item['value'] : array();
					$item['value'] = self::convert_elements_from_name_to_id( $item['value'], $item['type'] );
				}
				unset( $item );
			}

			if ( ! empty( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as &$item ) {
					if ( ! isset( $item['options'][2] ) ) {
						continue;
					}

					$item['options'][2] = self::convert_elements_from_name_to_id( $item['options'][2], $item['type'] );
				}
				unset( $item );
			}

			$attributes = array(
				'options',
				'conditions',
				'filters',
				'limits',
				'cart_adjustments',
				'product_adjustments',
				'bulk_adjustments',
				'role_discounts',
				'get_products',
				'sortable_blocks_priority',
				'additional',
			);
			foreach ( $attributes as $attr ) {
				$rule[ $attr ] = serialize( isset( $rule[ $attr ] ) ? $rule[ $attr ] : array() );
			}

			$imported[] = WDP_Database::store_rule( $rule );
		}

		return $imported;
	}

	protected static function convert_elements_from_name_to_id( $items, $type ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return $items;
		}
		foreach ( $items as &$value ) {
			if ( 'products' === $type ) {
				$value = WDP_Helpers::get_product_id( $value );
			} elseif ( 'product_categories' === $type ) {
				$value = WDP_Helpers::get_category_id( $value );
			} elseif ( 'product_tags' === $type ) {
				$value = WDP_Helpers::get_tag_id( $value );
			} elseif ( 'product_attributes' === $type ) {
				$value = WDP_Helpers::get_attribute_id( $value );
			}

			if ( empty( $value ) ) {
				$value = 0;
			}
		}

		return $items;
	}


}