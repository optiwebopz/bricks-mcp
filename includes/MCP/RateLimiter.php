<?php
/**
 * Atomic rate limiter for MCP endpoints.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\MCP;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RateLimiter class.
 *
 * Provides atomic per-user rate limiting using wp_cache_add + wp_cache_incr.
 * On persistent object cache backends (Redis, Memcached), wp_cache_incr is
 * atomic, closing the TOCTOU race condition present in get_transient/set_transient.
 * On sites without persistent object cache, the counter resets per request
 * (in-process only), which is equivalent to the previous transient approach
 * under high concurrency.
 */
final class RateLimiter {

	/**
	 * Cache group for rate limit counters.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'bricks_mcp';

	/**
	 * Rate limit window in seconds.
	 *
	 * @var int
	 */
	private const WINDOW = 60;

	/**
	 * Check whether the given identifier is within the rate limit.
	 *
	 * Uses wp_cache_add to atomically initialize the counter only when it does
	 * not exist, then wp_cache_incr to atomically increment. On Redis/Memcached
	 * backends both operations are atomic, eliminating the race condition.
	 *
	 * @param string $identifier The rate limit identifier (e.g. 'user_42' or 'ip_1.2.3.4').
	 * @return true|\WP_Error True if within limit, WP_Error with status 429 if exceeded.
	 */
	public static function check( string $identifier ): true|\WP_Error {
		$settings = get_option( 'bricks_mcp_settings', [] );
		$limit    = (int) ( $settings['rate_limit_rpm'] ?? 120 );
		$key      = 'rl_' . $identifier;

		// Initialize counter only if it does not already exist (atomic on persistent cache).
		wp_cache_add( $key, 0, self::CACHE_GROUP, self::WINDOW );

		// Atomically increment and get the new count.
		$count = wp_cache_incr( $key, 1, self::CACHE_GROUP );

		if ( false === $count || (int) $count > $limit ) {
			header( 'Retry-After: ' . self::WINDOW );

			return new \WP_Error(
				'bricks_mcp_rate_limit',
				__( 'Rate limit exceeded. Try again later.', 'bricks-mcp' ),
				[ 'status' => 429 ]
			);
		}

		return true;
	}
}
