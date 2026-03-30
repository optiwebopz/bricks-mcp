<?php
/**
 * Response tests.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the Response class.
 *
 * Note: These tests require the WordPress test environment.
 * They are skipped when running in simple mode.
 */
final class ResponseTest extends TestCase {

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Skip if WordPress is not loaded.
		if ( ! class_exists( 'WP_REST_Response' ) ) {
			$this->markTestSkipped( 'WordPress test environment not available.' );
		}
	}

	/**
	 * Test that success response is created correctly.
	 *
	 * @return void
	 */
	public function test_success_response(): void {
		$data     = [ 'test' => 'data' ];
		$response = \BricksMCP\MCP\Response::success( $data );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $data, $response->get_data() );
	}

	/**
	 * Test that error response is created correctly.
	 *
	 * @return void
	 */
	public function test_error_response(): void {
		$response = \BricksMCP\MCP\Response::error( 'test_error', 'Test error message', 400 );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['error'] );
		$this->assertEquals( 'test_error', $data['details']['code'] );
	}

	/**
	 * Test that error guidance is included only when provided.
	 *
	 * @return void
	 */
	public function test_error_response_includes_guidance_when_present(): void {
		$response = \BricksMCP\MCP\Response::error(
			'test_error',
			'Test error message',
			400,
			null,
			'Retry with a valid tool name.'
		);

		$data = $response->get_data();
		$this->assertSame( 'Retry with a valid tool name.', $data['details']['guidance'] );
	}

	/**
	 * Test that tool error response is created correctly with data.
	 *
	 * @return void
	 */
	public function test_tool_error_response(): void {
		$error    = new \WP_Error( 'test_code', 'Test error message', [ 'status' => 404 ] );
		$response = \BricksMCP\MCP\Response::tool_error( $error );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['isError'] );
		$this->assertIsArray( $data['content'] );
		$this->assertCount( 1, $data['content'] );
		$this->assertEquals( 'text', $data['content'][0]['type'] );

		$payload = json_decode( $data['content'][0]['text'], true );
		$this->assertEquals( 'test_code', $payload['code'] );
		$this->assertEquals( 'Test error message', $payload['message'] );
		$this->assertEquals( [ 'status' => 404 ], $payload['data'] );
	}

	/**
	 * Test that tool error response omits data key when not present.
	 *
	 * @return void
	 */
	public function test_tool_error_response_without_data(): void {
		$error    = new \WP_Error( 'no_data_code', 'Error without data' );
		$response = \BricksMCP\MCP\Response::tool_error( $error );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data    = $response->get_data();
		$payload = json_decode( $data['content'][0]['text'], true );
		$this->assertEquals( 'no_data_code', $payload['code'] );
		$this->assertEquals( 'Error without data', $payload['message'] );
		$this->assertArrayNotHasKey( 'data', $payload );
	}

	/**
	 * Test that tool error guidance is included only when provided.
	 *
	 * @return void
	 */
	public function test_tool_error_response_includes_guidance_when_present(): void {
		$error    = new \WP_Error( 'guided_code', 'Guided error' );
		$response = \BricksMCP\MCP\Response::tool_error( $error, 'Check the schema and retry.' );

		$data    = $response->get_data();
		$payload = json_decode( $data['content'][0]['text'], true );
		$this->assertSame( 'Check the schema and retry.', $payload['guidance'] );
	}
}
