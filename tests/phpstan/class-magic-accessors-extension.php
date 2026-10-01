<?php
/**
 * PHPStan extension for the dynamic accessors of the activity objects.
 *
 * @package Activitypub
 */

namespace Activitypub\PHPStan;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Generic_Object;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\ArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\TypeCombinator;

/**
 * Teaches PHPStan the `get_*()`, `set_*()` and `add_*()` methods dispatched by
 * Generic_Object and its descendants. The attribute is the method name after
 * the prefix, lower-cased.
 *
 * A getter has the declared type of the attribute, or `null` when the object
 * does not carry it; an attribute the class does not declare may still be set
 * dynamically, so it is `mixed`. Setters return the object; adders return an
 * array or null. Base objects can reject undeclared properties with WP_Error.
 */
class Magic_Accessors_Extension implements MethodsClassReflectionExtension {
	/**
	 * Whether the class answers the method through `__call()`.
	 *
	 * @param ClassReflection $class_reflection The class.
	 * @param string          $method_name      The method name.
	 *
	 * @return bool
	 */
	public function hasMethod( ClassReflection $class_reflection, string $method_name ): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( Generic_Object::class !== $class_reflection->getName() && ! $class_reflection->isSubclassOf( Generic_Object::class ) ) {
			return false;
		}

		if ( Generic_Object::class !== $class_reflection->getNativeMethod( '__call' )->getDeclaringClass()->getName() ) {
			return false;
		}

		return 1 === \preg_match( '/^(get|set|add)_.+$/i', $method_name );
	}

	/**
	 * Build the signature `__call()` implements.
	 *
	 * @param ClassReflection $class_reflection The class.
	 * @param string          $method_name      The method name.
	 *
	 * @return MethodReflection
	 */
	public function getMethod( ClassReflection $class_reflection, string $method_name ): MethodReflection { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$method_name = \strtolower( $method_name );
		$attribute   = \substr( $method_name, 4 );

		if ( 0 === \strpos( $method_name, 'get' ) ) {
			$type = $class_reflection->hasNativeProperty( $attribute )
				? TypeCombinator::addNull( $class_reflection->getNativeProperty( $attribute )->getReadableType() )
				: new MixedType();

			return new Magic_Accessor( $class_reflection, $method_name, array(), $type );
		}

		$value = new NativeParameterReflection( 'value', false, new MixedType(), PassedByReference::createNo(), false, null );
		$type  = 0 === \strpos( $method_name, 'add' )
			? TypeCombinator::addNull( new ArrayType( new MixedType(), new MixedType() ) )
			: new StaticType( $class_reflection );

		if ( ! $class_reflection->hasNativeProperty( $attribute ) && ( Base_Object::class === $class_reflection->getName() || $class_reflection->isSubclassOf( Base_Object::class ) ) ) {
			$type = TypeCombinator::union( $type, new ObjectType( 'WP_Error' ) );
		}

		return new Magic_Accessor( $class_reflection, $method_name, array( $value ), $type );
	}
}
