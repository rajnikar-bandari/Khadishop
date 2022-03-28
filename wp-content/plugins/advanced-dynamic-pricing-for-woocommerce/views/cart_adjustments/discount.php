<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="wdp-column wdp-cart-adjustment-value">
    <input name="rule[cart_adjustments][{ca}][options][0]" class="adjustment-value" type="number"
           placeholder="0.00" step="any" min="0">
</div>
<div class="wdp-column wdp-cart-adjustment-value">
    <input name="rule[cart_adjustments][{ca}][options][1]" class="adjustment-value" type="text"
           placeholder="<?php _e( 'discount name', 'advanced-dynamic-pricing-for-woocommerce' ) ?>">
</div>