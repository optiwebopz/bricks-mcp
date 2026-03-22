<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package BricksMCP
 */

declare(strict_types=1);

// Define testing constant.
define( 'BRICKS_MCP_TESTING', true );

// Get the WordPress test suite directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if the WordPress test suite is available.
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	// Fall back to simple unit tests without WordPress.
	require_once __DIR__ . '/bootstrap-simple.php';
	return;
}

// Load WordPress test functions.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin.
 *
 * @return void
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/bricks-mcp.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WordPress test environment.
require "{$_tests_dir}/includes/bootstrap.php";
