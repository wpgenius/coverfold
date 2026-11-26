<?php
/**
 * Pre-submission check: Plugin Check with every static check, experimental
 * ones included, on the packaged plugin. Writes /sp/plugin-check.txt.
 *
 * Run by bin/presubmit/run.sh inside WordPress Playground; not part of the plugin.
 *
 * @package Coverfold
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Writes its results to the mounted folder.

require_once '/wordpress/wp-load.php';

$coverfold_lines = array();
try {
	$coverfold_repository = 'WordPress\Plugin_Check\Checker\Check_Repository';
	$coverfold_checks     = ( new WordPress\Plugin_Check\Checker\Default_Check_Repository() )->get_checks(
		constant( $coverfold_repository . '::TYPE_STATIC' ) | constant( $coverfold_repository . '::INCLUDE_EXPERIMENTAL' )
	);
	$coverfold_context    = new WordPress\Plugin_Check\Checker\Check_Context( WP_PLUGIN_DIR . '/' . PRESUBMIT_PLUGIN );
	$coverfold_result     = new WordPress\Plugin_Check\Checker\Check_Result( $coverfold_context );

	$coverfold_lines[] = 'checks: ' . implode( ', ', array_keys( iterator_to_array( $coverfold_checks ) ) );
	foreach ( $coverfold_checks as $coverfold_slug => $coverfold_check ) {
		try {
			$coverfold_check->run( $coverfold_result );
		} catch ( Throwable $coverfold_e ) {
			$coverfold_lines[] = "EXCEPTION $coverfold_slug: " . $coverfold_e->getMessage();
		}
	}

	$coverfold_found = array(
		'ERROR'   => $coverfold_result->get_errors(),
		'WARNING' => $coverfold_result->get_warnings(),
	);
	foreach ( $coverfold_found as $coverfold_type => $coverfold_files ) {
		foreach ( $coverfold_files as $coverfold_file => $coverfold_rows ) {
			foreach ( $coverfold_rows as $coverfold_line => $coverfold_columns ) {
				foreach ( $coverfold_columns as $coverfold_messages ) {
					foreach ( $coverfold_messages as $coverfold_message ) {
						$coverfold_lines[] = "$coverfold_type $coverfold_file:$coverfold_line {$coverfold_message['code']} " . wp_strip_all_tags( $coverfold_message['message'] );
					}
				}
			}
		}
	}
	$coverfold_lines[] = 'errors=' . $coverfold_result->get_error_count() . ' warnings=' . $coverfold_result->get_warning_count();
} catch ( Throwable $coverfold_e ) {
	$coverfold_lines[] = 'FATAL ' . get_class( $coverfold_e ) . ': ' . $coverfold_e->getMessage() . ' @ ' . $coverfold_e->getFile() . ':' . $coverfold_e->getLine();
}

file_put_contents( '/sp/plugin-check.txt', implode( "\n", $coverfold_lines ) . "\n" );
