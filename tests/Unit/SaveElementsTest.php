<?php
/**
 * Tests for BricksService::save_elements() robustness.
 *
 * Verifies cache-clearing, fallback write, and post-write verification
 * logic added to fix OPS-172 (silent save failures).
 *
 * @package BricksMCP
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for save_elements() failure modes.
 *
 * These tests verify the structural behavior of the save_elements() method
 * by inspecting the source code for required patterns. Full integration
 * testing is done via live MCP API calls in Task 2.
 */
final class SaveElementsTest extends TestCase {

	/**
	 * Path to BricksService.php.
	 *
	 * @var string
	 */
	private string $service_path;

	/**
	 * Source code of BricksService.php.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service_path = dirname( __DIR__, 2 ) . '/includes/MCP/Services/BricksService.php';
		$this->assertFileExists( $this->service_path, 'BricksService.php must exist' );
		$this->source = (string) file_get_contents( $this->service_path );
	}

	/**
	 * Test that save_elements() clears the post meta object cache before writing.
	 *
	 * @return void
	 */
	public function test_save_elements_clears_cache_before_write(): void {
		// wp_cache_delete( must appear in save_elements before update_post_meta(.
		// Use function-call syntax to avoid matching docblock text.
		$save_start = strpos( $this->source, 'function save_elements(' );
		$this->assertNotFalse( $save_start, 'save_elements method must exist' );

		$save_body = substr( $this->source, $save_start, 1500 );

		$cache_pos  = strpos( $save_body, 'wp_cache_delete(' );
		$update_pos = strpos( $save_body, 'update_post_meta(' );

		$this->assertNotFalse( $cache_pos, 'save_elements must call wp_cache_delete()' );
		$this->assertNotFalse( $update_pos, 'save_elements must call update_post_meta()' );
		$this->assertLessThan( $update_pos, $cache_pos, 'wp_cache_delete() must come before update_post_meta()' );
	}

	/**
	 * Test that save_elements() has a fallback delete+add path when update returns false.
	 *
	 * @return void
	 */
	public function test_save_elements_has_delete_add_fallback(): void {
		$save_start = strpos( $this->source, 'function save_elements(' );
		$save_body  = substr( $this->source, $save_start, 1500 );

		$this->assertStringContainsString( 'delete_post_meta', $save_body, 'save_elements must have delete_post_meta fallback' );
		$this->assertStringContainsString( 'add_post_meta', $save_body, 'save_elements must have add_post_meta fallback' );

		// The fallback should be conditional on update_post_meta returning false.
		$this->assertMatchesRegularExpression(
			'/false\s*===\s*\$updated|if\s*\(\s*false\s*===\s*\$updated/',
			$save_body,
			'Fallback must be conditioned on update_post_meta returning false'
		);
	}

	/**
	 * Test that save_elements() verifies the write via read-back.
	 *
	 * @return void
	 */
	public function test_save_elements_verifies_write_via_readback(): void {
		$save_start = strpos( $this->source, 'function save_elements(' );
		$save_body  = substr( $this->source, $save_start, 2000 );

		// Must read back via get_post_meta after write.
		$this->assertStringContainsString( 'get_post_meta', $save_body, 'save_elements must read back via get_post_meta for verification' );

		// Must clear cache before verification read.
		$cache_positions = [];
		$offset          = 0;
		while ( false !== ( $pos = strpos( $save_body, 'wp_cache_delete', $offset ) ) ) {
			$cache_positions[] = $pos;
			$offset            = $pos + 1;
		}
		$this->assertGreaterThanOrEqual( 2, count( $cache_positions ), 'save_elements must call wp_cache_delete at least twice (before write and before verification read)' );
	}

	/**
	 * Test that save_elements() returns WP_Error on verification failure.
	 *
	 * @return void
	 */
	public function test_save_elements_returns_wp_error_on_verification_failure(): void {
		$save_start = strpos( $this->source, 'function save_elements(' );
		$save_body  = substr( $this->source, $save_start, 2000 );

		$this->assertStringContainsString( 'save_elements_failed', $save_body, 'save_elements must return WP_Error with code save_elements_failed on verification failure' );
	}
}
