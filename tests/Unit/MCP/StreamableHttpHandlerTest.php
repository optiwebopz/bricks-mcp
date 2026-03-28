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
}
