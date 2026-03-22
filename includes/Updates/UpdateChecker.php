<?php
/**
 * Plugin update checker.
 *
 * Hooks into WordPress 5.8+ Update URI system to check GitHub Releases
 * for new plugin versions.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Updates;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UpdateChecker class.
 *
 * Manages plugin update checking via the modern `update_plugins_{$hostname}` filter,
 * with transient-based caching.
 */
final class UpdateChecker {

	/**
	 * GitHub Releases API URL for the latest release.
	 *
	 * @var string
	 */
	private const GITHUB_API_URL = 'https://api.github.com/repos/cristianuibar/bricks-mcp/releases/latest';

	/**
	 * Transient key for cached update data.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'bricks_mcp_update_data';

	/**
	 * Cache TTL in seconds (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Initialize update checker hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Modern WP 5.8+ update hook (hostname extracted from Update URI header).
		add_filter( 'update_plugins_github.com', [ $this, 'check_update' ], 10, 4 );

		// AJAX handler for "Check Now" button on settings page.
		add_action( 'wp_ajax_bricks_mcp_check_update', [ $this, 'ajax_check_update' ] );

		// Plugin row notice when an update is available.
		add_action( 'after_plugin_row_' . BRICKS_MCP_PLUGIN_BASENAME, [ $this, 'render_update_notice' ], 10, 2 );
	}

	/**
	 * Check for plugin updates via the update_plugins_{$hostname} filter.
	 *
	 * @param mixed             $update      The update data. Default false.
	 * @param array<string,string> $plugin_data Plugin header data.
	 * @param string            $plugin_file Plugin file relative to plugins directory.
	 * @param array<string>     $locales     Installed locales.
	 * @return mixed Update data array if update available, original value otherwise.
	 */
	public function check_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
		$remote = $this->get_update_data();

		if ( empty( $remote['version'] ) ) {
			return $update;
		}

		// Only return update data if remote version is newer.
		if ( version_compare( $plugin_data['Version'], $remote['version'], '>=' ) ) {
			return $update;
		}

		return [
			'id'           => $plugin_data['UpdateURI'],
			'slug'         => 'bricks-mcp',
			'version'      => $remote['version'],
			'url'          => $remote['url'] ?? '',
			'package'      => $remote['package'] ?? '',
			'tested'       => $remote['tested'] ?? '',
			'requires_php' => $remote['requires_php'] ?? '8.2',
			'autoupdate'   => true,
		];
	}

	/**
	 * Get update data from cache or GitHub Releases API.
	 *
	 * Fetches the latest release from GitHub, extracts the version from
	 * `tag_name` (stripping a leading "v") and the ZIP download URL from
	 * `assets[0].browser_download_url`.
	 *
	 * @return array<string,mixed> Update data array.
	 */
	private function get_update_data(): array {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::GITHUB_API_URL,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Bricks-MCP-UpdateChecker/1.0',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache empty result briefly to avoid hammering on failure.
			set_transient( self::TRANSIENT_KEY, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		// Strip leading "v" from tag name (e.g. "v1.2.3" → "1.2.3").
		$version = ltrim( $release['tag_name'], 'v' );

		// First asset is expected to be the plugin ZIP.
		$package = '';
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			$package = $release['assets'][0]['browser_download_url'] ?? '';
		}

		$data = [
			'version' => $version,
			'package' => $package,
			'url'     => $release['html_url'] ?? '',
		];

		set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Clear the cached update data.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Get cached update data without triggering a remote fetch.
	 *
	 * Used by the Settings page version card to display update status.
	 *
	 * @return array<string,mixed> Cached update data, or empty array if no cache.
	 */
	public function get_cached_update_data(): array {
		$cached = get_transient( self::TRANSIENT_KEY );

		return false !== $cached ? $cached : [];
	}

	/**
	 * AJAX handler for "Check Now" button.
	 *
	 * Clears all caches and forces a fresh update check.
	 *
	 * @return void
	 */
	public function ajax_check_update(): void {
		check_ajax_referer( 'bricks_mcp_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'bricks-mcp' ) ], 403 );
		}

		// Clear our own cache.
		$this->clear_cache();

		// Clear WordPress core update cache.
		delete_site_transient( 'update_plugins' );

		// Force WordPress to re-check all plugin updates.
		wp_update_plugins();

		// Fetch fresh data.
		$data = $this->get_update_data();

		wp_send_json_success( $data );
	}

	/**
	 * Render notice below plugin row when an update is available.
	 *
	 * @param string               $plugin_file Plugin basename.
	 * @param array<string,string> $plugin_data Plugin header data.
	 * @return void
	 */
	public function render_update_notice( string $plugin_file, array $plugin_data ): void {
		$update_data = $this->get_cached_update_data();

		if ( empty( $update_data['version'] ) ) {
			return;
		}
		if ( version_compare( $plugin_data['Version'] ?? '0', $update_data['version'], '>=' ) ) {
			return;
		}

		// Standard WordPress plugin row notice structure.
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$colspan       = $wp_list_table->get_column_count();

		echo '<tr class="plugin-update-tr' . ( is_plugin_active( $plugin_file ) ? ' active' : '' ) . '">'
			. '<td colspan="' . esc_attr( (string) $colspan ) . '" class="plugin-update colspanchange">'
			. '<div class="update-message notice inline notice-warning notice-alt"><p>';

		printf(
			/* translators: 1: version number, 2: opening link tag, 3: closing link tag */
			esc_html__( 'Version %1$s is available. %2$sGo to Updates%3$s to install.', 'bricks-mcp' ),
			esc_html( $update_data['version'] ),
			'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
			'</a>'
		);

		echo '</p></div></td></tr>';
	}
}
