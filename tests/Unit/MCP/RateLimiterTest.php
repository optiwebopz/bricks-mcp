<?php
/**
 * RateLimiter unit tests.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use BricksMCP\MCP\RateLimiter;

/**
 * Tests for the RateLimiter class.
 *
 * WordPress function stubs are provided by tests/stubs/wp-functions.php
 * (loaded via bootstrap-simple.php) in the global namespace. Tests control
 * stub behavior through $GLOBALS arrays reset in setUp()/tearDown().
 */
final class RateLimiterTest extends TestCase {

	/**
	 * Unique identifier prefix to avoid collisions between tests.
	 *
	 * @var string
	 */
	private string $test_id;

	/**
	 * Reset state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->test_id = uniqid( 'test_', true );

		$GLOBALS['_bricks_mcp_test_cache']            = [];
		$GLOBALS['_bricks_mcp_test_settings']         = [];
		$GLOBALS['_bricks_mcp_test_ext_object_cache'] = true;
		$GLOBALS['_bricks_mcp_test_transients']       = [];
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();

		$GLOBALS['_bricks_mcp_test_cache']            = [];
		$GLOBALS['_bricks_mcp_test_settings']         = [];
		$GLOBALS['_bricks_mcp_test_ext_object_cache'] = true;
		$GLOBALS['_bricks_mcp_test_transients']       = [];
	}

	/**
	 * Helper: set rate limit RPM.
	 *
	 * @param int $rpm Requests per minute limit.
	 * @return void
	 */
	private function set_rate_limit( int $rpm ): void {
		$GLOBALS['_bricks_mcp_test_settings'] = [ 'rate_limit_rpm' => $rpm ];
	}

	/**
	 * Helper: force transient fallback path.
	 *
	 * @return void
	 */
	private function use_transient_path(): void {
		$GLOBALS['_bricks_mcp_test_ext_object_cache'] = false;
	}

	/**
	 * Helper: force persistent cache path.
	 *
	 * @return void
	 */
	private function use_cache_path(): void {
		$GLOBALS['_bricks_mcp_test_ext_object_cache'] = true;
	}

	// -----------------------------------------------------------------------
	// Persistent object cache path tests.
	// -----------------------------------------------------------------------

	/**
	 * Test: check() with an IP-based identifier returns true when under limit (persistent cache path).
	 *
	 * @return void
	 */
	public function test_cache_path_ip_identifier_under_limit_returns_true(): void {
		$this->use_cache_path();

		$result = RateLimiter::check( 'ip_192.168.1.1_' . $this->test_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test: check() with a user-based identifier returns true when under limit (persistent cache path).
	 *
	 * @return void
	 */
	public function test_cache_path_user_identifier_under_limit_returns_true(): void {
		$this->use_cache_path();

		$result = RateLimiter::check( 'user_42_' . $this->test_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test: check() returns WP_Error with status 429 after exceeding rate_limit_rpm (persistent cache path).
	 *
	 * @return void
	 */
	public function test_cache_path_exceeds_limit_returns_wp_error_429(): void {
		$this->use_cache_path();
		$this->set_rate_limit( 2 );

		$id = 'ip_10.0.0.1_' . $this->test_id;

		$this->assertTrue( RateLimiter::check( $id ) );
		$this->assertTrue( RateLimiter::check( $id ) );

		$result = RateLimiter::check( $id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'bricks_mcp_rate_limit', $result->get_error_code() );
		$this->assertSame( [ 'status' => 429 ], $result->get_error_data() );
	}

	/**
	 * Test: Different identifiers maintain independent counters (persistent cache path).
	 *
	 * @return void
	 */
	public function test_cache_path_independent_counters(): void {
		$this->use_cache_path();
		$this->set_rate_limit( 1 );

		$id_a = 'ip_1.2.3.4_' . $this->test_id;
		$id_b = 'user_99_' . $this->test_id;

		RateLimiter::check( $id_a );
		$this->assertInstanceOf( \WP_Error::class, RateLimiter::check( $id_a ), 'id_a should be rate-limited' );
		$this->assertTrue( RateLimiter::check( $id_b ), 'id_b should not be affected by id_a limit' );
	}

	/**
	 * Test: Persistent cache path does NOT write transients.
	 *
	 * @return void
	 */
	public function test_cache_path_does_not_write_transients(): void {
		$this->use_cache_path();

		RateLimiter::check( 'user_50_' . $this->test_id );

		$this->assertEmpty(
			$GLOBALS['_bricks_mcp_test_transients'],
			'Transients must not be written when persistent object cache is active'
		);
	}

	// -----------------------------------------------------------------------
	// Transient fallback path tests.
	// -----------------------------------------------------------------------

	/**
	 * Test: Transient path returns true when under limit.
	 *
	 * @return void
	 */
	public function test_transient_path_under_limit_returns_true(): void {
		$this->use_transient_path();

		$id     = 'user_10_' . $this->test_id;
		$result = RateLimiter::check( $id );

		$this->assertTrue( $result );

		// Verify transient was written.
		$this->assertSame( 1, $GLOBALS['_bricks_mcp_test_transients'][ 'bricks_mcp_rl_' . $id ] ?? null );
	}

	/**
	 * Test: Transient path increments count across calls.
	 *
	 * @return void
	 */
	public function test_transient_path_increments_counter(): void {
		$this->use_transient_path();

		$id = 'user_11_' . $this->test_id;

		RateLimiter::check( $id );
		RateLimiter::check( $id );
		RateLimiter::check( $id );

		$this->assertSame( 3, $GLOBALS['_bricks_mcp_test_transients'][ 'bricks_mcp_rl_' . $id ] ?? null );
	}

	/**
	 * Test: Transient path returns WP_Error 429 after exceeding limit.
	 *
	 * @return void
	 */
	public function test_transient_path_exceeds_limit_returns_wp_error_429(): void {
		$this->use_transient_path();
		$this->set_rate_limit( 2 );

		$id = 'user_20_' . $this->test_id;

		$this->assertTrue( RateLimiter::check( $id ) );
		$this->assertTrue( RateLimiter::check( $id ) );

		$result = RateLimiter::check( $id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'bricks_mcp_rate_limit', $result->get_error_code() );
		$this->assertSame( [ 'status' => 429 ], $result->get_error_data() );
	}

	/**
	 * Test: Transient path maintains independent counters per identifier.
	 *
	 * @return void
	 */
	public function test_transient_path_independent_counters(): void {
		$this->use_transient_path();
		$this->set_rate_limit( 1 );

		$id_a = 'user_30_' . $this->test_id;
		$id_b = 'user_31_' . $this->test_id;

		RateLimiter::check( $id_a );
		$this->assertInstanceOf( \WP_Error::class, RateLimiter::check( $id_a ), 'id_a should be rate-limited' );
		$this->assertTrue( RateLimiter::check( $id_b ), 'id_b should not be affected by id_a limit' );
	}

	/**
	 * Test: Transient path does NOT write to object cache.
	 *
	 * @return void
	 */
	public function test_transient_path_does_not_write_to_object_cache(): void {
		$this->use_transient_path();

		RateLimiter::check( 'user_60_' . $this->test_id );

		$this->assertEmpty(
			$GLOBALS['_bricks_mcp_test_cache'],
			'Object cache must not be written when using transient fallback'
		);
	}
}
