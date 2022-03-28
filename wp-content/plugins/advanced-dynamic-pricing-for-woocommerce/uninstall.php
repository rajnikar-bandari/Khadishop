<?php

if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	$path = trailingslashit( dirname( __FILE__ ) );

	// delete tables  only if have value in settings
	$options = get_option( 'wdp_settings', array() );
	if ( isset( $options['uninstall_remove_data'] ) AND $options['uninstall_remove_data'] ) {
		include_once $path . 'classes/common/class-wdp-database.php';
		WDP_Database::delete_database();
	}

	$extension_file = $path . 'pro_version/uninstall.php';
	if ( file_exists( $extension_file ) ) {
		include_once $extension_file;
	}
}