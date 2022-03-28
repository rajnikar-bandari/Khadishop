<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc"><?php _e('Disable external coupons only if any of cart items updated', 'advanced-dynamic-pricing-for-woocommerce') ?></th>
    <td class="forminp forminp-checkbox">
        <fieldset>
            <legend class="screen-reader-text"><span><?php _e('Disable external coupons only if any of cart items updated', 'advanced-dynamic-pricing-for-woocommerce') ?></span></legend>
            <label for="disable_external_coupons_only_if_items_updated">
                <input <?php checked( $options['disable_external_coupons_only_if_items_updated'] ); ?>
                        name="disable_external_coupons_only_if_items_updated" id="disable_external_coupons_only_if_items_updated" type="checkbox">
            </label>
        </fieldset>
    </td>
</tr>
