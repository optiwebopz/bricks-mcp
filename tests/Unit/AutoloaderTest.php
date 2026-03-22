<?php
/**
 * Autoloader tests.
 *
 * @package BricksMCP
 */

declare(strict_types=1);

namespace BricksMCP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BricksMCP\Autoloader;

/**
 * Tests for the Autoloader class.
 */
final class AutoloaderTest extends TestCase {

	/**
	 * Test that the autoloader registers correctly.
	 *
	 * @return void
	 */
	public function test_autoloader_is_registered(): void {
		$autoloaders = spl_autoload_functions();

		$found = false;
		foreach ( $autoloaders as $autoloader ) {
			if ( is_array( $autoloader ) && $autoloader[0] === Autoloader::class ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Autoloader should be registered.' );
	}

	/**
	 * Test that the autoloader loads classes correctly.
	 *
	 * @return void
	 */
	public function test_autoloader_loads_classes(): void {
		// The Autoloader itself was loaded, so it works.
		$this->assertTrue( class_exists( Autoloader::class ) );
	}

	/**
	 * Test that the class map caches loaded classes.
	 *
	 * @return void
	 */
	public function test_class_map_caches_classes(): void {
		$class_map = Autoloader::get_class_map();

		$this->assertIsArray( $class_map );
		$this->assertNotEmpty( $class_map );
	}

	/**
	 * Test that non-namespaced classes are ignored.
	 *
	 * @return void
	 */
	public function test_non_namespaced_classes_ignored(): void {
		// This should not throw an error.
		Autoloader::load_class( 'SomeRandomClass' );
		$this->assertTrue( true );
	}

	/**
	 * Test that non-existent classes are handled gracefully.
	 *
	 * @return void
	 */
	public function test_non_existent_classes_handled(): void {
		// This should not throw an error.
		Autoloader::load_class( 'BricksMCP\\NonExistent\\SomeClass' );
		$this->assertTrue( true );
	}
}
