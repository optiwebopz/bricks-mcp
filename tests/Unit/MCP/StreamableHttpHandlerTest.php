<?php
/**
 * StreamableHttpHandler unit tests.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use BricksMCP\MCP\StreamableHttpHandler;

/**
 * Tests for the StreamableHttpHandler class.
 *
 * Focuses on verifiable class-level behavior: constants and JSON-RPC error
 * response shape. The handle_post() path calls exit() so cannot be tested
 * directly in unit tests without process isolation.
 */
final class StreamableHttpHandlerTest extends TestCase {

	/**
	 * Test: MAX_BATCH_SIZE constant equals 20.
	 *
	 * @return void
	 */
	public function test_max_batch_size_constant(): void {
		$this->assertSame( 20, StreamableHttpHandler::MAX_BATCH_SIZE );
	}

	/**
	 * Test: jsonrpc_error returns correct shape for batch-too-large error.
	 *
	 * Verifies that the private jsonrpc_error() helper produces the expected
	 * JSON-RPC 2.0 error response for the batch size limit message.
	 *
	 * @return void
	 */
	public function test_jsonrpc_error_format_for_batch_too_large(): void {
		$ref     = new \ReflectionClass( StreamableHttpHandler::class );
		$handler = $ref->newInstanceWithoutConstructor();

		$method = new \ReflectionMethod( $handler, 'jsonrpc_error' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$handler,
			null,
			StreamableHttpHandler::INVALID_REQUEST,
			'Batch too large (max 20 messages)'
		);

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertNull( $result['id'] );
		$this->assertSame( -32600, $result['error']['code'] );
		$this->assertSame( 'Batch too large (max 20 messages)', $result['error']['message'] );
	}

	/**
	 * Test: INVALID_REQUEST constant equals -32600.
	 *
	 * @return void
	 */
	public function test_invalid_request_constant(): void {
		$this->assertSame( -32600, StreamableHttpHandler::INVALID_REQUEST );
	}

	/**
	 * Test: MAX_BODY_SIZE constant equals 1048576 (1 MB).
	 *
	 * @return void
	 */
	public function test_max_body_size_constant(): void {
		$this->assertSame( 1048576, StreamableHttpHandler::MAX_BODY_SIZE );
	}

	/**
	 * Test: jsonrpc_error returns correct shape for body-too-large error.
	 *
	 * Verifies that the private jsonrpc_error() helper produces the expected
	 * JSON-RPC 2.0 error response for the body size limit message.
	 *
	 * @return void
	 */
	public function test_jsonrpc_error_format_for_body_too_large(): void {
		$ref     = new \ReflectionClass( StreamableHttpHandler::class );
		$handler = $ref->newInstanceWithoutConstructor();

		$method = new \ReflectionMethod( $handler, 'jsonrpc_error' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$handler,
			null,
			StreamableHttpHandler::INVALID_REQUEST,
			'Request body too large'
		);

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertNull( $result['id'] );
		$this->assertSame( -32600, $result['error']['code'] );
		$this->assertSame( 'Request body too large', $result['error']['message'] );
	}

	// -------------------------------------------------------------------------
	// CONN-01 through CONN-04 source-assertion tests (Phase 39).
	// These tests read the method source via ReflectionMethod and assert that
	// the required function calls and patterns are present. This pattern is used
	// because the methods call exit() and produce output, which cannot be tested
	// directly under beStrictAboutOutputDuringTests="true".
	// -------------------------------------------------------------------------

	/**
	 * Helper: extract source code of a method as a string.
	 *
	 * @param string $method_name Method name in StreamableHttpHandler.
	 * @return string The raw PHP source lines of the method.
	 */
	private function get_method_source( string $method_name ): string {
		$ref   = new \ReflectionMethod( StreamableHttpHandler::class, $method_name );
		$file  = file( (string) $ref->getFileName() );
		$start = $ref->getStartLine() - 1;
		$end   = $ref->getEndLine();
		return implode( '', array_slice( $file, $start, $end - $start ) );
	}

	/**
	 * Test: emit_sse_headers() source contains set_time_limit call (CONN-02).
	 *
	 * @return void
	 */
	public function test_emit_sse_headers_contains_set_time_limit(): void {
		$source = $this->get_method_source( 'emit_sse_headers' );
		$this->assertStringContainsString( 'set_time_limit', $source );
	}

	/**
	 * Test: emit_sse_headers() source contains ignore_user_abort( true ) (CONN-03).
	 *
	 * @return void
	 */
	public function test_emit_sse_headers_contains_ignore_user_abort(): void {
		$source = $this->get_method_source( 'emit_sse_headers' );
		$this->assertStringContainsString( 'ignore_user_abort( true )', $source );
	}

	/**
	 * Test: emit_sse_headers() source contains bricks_mcp_sse_timeout filter (CONN-02).
	 *
	 * @return void
	 */
	public function test_emit_sse_headers_contains_sse_timeout_filter(): void {
		$source = $this->get_method_source( 'emit_sse_headers' );
		$this->assertStringContainsString( 'bricks_mcp_sse_timeout', $source );
	}

	/**
	 * Test: emit_sse_headers() source contains register_shutdown_function (CONN-03).
	 *
	 * @return void
	 */
	public function test_emit_sse_headers_contains_register_shutdown_function(): void {
		$source = $this->get_method_source( 'emit_sse_headers' );
		$this->assertStringContainsString( 'register_shutdown_function', $source );
	}

	/**
	 * Test: handle_get() source contains keepalive SSE comment (CONN-01).
	 *
	 * @return void
	 */
	public function test_handle_get_contains_keepalive_comment(): void {
		$source = $this->get_method_source( 'handle_get' );
		$this->assertStringContainsString( 'keepalive', $source );
	}

	/**
	 * Test: handle_get() source contains connection_aborted() check (CONN-03).
	 *
	 * @return void
	 */
	public function test_handle_get_contains_connection_aborted_check(): void {
		$source = $this->get_method_source( 'handle_get' );
		$this->assertStringContainsString( 'connection_aborted()', $source );
	}

	/**
	 * Test: handle_get() source contains sleep( 25 ) for keepalive interval (CONN-01).
	 *
	 * @return void
	 */
	public function test_handle_get_contains_sleep_25(): void {
		$source = $this->get_method_source( 'handle_get' );
		$this->assertStringContainsString( 'sleep( 25 )', $source );
	}

	/**
	 * Test: handle_post() source contains bricks_mcp_max_body_size filter (CONN-04).
	 *
	 * @return void
	 */
	public function test_handle_post_contains_max_body_size_filter(): void {
		$source = $this->get_method_source( 'handle_post' );
		$this->assertStringContainsString( 'bricks_mcp_max_body_size', $source );
	}

	/**
	 * Test: handle_post() 413 block contains Connection: close header (CONN-04).
	 *
	 * @return void
	 */
	public function test_handle_post_413_has_connection_close(): void {
		$source = $this->get_method_source( 'handle_post' );
		$this->assertStringContainsString( 'Connection: close', $source );
	}

	/**
	 * Test: handle_post() contains Connection: close at least twice (413 and 415 blocks) (CONN-04).
	 *
	 * @return void
	 */
	public function test_handle_post_415_has_connection_close(): void {
		$source = $this->get_method_source( 'handle_post' );
		$count  = substr_count( $source, 'Connection: close' );
		$this->assertGreaterThanOrEqual( 2, $count, 'Expected Connection: close in both 413 and 415 blocks' );
	}

	/**
	 * Test: dispatch_single() supports MCP resources and prompts methods.
	 *
	 * @return void
	 */
	public function test_dispatch_single_contains_resources_and_prompts_methods(): void {
		$source = $this->get_method_source( 'dispatch_single' );
		$this->assertStringContainsString( "'resources/list'", $source );
		$this->assertStringContainsString( "'resources/read'", $source );
		$this->assertStringContainsString( "'prompts/list'", $source );
		$this->assertStringContainsString( "'prompts/get'", $source );
	}

	/**
	 * Test: handle_initialize() advertises resources and prompts capabilities.
	 *
	 * @return void
	 */
	public function test_handle_initialize_contains_resources_and_prompts_capabilities(): void {
		$source = $this->get_method_source( 'handle_initialize' );
		$this->assertStringContainsString( "'resources' => new \\stdClass()", $source );
		$this->assertStringContainsString( "'prompts'   => new \\stdClass()", $source );
	}
}
