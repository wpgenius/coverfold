<?php
/**
 * Editor script dependencies (the block editor script is plain JS, no build step).
 *
 * @package Coverfold
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-data', 'wp-core-data', 'wp-i18n' ),
	'version'      => COVERFOLD_VERSION,
);
