<?php
/**
 * Unit test: CSS injection prevention in BricksService::update_page_css().
 *
 * Verifies that custom CSS storage is blocked when the dangerous_actions
 * toggle is not enabled, consistent with script handling.
 *
 * @package BricksMCP\Tests\Unit\MCP\Services
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit\MCP\Services;

use BricksMCP\MCP\Services\BricksService;
use PHPUnit\Framework\TestCase;

/**
 * Tests that update_page_css() requires dangerous_actions to be enabled.
 */
class BricksServiceCssInjectionTest extends TestCase {

	protected function setUp(): void {
		// Ensure dangerous_actions is disabled by default.
		unset( $GLOBALS['_bricks_mcp_test_settings'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_bricks_mcp_test_settings'] );
	}

	public function test_update_page_css_blocked_when_dangerous_actions_disabled(): void {
		// dangerous_actions not set (default) — CSS should be rejected.
		$service = new BricksService();
		$result  = $service->update_page_css( 1, 'body { color: red; }' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dangerous_actions_disabled', $result->get_error_code() );
	}

	public function test_update_page_css_blocked_when_dangerous_actions_explicitly_false(): void {
		$GLOBALS['_bricks_mcp_test_settings'] = array( 'dangerous_actions' => false );

		$service = new BricksService();
		$result  = $service->update_page_css( 1, '.inject { background: url(evil); }' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'dangerous_actions_disabled', $result->get_error_code() );
	}
}
