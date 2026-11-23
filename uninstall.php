<?php
/**
 * Uninstall: remove plugin settings, scheduled jobs and cached video data. Albums are your content and are kept.
 *
 * @package Coverfold
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'coverfold_settings' );
delete_option( 'coverfold_db_version' );
delete_option( 'coverfold_migration_notice' );
delete_option( 'coverfold_cache_version' );

// Scheduled refreshes and cached remote video data (transients named coverfold_{version}_{hash}).
wp_unschedule_hook( 'coverfold_refresh_video_data' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup on uninstall; transients have no API to list them by prefix.
$coverfold_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_coverfold_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_coverfold_' ) . '%'
	)
);
foreach ( array_unique( preg_replace( '/^_transient_(timeout_)?/', '', $coverfold_options ) ) as $coverfold_transient ) {
	delete_transient( $coverfold_transient );
}
