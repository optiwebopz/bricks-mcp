<?php
/**
 * Plugin deactivation handler.
 *
 * @package BricksMCP
 */

declare(strict_types=1);

namespace BricksMCP;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class.
 *
 * Handles plugin deactivation tasks.
 */
final class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * This method is called when the plugin is deactivated.
	 * Note: This should NOT delete plugin data. Use uninstall.php for that.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Flush rewrite rules to clean up REST API endpoints.
		flush_rewrite_rules();

		// Clear any scheduled cron events.
		self::clear_scheduled_events();

		// Clear any transients.
		self::clear_transients();
	}

	/**
	 * Clear scheduled cron events.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events(): void {
		$cron_hooks = [
			'bricks_mcp_cleanup',
		];

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Clear plugin transients.
	 *
	 * @return void
	 */
	private static function clear_transients(): void {
		global $wpdb;

		// Delete all transients with the plugin prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_bricks_mcp_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_bricks_mcp_' ) . '%'
			)
		);
	}
}
