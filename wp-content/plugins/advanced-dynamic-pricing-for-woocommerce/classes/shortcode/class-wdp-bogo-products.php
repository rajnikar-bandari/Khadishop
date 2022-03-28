<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once WC_ADP_PLUGIN_PATH . 'classes/shortcode/class-wdp-shortcode-products.php';

class WDP_Shortcode_Products_Bogo extends WDP_Shortcode_Products {

    const NAME		= 'adp_products_bogo';
	const STORAGE_KEY	= 'wdp_products_bogo';
	
	protected function set_adp_products_bogo_query_args( &$query_args ) {
	    $query_args['post__in'] = array_merge( array( 0 ), static::get_cached_products_ids() );
    }

    public static function get_products_ids() {

		global $wpdb;

		$settings = WDP_Helpers::get_settings();
		$apply_mode = $settings['rules_apply_mode'];

		$rule_array = $apply_mode != "none" ? WDP_Rules_Registry::get_instance()->get_active_rules()->to_array() : array();
		$sql_generator = WDP_Loader::get_rule_sql_generator_class();

			foreach ( $rule_array as $rule ) {
				if ( $rule->is_simple_bogo_rule() ) {
					$sql_generator->apply_rule_to_query( $rule );
				}
			}

		if ( $sql_generator->is_empty() ) {
			return array();
		}

		$sql_joins = $sql_generator->get_join();
		$sql_where = $sql_generator->get_where();

		$sql = "SELECT post.ID as id, post.post_parent as parent_id FROM `$wpdb->posts` AS post
			".implode(" ", $sql_joins)."
			WHERE post.post_type IN ( 'product', 'product_variation' )
				AND post.post_status = 'publish'
			". ($sql_where ? " AND " : "") . implode(" OR ", array_map(function ($v) { return "(".$v.")"; }, $sql_where))."
			GROUP BY post.ID";

		$bogo_products = $wpdb->get_results($sql);

		$product_ids_bogo = wp_parse_id_list(array_merge(
			wp_list_pluck( $bogo_products, 'id' ),
			array_diff( wp_list_pluck( $bogo_products, 'parent_id' ), array( 0 ) )
		));

		return $product_ids_bogo;
	}
}