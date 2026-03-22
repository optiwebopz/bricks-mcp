<?php
/**
 * Plugin activation handler.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 *
 * Handles plugin activation tasks.
 */
final class Activator {

	/**
	 * Run activation tasks.
	 *
	 * This method is called when the plugin is activated.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Store activation timestamp.
		if ( ! get_option( 'bricks_mcp_activated_at' ) ) {
			update_option( 'bricks_mcp_activated_at', time() );
		}

		// Store plugin version.
		update_option( 'bricks_mcp_version', BRICKS_MCP_VERSION );

		// Set default options if they don't exist.
		self::set_default_options();

		// Flush rewrite rules for REST API endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'enabled'      => true,
			'require_auth' => true,
		];

		$existing = get_option( 'bricks_mcp_settings', [] );

		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		// Merge defaults with existing settings.
		$settings = array_merge( $defaults, $existing );

		update_option( 'bricks_mcp_settings', $settings );
	}
}
