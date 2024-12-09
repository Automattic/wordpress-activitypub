<?php
/**
 * Tests for Autoloader class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Autoloader;

/**
 * Class Test_Autoloader.
 *
 * @coversDefaultClass \Activitypub\Autoloader
 */
class Test_Autoloader extends \WP_UnitTestCase {

	/**
	 * Test__construct.
	 *
	 * @covers ::__construct
	 */
	public function test__construct() {
		$autoloader = new Autoloader( 'Activitypub', __DIR__ );

		$prefix = new \ReflectionProperty( '\Activitypub\Autoloader', 'prefix' );
		$this->assertTrue( $prefix->isProtected() );
		$prefix->setAccessible( true );
		$this->assertSame( $prefix->getValue( $autoloader ), 'Activitypub' );

		$prefix_length = new \ReflectionProperty( '\Activitypub\Autoloader', 'prefix_length' );
		$this->assertTrue( $prefix_length->isProtected() );
		$prefix_length->setAccessible( true );
		$this->assertSame( $prefix_length->getValue( $autoloader ), strlen( 'Activitypub' ) );

		$path = new \ReflectionProperty( '\Activitypub\Autoloader', 'path' );
		$this->assertTrue( $path->isProtected() );
		$path->setAccessible( true );
		$this->assertSame( $path->getValue( $autoloader ), rtrim( __DIR__ . '/' ) );
	}

	/**
	 * Test_register_path.
	 *
	 * @covers ::register_path
	 */
	public function test_register_path() {
		Autoloader::register_path( 'Activitypub', __DIR__ );

		foreach ( spl_autoload_functions() as $function ) {
			if ( is_array( $function ) && $function[0] instanceof Autoloader ) {
				$path = new \ReflectionProperty( '\Activitypub\Autoloader', 'path' );
				$path->setAccessible( true );

				if ( $path->getValue( $function[0] ) === rtrim( __DIR__ . '/' ) ) {
					$this->assertTrue( true );
					return;
				}
			}
		}

		$this->fail( 'Failed asserting that autoload function gets registered.' );
	}

	/**
	 * Test_load.
	 *
	 * @covers ::load
	 */
	public function test_load() {
		$autoloader = new Autoloader( __NAMESPACE__, dirname( __DIR__ ) );

		// Wrong prefix.
		$autoloader->load( 'Activitypub\Autoload_Test_File' );
		$this->assertFalse( class_exists( 'Activitypub\Autoload_Test_File' ) );

		// Right prefix but class doesn't exist.
		$autoloader->load( 'Activitypub\Tests\Data\Non\Existent\Class' );
		$this->assertFalse( class_exists( 'Activitypub\Tests\Data\Non\Existent\Class' ) );

		// Class should load.
		$autoloader->load( 'Activitypub\Tests\Data\Autoload_Test_File' );
		$this->assertTrue( class_exists( 'Activitypub\Tests\Data\Autoload_Test_File' ) );
	}
}
