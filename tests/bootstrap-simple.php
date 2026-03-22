<?php
/**
 * Simple bootstrap for unit tests without WordPress.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Define plugin constants for testing.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'BRICKS_MCP_VERSION', '1.1.2' );
define( 'BRICKS_MCP_MIN_PHP_VERSION', '8.2' );
define( 'BRICKS_MCP_MIN_WP_VERSION', '6.4' );
define( 'BRICKS_MCP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'BRICKS_MCP_PLUGIN_URL', 'http://localhost/wp-content/plugins/bricks-mcp/' );
define( 'BRICKS_MCP_PLUGIN_BASENAME', 'bricks-mcp/bricks-mcp.php' );

// Load the autoloader.
require_once BRICKS_MCP_PLUGIN_DIR . 'includes/Autoloader.php';
BricksMCP\Autoloader::register();
