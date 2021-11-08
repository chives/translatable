<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Translatable;

final class TranslatableConfiguration
{
    /**
     * @var class-string
     */
    private string $translatableClass;
    private string $localeField;
    private TranslationConfiguration $translationsConfiguration;
    private ?PropertyConfiguration $localeFieldReflection;
    /**
     * @var array<PropertyConfiguration>
     */
    private array $propertyConfigurations;

    /**
     * @param class-string $translatableClass
     * @param class-string $translationsClass
     * @param string $localeField
     * @param array<string> $properties
     */
    public function __construct(
        string $translatableClass,
        string $localeField,
        string $translationsClass,
        string $translationsLocaleField,
        string $translationsPropertyField,
        array $properties
    ) {
        $this->translatableClass = $translatableClass;
        $this->localeField = $localeField;
        $this->localeFieldReflection = null;
        $this->translationsConfiguration = new TranslationConfiguration(
            $translationsClass,
            $translationsLocaleField,
            $translationsPropertyField,
            $properties
        );

        $this->propertyConfigurations = array_map(
            fn(string $property): PropertyConfiguration
                => new PropertyConfiguration($translatableClass, $property),
            $properties
        );
    }

    /**
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->translatableClass;
    }

    public function getLocale(object $entity): ?string
    {
        return $this->getLocaleFieldReflection()->getValueForEntity($entity);
    }

    public function setLocale(object $entity, string $locale): void
    {
        $this->getLocaleFieldReflection()->setValueForEntity($entity, $locale);
    }

    public function getTranslationConfiguration(): TranslationConfiguration
    {
        return $this->translationsConfiguration;
    }

    /**
     * @return array<PropertyConfiguration>
     */
    public function getPropertyConfigurations(): array
    {
        return $this->propertyConfigurations;
    }

    private function getLocaleFieldReflection(): PropertyConfiguration
    {
        if (null === $this->localeFieldReflection) {
            $this->localeFieldReflection = new PropertyConfiguration(
                $this->translatableClass,
                $this->localeField
            );
        }

        return $this->localeFieldReflection;
    }
}
