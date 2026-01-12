<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Translatable;

use Assert\Assertion;
use FSi\Component\Translatable\Exception\PropertyDoesNotExistException;
use FSi\Component\Translatable\Exception\UninitializedPropertyValueException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_reduce;
use function class_parents;
use function method_exists;
use function property_exists;

use const PHP_VERSION_ID;

final class PropertyConfiguration
{
    /**
     * @var class-string
     */
    private string $entityClass;
    private string $propertyName;
    private ?ReflectionProperty $propertyReflection;

    /**
     * @param class-string $entityClass
     * @throws InvalidArgumentException
     * @throws PropertyDoesNotExistException
     */
    public static function verifyPropertyExists(string $entityClass, string $propertyName): void
    {
        if (true === property_exists($entityClass, $propertyName)) {
            return;
        }

        $parents = class_parents($entityClass);
        Assertion::isArray($parents, "Unable to read parent classes for \"{$entityClass}\"");

        $propertyExistsInAParent = array_reduce(
            $parents,
            fn(bool $accumulator, string $parent): bool =>
                true === $accumulator || property_exists($parent, $propertyName),
            false
        );

        if (false === $propertyExistsInAParent) {
            throw PropertyDoesNotExistException::create($entityClass, $propertyName);
        }
    }

    /**
     * @param class-string $entityClass
     */
    public function __construct(string $entityClass, string $propertyName)
    {
        self::verifyPropertyExists($entityClass, $propertyName);

        $this->entityClass = $entityClass;
        $this->propertyName = $propertyName;
        $this->propertyReflection = null;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * @return mixed
     */
    public function getValueForEntity(object $entity)
    {
        Assertion::isInstanceOf($entity, $this->entityClass);
        $propertyReflection = $this->getPropertyReflection();
        if (false === $propertyReflection->isInitialized($entity)) {
            return $this->handleUninitializedProperty($propertyReflection);
        }

        return $propertyReflection->getValue($entity);
    }

    /**
     * @param mixed $value
     */
    public function setValueForEntity(object $entity, $value): void
    {
        Assertion::isInstanceOf($entity, $this->entityClass);
        $this->getPropertyReflection()->setValue($entity, $value);
    }

    /**
     * @return ReflectionNamedType|ReflectionUnionType|ReflectionType|null
     */
    public function getType()
    {
        return $this->getPropertyReflection()->getType();
    }

    private function getPropertyReflection(): ReflectionProperty
    {
        if (null === $this->propertyReflection) {
            $reflectionClass = new ReflectionClass($this->entityClass);
            do {
                if (false === $reflectionClass->hasProperty($this->propertyName)) {
                    continue;
                }

                $this->propertyReflection = $reflectionClass->getProperty($this->propertyName);
                if (PHP_VERSION_ID < 80500) {
                    $this->propertyReflection->setAccessible(true);
                }
            } while ($reflectionClass = $reflectionClass->getParentClass());
        }

        if (null === $this->propertyReflection) {
            throw PropertyDoesNotExistException::create($this->entityClass, $this->propertyName);
        }

        return $this->propertyReflection;
    }

    /**
     * @return null
     * @throws UninitializedPropertyValueException
     */
    private function handleUninitializedProperty(ReflectionProperty $propertyReflection)
    {
        if (
            true === method_exists($propertyReflection, 'hasDefaultValue')
            && true === method_exists($propertyReflection, 'getDefaultValue')
            && true === $propertyReflection->hasDefaultValue()
        ) {
            return $propertyReflection->getDefaultValue();
        }

        $type = $propertyReflection->getType();
        if (null === $type) {
            return null;
        }

        if (false === $type->allowsNull()) {
            throw UninitializedPropertyValueException::create(
                $this->entityClass,
                $propertyReflection->getName()
            );
        }

        return null;
    }
}
