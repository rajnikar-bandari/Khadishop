<?php
/*================================================
#Load the Parent theme style.css file
================================================*/
function wpninja_enqueue_styles() {
	wp_enqueue_style( 'divi-parent', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'wpninja_enqueue_styles' );

/*================================================
#Load the translations from the child theme folder
================================================*/
function wpcninja_translation() {
	load_child_theme_textdomain( 'Divi', get_stylesheet_directory() . '/lang/theme/' );
	load_child_theme_textdomain( 'et_builder', get_stylesheet_directory() . '/lang/builder/' );
}
add_action( 'after_setup_theme', 'wpcninja_translation' );

remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

/**
 * Show product thumbnail on checkout page functionality.
 *
 * @see {templates|woocommerce}/checkout/review-order.php
 */
add_filter( 'woocommerce_cart_item_name', 'jfs_checkout_show_product_thumbnail', 10, 2 );
function jfs_checkout_show_product_thumbnail( $name, $cart_item ) {
    if ( ! is_checkout() ) return $name;
    $thumbnail = '<span class="product-name__thumbnail" style="float: left; padding-right: 15px">' . get_the_post_thumbnail( $cart_item['product_id'], array( 60, 120 ) ) . '</span>';
    return $thumbnail . '<span class="product-name__text">' . $name . '</span>';
}