<?php
/**
 * One dynamic accessor of the activity objects, as PHPStan sees it.
 *
 * @package Activitypub
 */

namespace Activitypub\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * One accessor as PHPStan sees it.
 */
class Magic_Accessor implements MethodReflection {
	/**
	 * The class the accessor belongs to.
	 *
	 * @var ClassReflection
	 */
	private $class_reflection;

	/**
	 * The method name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The parameters.
	 *
	 * @var NativeParameterReflection[]
	 */
	private $parameters;

	/**
	 * The return type.
	 *
	 * @var Type
	 */
	private $return_type;

	/**
	 * Constructor.
	 *
	 * @param ClassReflection             $class_reflection The class.
	 * @param string                      $name             The method name.
	 * @param NativeParameterReflection[] $parameters       The parameters.
	 * @param Type                        $return_type      The return type.
	 */
	public function __construct( ClassReflection $class_reflection, string $name, array $parameters, Type $return_type ) {
		$this->class_reflection = $class_reflection;
		$this->name             = $name;
		$this->parameters       = $parameters;
		$this->return_type      = $return_type;
	}

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, Squiz.Commenting.FunctionComment.Missing

	public function getDeclaringClass(): ClassReflection {
		return $this->class_reflection;
	}

	public function isStatic(): bool {
		return false;
	}

	public function isPrivate(): bool {
		return false;
	}

	public function isPublic(): bool {
		return true;
	}

	public function getDocComment(): ?string {
		return null;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getPrototype(): ClassMemberReflection {
		return $this;
	}

	public function getVariants(): array {
		return array( new FunctionVariant( TemplateTypeMap::createEmpty(), null, $this->parameters, false, $this->return_type ) );
	}

	public function isDeprecated(): TrinaryLogic {
		return TrinaryLogic::createNo();
	}

	public function getDeprecatedDescription(): ?string {
		return null;
	}

	public function isFinal(): TrinaryLogic {
		return TrinaryLogic::createNo();
	}

	public function isInternal(): TrinaryLogic {
		return TrinaryLogic::createNo();
	}

	public function getThrowType(): ?Type {
		return null;
	}

	public function hasSideEffects(): TrinaryLogic {
		return TrinaryLogic::createMaybe();
	}

	// phpcs:enable
}
