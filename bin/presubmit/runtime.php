<?php
/**
 * Pre-submission check, step 2: on a clean site with WP_DEBUG, render the
 * block and the shortcodes, open the settings page, look at an admin screen
 * that isn't the plugin's, deactivate, uninstall, and count PHP notices.
 * Appends to /sp/runtime-<php>.txt.
 *
 * Calls hooks directly instead of firing init again: a second init floods the
 * log with notices that a real request never shows.
 *
 * Run by bin/presubmit/run.sh inside WordPress Playground; not part of the plugin.
 *
 * @package Coverfold
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A test script, not plugin code.

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$coverfold_lines  = array();
$coverfold_errors = array();
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Counts notices.
	static function ( $number, $message, $file, $line ) use ( &$coverfold_errors ) {
		$coverfold_errors[] = ( false !== strpos( $file, '/wp-content/plugins/' ) ? 'PLUGIN ' : 'CORE   ' ) . "$number $message @ $file:$line";
		return false;
	}
);
wp_set_current_user( 1 );

// No real requests to video services: record which hosts the plugin would contact.
$coverfold_http = array();
add_filter(
	'pre_http_request',
	static function ( $response, $args, $url ) use ( &$coverfold_http ) {
		$coverfold_http[] = (string) wp_parse_url( $url, PHP_URL_HOST );
		return new WP_Error( 'presubmit', 'Blocked by the pre-submission check.' );
	},
	10,
	3
);

$coverfold_lines[] = 'active=' . ( is_plugin_active( PRESUBMIT_PLUGIN ) ? 'yes' : 'no' )
	. ' db_version=' . (string) get_option( 'coverfold_db_version', 'none' );

// Two categories, two albums with images, one with a video link.
$coverfold_image = wp_upload_bits( 'presubmit.png', null, (string) file_get_contents( ABSPATH . 'wp-admin/images/w-logo-blue.png' ) );
$coverfold_media = array();
for ( $coverfold_i = 1; $coverfold_i <= 2; $coverfold_i++ ) {
	$coverfold_media[] = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Image ' . $coverfold_i,
			'post_excerpt'   => 'Caption <em>' . $coverfold_i . '</em> & more',
			'post_status'    => 'inherit',
		),
		$coverfold_image['file']
	);
}
$coverfold_albums = array();
foreach ( array( 'Weddings', 'Travel & Trips' ) as $coverfold_index => $coverfold_name ) {
	$coverfold_term     = wp_insert_term( $coverfold_name, Coverfold_Post_Type::TAXONOMY );
	$coverfold_album    = wp_insert_post(
		array(
			'post_type'   => Coverfold_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $coverfold_name . ' album',
		)
	);
	$coverfold_albums[] = $coverfold_album;
	wp_set_object_terms( $coverfold_album, array( (int) $coverfold_term['term_id'] ), Coverfold_Post_Type::TAXONOMY );
	set_post_thumbnail( $coverfold_album, $coverfold_media[0] );
	update_post_meta( $coverfold_album, Coverfold_Album::META_MEDIA, implode( ',', $coverfold_media ) );
	if ( 0 === $coverfold_index ) {
		update_post_meta( $coverfold_album, Coverfold_Album::META_VIDEOS, "https://www.youtube.com/watch?v=aqz-KE-bpKQ\nhttps://vimeo.com/76979871" );
	}
}

// Front end: the block, the shortcode and the old Album Station shortcode.
$coverfold_html = do_blocks( '<!-- wp:coverfold/album-grid {"filters":true,"showCount":true} /-->' )
	. do_shortcode( '[coverfold filters="yes" count="yes"]' )
	. do_shortcode( '[feature_album]' );
file_put_contents( '/sp/render-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.html', $coverfold_html );
$coverfold_lines[] = 'render_bytes=' . strlen( $coverfold_html )
	. ' grids=' . substr_count( $coverfold_html, 'coverfold-grid' )
	. ' cards=' . preg_match_all( '/class="[^"]*\bcoverfold-item\b/', $coverfold_html )
	. ' filters=' . substr_count( $coverfold_html, 'class="coverfold-filter' )
	. ' legacy_shortcode=' . ( shortcode_exists( 'feature_album' ) ? 'registered' : 'not-registered' )
	. ' inline_style_or_script=' . ( preg_match( '/<(style|script)\b/i', $coverfold_html ) ? 'yes' : 'no' );
$coverfold_lines[] = 'front_assets=' . implode( ',', array_values( array_filter( array_merge( wp_scripts()->queue, wp_styles()->queue ), static fn( $h ) => 0 === strpos( $h, 'coverfold' ) || 0 === strpos( $h, 'foldbox' ) ) ) );

// Admin: the settings page, then the dashboard (not the plugin's screen).
// Outside a real admin request core's get_admin_page_title() may raise a
// deprecation on PHP 8.1+ (counted as core_notices, not the plugin's).
$GLOBALS['plugin_page'] = Coverfold_Settings::PAGE;
$GLOBALS['title']       = 'Settings';

Coverfold_Settings::register();
ob_start();
Coverfold_Settings::render_page();
$coverfold_page    = (string) ob_get_clean();
$coverfold_lines[] = 'settings_page_bytes=' . strlen( $coverfold_page )
	. ' nonce=' . ( false !== strpos( $coverfold_page, '_wpnonce' ) ? 'yes' : 'no' );

set_current_screen( 'dashboard' );
$coverfold_queued = array_merge( wp_scripts()->queue, wp_styles()->queue ); // The front end above queued its assets.
do_action( 'admin_enqueue_scripts', 'index.php' );
$coverfold_assets = array();
foreach ( array( wp_scripts(), wp_styles() ) as $coverfold_deps ) {
	foreach ( array_diff( $coverfold_deps->queue, $coverfold_queued ) as $coverfold_handle ) {
		$coverfold_src = isset( $coverfold_deps->registered[ $coverfold_handle ] ) ? (string) $coverfold_deps->registered[ $coverfold_handle ]->src : '';
		if ( false !== strpos( $coverfold_src, '/plugins/' . dirname( PRESUBMIT_PLUGIN ) . '/' ) ) {
			$coverfold_assets[] = $coverfold_handle;
		}
	}
}
ob_start();
do_action( 'admin_notices' );
$coverfold_lines[] = 'dashboard_plugin_assets=' . ( $coverfold_assets ? implode( ',', $coverfold_assets ) : 'none' )
	. ' dashboard_notice_bytes=' . strlen( (string) ob_get_clean() );

// Deactivate with a video refresh queued: its cron event must go.
wp_schedule_single_event( time() + HOUR_IN_SECONDS, Coverfold_Videos::REFRESH_HOOK, array( 'youtube', 'presubmit' ) );
$coverfold_cron_before = wp_next_scheduled( Coverfold_Videos::REFRESH_HOOK, array( 'youtube', 'presubmit' ) ) ? 'yes' : 'no';
deactivate_plugins( PRESUBMIT_PLUGIN );
$coverfold_lines[] = 'cron_before_deactivate=' . $coverfold_cron_before
	. ' cron_after_deactivate=' . ( wp_next_scheduled( Coverfold_Videos::REFRESH_HOOK, array( 'youtube', 'presubmit' ) ) ? 'yes' : 'no' );

// Uninstall: settings, cron and cached video data go; albums are the user's content and stay.
update_option( Coverfold_Settings::OPTION, array( 'youtube_api_key' => 'presubmit' ) );
set_transient( 'coverfold_1_presubmit', array( 'cached' => true ), HOUR_IN_SECONDS );
ob_start();
uninstall_plugin( PRESUBMIT_PLUGIN );
$coverfold_uninstall_output = (string) ob_get_clean();
global $wpdb;
$coverfold_options_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 'coverfold%', '_transient%coverfold%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Checks the rows are gone.
$coverfold_lines[]      = 'uninstall_output_bytes=' . strlen( $coverfold_uninstall_output )
	. ' options_left=' . $coverfold_options_left
	. ' albums_kept=' . count( array_filter( array_map( 'get_post', $coverfold_albums ) ) );

$coverfold_lines[] = 'http_hosts=' . ( $coverfold_http ? implode( ',', array_unique( $coverfold_http ) ) : 'none' );

$coverfold_plugin_notices = count( preg_grep( '/^PLUGIN /', $coverfold_errors ) );
$coverfold_lines[]        = 'plugin_notices=' . $coverfold_plugin_notices . ' core_notices=' . ( count( $coverfold_errors ) - $coverfold_plugin_notices );
$coverfold_lines          = array_merge( $coverfold_lines, $coverfold_errors );
file_put_contents( '/sp/runtime-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.txt', implode( "\n", $coverfold_lines ) . "\n", FILE_APPEND );
