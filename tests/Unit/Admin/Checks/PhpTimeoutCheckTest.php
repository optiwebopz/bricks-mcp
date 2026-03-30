<?php
/**
 * PhpTimeoutCheck unit tests.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit\Admin\Checks;

use PHPUnit\Framework\TestCase;
use BricksMCP\Admin\Checks\PhpTimeoutCheck;

/**
 * Testable subclass that overrides ini_get so we can control the return value.
 */
class TestablePhpTimeoutCheck extends PhpTimeoutCheck {

	/**
	 * Configurable max_execution_time value.
	 *
	 * @var string
	 */
	private string $fake_value;

	/**
	 * Constructor.
	 *
	 * @param string $fake_value The value to return from get_max_execution_time().
	 */
	public function __construct( string $fake_value ) {
		$this->fake_value = $fake_value;
	}

	/**
	 * Override to return the fake value instead of calling ini_get().
	 *
	 * @return string
	 */
	protected function get_max_execution_time(): string {
		return $this->fake_value;
	}
}

/**
 * Tests for PhpTimeoutCheck.
 */
final class PhpTimeoutCheckTest extends TestCase {

	/**
	 * Test 1: unlimited (0) returns pass with 'unlimited' in message.
	 *
	 * @return void
	 */
	public function test_unlimited_returns_pass(): void {
		$check  = new TestablePhpTimeoutCheck( '0' );
		$result = $check->run();

		$this->assertSame( 'pass', $result['status'] );
		$this->assertStringContainsString( 'unlimited', strtolower( $result['message'] ) );
	}

	/**
	 * Test 2: sufficient time (120s) returns pass with value in message.
	 *
	 * @return void
	 */
	public function test_sufficient_time_returns_pass(): void {
		$check  = new TestablePhpTimeoutCheck( '120' );
		$result = $check->run();

		$this->assertSame( 'pass', $result['status'] );
		$this->assertStringContainsString( '120', $result['message'] );
	}

	/**
	 * Test 3: exactly 60 seconds returns pass.
	 *
	 * @return void
	 */
	public function test_exactly_60_returns_pass(): void {
		$check  = new TestablePhpTimeoutCheck( '60' );
		$result = $check->run();

		$this->assertSame( 'pass', $result['status'] );
	}

	/**
	 * Test 4: too low (30s) returns warn with value in message and 2 fix_steps.
	 *
	 * @return void
	 */
	public function test_too_low_returns_warn(): void {
		$check  = new TestablePhpTimeoutCheck( '30' );
		$result = $check->run();

		$this->assertSame( 'warn', $result['status'] );
		$this->assertStringContainsString( '30', $result['message'] );
		$this->assertIsArray( $result['fix_steps'] );
		$this->assertCount( 2, $result['fix_steps'] );
	}

	/**
	 * Test 5: fix_steps content contains ini_set and php.ini references.
	 *
	 * @return void
	 */
	public function test_fix_steps_contain_expected_content(): void {
		$check  = new TestablePhpTimeoutCheck( '30' );
		$result = $check->run();

		$this->assertStringContainsString( 'ini_set', $result['fix_steps'][0] );
		$this->assertStringContainsString( 'php.ini', $result['fix_steps'][1] );
	}

	/**
	 * Test 6: identity methods return expected values.
	 *
	 * @return void
	 */
	public function test_identity_methods(): void {
		$check = new TestablePhpTimeoutCheck( '0' );

		$this->assertSame( 'php_timeout', $check->id() );
		$this->assertNotEmpty( $check->label() );
		$this->assertSame( 'compatibility', $check->category() );
		$this->assertSame( array(), $check->dependencies() );
	}
}
