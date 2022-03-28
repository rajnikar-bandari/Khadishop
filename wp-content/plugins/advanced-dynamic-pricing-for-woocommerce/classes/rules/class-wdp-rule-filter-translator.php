<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Rule_Filter_Translator {

	public function translate_by_type( $type, $value, $language_code ) {
		$return_as_array = is_array( $value );
		$values          = is_array( $value ) ? $value : array( $value );

		if ( 'products' === $type ) {
			$values = $this->translate_product( $values, $language_code );
		} else if ( 'product_categories' === $type ) {
			$values = $this->translate_category( $values, $language_code );
		} else if ( 'product_category_slug' === $type ) {
			$values = $this->translate_category_slug( $values, $language_code );
		} else if ( 'product_attributes' === $type ) {
			$values = $this->translate_attribute( $values, $language_code );
		} else if ( 'product_tags' === $type ) {
			$values = $this->translate_tag( $values, $language_code );
		} else if ( 'product_skus' === $type ) {
			// do not translate
		} else if ( 'product_custom_fields' === $type ) {
			// do not translate
		} else {
			$values = $this->translate_custom_tax( $values, $type, $language_code );
		}

		return $return_as_array ? $values : reset( $values );
	}

	public function translate_product( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'post', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translate_category( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'product_cat', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translate_category_slug( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$slugs           = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $slugs as &$slug ) {
			// translated in get_term_by
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$slug = $term->slug;
			}
		}

		return $return_as_array ? $slugs : reset( $slugs );
	}

	public function translate_attribute( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			// translated in get_term
			$term = get_term( $id );
			if ( $term && ! is_wp_error( $term ) ) {
				$id = $term->term_id;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translate_tag( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'product_tag', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translate_custom_tax( $the_value, $tax, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, $tax, false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

}