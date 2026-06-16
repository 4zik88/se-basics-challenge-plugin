<?php
/**
 * Uninstall: remove tables, options and cached badges.
 *
 * @package NAASE_Challenge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-naase-db.php';

// Drop custom tables.
NAASE_DB::drop_tables();

// Remove settings.
delete_option( 'naase_settings' );

// Remove cached badges.
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'naase-badges';
if ( is_dir( $dir ) ) {
	foreach ( (array) glob( $dir . '/*.png' ) as $file ) {
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
