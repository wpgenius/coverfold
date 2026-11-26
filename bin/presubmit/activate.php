<?php
/**
 * Pre-submission check, step 1: activate the plugin in its own request and
 * record any output (WordPress shows "unexpected output" for it).
 *
 * Run by bin/presubmit/run.sh inside WordPress Playground; not part of the plugin.
 *
 * @package Coverfold
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Writes its results to the mounted folder.

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

ob_start();
$coverfold_result = activate_plugin( PRESUBMIT_PLUGIN );
$coverfold_output = (string) ob_get_clean();

file_put_contents(
	'/sp/runtime-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.txt',
	'php=' . PHP_VERSION . ' wp=' . get_bloginfo( 'version' ) . "\n"
	. 'activate=' . ( is_wp_error( $coverfold_result ) ? $coverfold_result->get_error_message() : 'ok' )
	. ' output_bytes=' . strlen( $coverfold_output ) . "\n"
);
