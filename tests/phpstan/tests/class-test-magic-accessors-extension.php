<?php
/**
 * Magic accessor reflection tests.
 *
 * @package Activitypub
 * @group activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Generic_Object;
use Activitypub\PHPStan\Magic_Accessors_Extension;
use Activitypub\Tests\PHPStan\Overridden_Magic_Object;
use Activitypub\Tests\PHPStan\Unrelated_Magic_Object;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\ArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\StringType;
use PHPStan\Type\TypeCombinator;

/**
 * Test the signatures exposed to PHPStan.
 */
class Test_Magic_Accessors_Extension extends PHPStanTestCase {
	/**
	 * Load only the classes needed by the reflection tests.
	 *
	 * @return string[]
	 */
	public static function getAdditionalConfigFiles(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array( \dirname( __DIR__ ) . '/tests.neon' );
	}

	/**
	 * Only inherited activity-object magic methods are handled.
	 */
	public function test_supported_classes(): void {
		$provider  = self::createReflectionProvider();
		$extension = new Magic_Accessors_Extension();
		$generic   = $provider->getClass( Generic_Object::class );

		$this->assertTrue( $extension->hasMethod( $generic, 'get_id' ) );
		$this->assertTrue( $extension->hasMethod( $generic, 'SET_id' ) );
		$this->assertTrue( $extension->hasMethod( $provider->getClass( Base_Object::class ), 'add_cc' ) );
		$this->assertFalse( $extension->hasMethod( $generic, 'delete_id' ) );
		$this->assertFalse( $extension->hasMethod( $provider->getClass( Unrelated_Magic_Object::class ), 'get_id' ) );
		$this->assertFalse( $extension->hasMethod( $provider->getClass( Overridden_Magic_Object::class ), 'get_id' ) );
	}

	/**
	 * Getters preserve the declared property type and its nullable state.
	 */
	public function test_getter_types(): void {
		$class     = self::createReflectionProvider()->getClass( Generic_Object::class );
		$extension = new Magic_Accessors_Extension();
		$type      = $extension->getMethod( $class, 'GET_id' )->getVariants()[0]->getReturnType();

		$this->assertTrue( $type->equals( TypeCombinator::addNull( new StringType() ) ) );
		$this->assertInstanceOf( MixedType::class, $extension->getMethod( $class, 'get_custom' )->getVariants()[0]->getReturnType() );
	}

	/**
	 * Setters are fluent, unless a base object rejects an unknown property.
	 */
	public function test_setter_types(): void {
		$provider  = self::createReflectionProvider();
		$extension = new Magic_Accessors_Extension();
		$generic   = $provider->getClass( Generic_Object::class );
		$base      = $provider->getClass( Base_Object::class );

		$this->assertTrue( $extension->getMethod( $generic, 'set_custom' )->getVariants()[0]->getReturnType()->equals( new StaticType( $generic ) ) );
		$this->assertTrue( $extension->getMethod( $base, 'set_id' )->getVariants()[0]->getReturnType()->equals( new StaticType( $base ) ) );
		$this->assertTrue( $extension->getMethod( $base, 'set_custom' )->getVariants()[0]->getReturnType()->equals( TypeCombinator::union( new StaticType( $base ), new ObjectType( 'WP_Error' ) ) ) );
	}

	/**
	 * Adders return the accumulated array, not a fluent object.
	 */
	public function test_adder_types(): void {
		$provider  = self::createReflectionProvider();
		$extension = new Magic_Accessors_Extension();
		$generic   = $provider->getClass( Generic_Object::class );
		$base      = $provider->getClass( Base_Object::class );
		$expected  = TypeCombinator::addNull( new ArrayType( new MixedType(), new MixedType() ) );

		$this->assertTrue( $extension->getMethod( $generic, 'add_custom' )->getVariants()[0]->getReturnType()->equals( $expected ) );
		$this->assertTrue( $extension->getMethod( $base, 'add_cc' )->getVariants()[0]->getReturnType()->equals( $expected ) );
		$this->assertTrue( $extension->getMethod( $base, 'add_custom' )->getVariants()[0]->getReturnType()->equals( TypeCombinator::union( $expected, new ObjectType( 'WP_Error' ) ) ) );
	}
}
