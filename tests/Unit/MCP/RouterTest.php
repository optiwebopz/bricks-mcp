<?php
/**
 * Router unit tests.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use BricksMCP\MCP\Router;

/**
 * Tests for the Router class — get_posts parameter allowlisting.
 *
 * WordPress function stubs are provided by tests/stubs/wp-functions.php
 * (loaded via bootstrap-simple.php) in the global namespace.
 */
final class RouterTest extends TestCase {

	private Router $router;

	/**
	 * Reset state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_bricks_mcp_test_current_user_can']  = true;
		$GLOBALS['_bricks_mcp_test_get_posts_return']   = [];
		$GLOBALS['_bricks_mcp_test_last_get_posts_args'] = [];

		$this->router = new Router();
	}

	/**
	 * Reset globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['_bricks_mcp_test_current_user_can'],
			$GLOBALS['_bricks_mcp_test_get_posts_return'],
			$GLOBALS['_bricks_mcp_test_last_get_posts_args']
		);

		parent::tearDown();
	}

	/**
	 * Helper to call tool_get_posts via the public tool_wordpress dispatcher.
	 *
	 * @param array<string, mixed> $extra_args Extra arguments to merge with defaults.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function call_get_posts( array $extra_args = [] ): array|\WP_Error {
		return $this->router->tool_wordpress( array_merge( [ 'action' => 'get_posts' ], $extra_args ) );
	}

	/**
	 * Get the arguments that were passed to WP get_posts().
	 *
	 * @return array<string, mixed>
	 */
	private function get_captured_wp_query_args(): array {
		return $GLOBALS['_bricks_mcp_test_last_get_posts_args'] ?? [];
	}

	/**
	 * post_status must always be 'publish' even when attacker passes it.
	 */
	public function test_post_status_locked_to_publish(): void {
		$this->call_get_posts( [ 'post_status' => 'draft' ] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 'publish', $captured['post_status'] );
	}

	/**
	 * post_status 'any' must not override the publish default.
	 */
	public function test_post_status_any_blocked(): void {
		$this->call_get_posts( [ 'post_status' => 'any' ] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 'publish', $captured['post_status'] );
	}

	/**
	 * Dangerous WP_Query params must NOT pass through to the query.
	 */
	public function test_disallowed_params_stripped(): void {
		$this->call_get_posts( [
			'meta_key'     => 'secret_field',
			'meta_value'   => 'leaked',
			'meta_query'   => [ [ 'key' => 'x', 'value' => 'y' ] ],
			'tax_query'    => [ [ 'taxonomy' => 'hidden' ] ],
			'cache_results' => false,
		] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertArrayNotHasKey( 'meta_key', $captured );
		$this->assertArrayNotHasKey( 'meta_value', $captured );
		$this->assertArrayNotHasKey( 'meta_query', $captured );
		$this->assertArrayNotHasKey( 'tax_query', $captured );
		$this->assertArrayNotHasKey( 'cache_results', $captured );
	}

	/**
	 * Allowed params pass through with proper sanitization.
	 */
	public function test_allowed_params_pass_through(): void {
		$this->call_get_posts( [
			'post_type'      => 'page',
			'posts_per_page' => 5,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => 'hello',
			'paged'          => 2,
			'category_name'  => 'news',
			'tag'            => 'featured',
			'author'         => 3,
		] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 'page', $captured['post_type'] );
		$this->assertSame( 5, $captured['posts_per_page'] );
		$this->assertSame( 'title', $captured['orderby'] );
		$this->assertSame( 'ASC', $captured['order'] );
		$this->assertSame( 'hello', $captured['s'] );
		$this->assertSame( 2, $captured['paged'] );
		$this->assertSame( 'news', $captured['category_name'] );
		$this->assertSame( 'featured', $captured['tag'] );
		$this->assertSame( 3, $captured['author'] );
		$this->assertSame( 'publish', $captured['post_status'] );
	}

	/**
	 * posts_per_page is capped at 100.
	 */
	public function test_posts_per_page_capped_at_100(): void {
		$this->call_get_posts( [ 'posts_per_page' => 999 ] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 100, $captured['posts_per_page'] );
	}

	/**
	 * order only accepts ASC or DESC, defaults to DESC.
	 */
	public function test_order_only_accepts_asc_or_desc(): void {
		$this->call_get_posts( [ 'order' => 'RAND' ] );

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 'DESC', $captured['order'] );
	}

	/**
	 * Default values are correct when no args provided.
	 */
	public function test_defaults_when_no_args(): void {
		$this->call_get_posts();

		$captured = $this->get_captured_wp_query_args();
		$this->assertSame( 'post', $captured['post_type'] );
		$this->assertSame( 10, $captured['posts_per_page'] );
		$this->assertSame( 'date', $captured['orderby'] );
		$this->assertSame( 'DESC', $captured['order'] );
		$this->assertSame( 'publish', $captured['post_status'] );
	}
}
