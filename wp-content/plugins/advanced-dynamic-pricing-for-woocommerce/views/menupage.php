<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * @var WDP_Admin_Abstract_Page[] $tabs
 * @var string                    $current_tab
 * @var WDP_Admin_Abstract_Page   $handler
 */
?>

<div class="wrap woocommerce">

    <h2 class="wcp_tabs_container nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_handler ): ?>
            <a class="nav-tab <?php echo( $tab_key === $current_tab ? 'nav-tab-active' : '' ); ?>"
               href="admin.php?page=wdp_settings&tab=<?php echo $tab_key; ?>"><?php echo $tab_handler->title; ?></a>
		<?php endforeach; ?>
    </h2>

    <div class="wdp_settings ui-page-theme-a">
        <div class="wdp_settings_container">
			<?php
			$handler->render();
			?>
        </div>
    </div>

</div>