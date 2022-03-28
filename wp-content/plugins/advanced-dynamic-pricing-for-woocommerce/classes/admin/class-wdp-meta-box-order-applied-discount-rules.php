<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Meta_Box_Order_Applied_Discount_Rules {

    private static $rules = null;

    public static function init() {
        global $post;

        self::$rules = WDP_Database::get_applied_rules_for_order( $post->ID );

        if ( !empty( self::$rules) ) {
            add_meta_box( 'wdp-order-applied-rules', __( 'Applied discounts', 'advanced-dynamic-pricing-for-woocommerce' ),
                'WDP_Meta_Box_Order_Applied_Discount_Rules::output', 'shop_order', 'side' );
        }
    }

	/**
	 * Output the metabox.
	 *
	 * @param WP_Post $post
	 */
	public static function output( $post ) {
        ?>
        <style> .wdp-aplied-rules, .wdp-aplied-rules td:first-child { width: 100%; } </style>
        <table class="wdp-aplied-rules">
            <?php foreach (self::$rules as $row): ?>
                <tr>
                    <td><a href="<?php echo self::rule_url( $row ); ?>"><?php echo $row->title; ?></a></td>
                    <td><?php
		                $amount = floatval( $row->amount + $row->extra + $row->gifted_amount );
		                echo empty( $amount ) ? '-' : wc_price( $amount );
		                ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

	private static function rule_url( $row ) {
		return add_query_arg(
			array(
				'rule_id' => $row->id,
				'tab'     => 'rules',
			),
			admin_url( 'admin.php?page=wdp_settings' )
		);
	}
}