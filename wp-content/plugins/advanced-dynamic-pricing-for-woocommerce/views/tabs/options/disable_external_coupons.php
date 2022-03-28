<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc"><?php _e('Disable external coupons if any rule applied', 'advanced-dynamic-pricing-for-woocommerce') ?></th>
    <td class="forminp forminp-checkbox">
        <fieldset>
            <legend class="screen-reader-text"><span><?php _e('Disable external coupons if any rule applied', 'advanced-dynamic-pricing-for-woocommerce') ?></span></legend>
            <label for="disable_external_coupons">
                <input <?php checked( $options['disable_external_coupons'] ); ?>
                        name="disable_external_coupons" id="disable_external_coupons" type="checkbox">
            </label>
        </fieldset>
    </td>
</tr>
