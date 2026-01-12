<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Translatable\Integration\Doctrine\ORM\Subscriber;

use Assert\Assertion;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Proxy;
use FSi\Component\Translatable\ConfigurationResolver;
use FSi\Component\Translatable\Entity\TranslationCleaner;
use FSi\Component\Translatable\Entity\TranslationLoader;
use FSi\Component\Translatable\Entity\TranslationUpdater;
use FSi\Component\Translatable\LocaleProvider;

use function array_walk;
use function get_class;
use function in_array;
use function method_exists;

final class EntitySubscriber
{
    private LocaleProvider $localeProvider;
    private ConfigurationResolver $entityConfigurationResolver;
    private TranslationLoader $translationLoader;
    private TranslationUpdater $translationUpdater;
    private TranslationCleaner $translationCleaner;

    public function __construct(
        LocaleProvider $localeProvider,
        ConfigurationResolver $entityConfigurationResolver,
        TranslationLoader $translationLoader,
        TranslationUpdater $translationUpdater,
        TranslationCleaner $translationCleaner
    ) {
        $this->localeProvider = $localeProvider;
        $this->entityConfigurationResolver = $entityConfigurationResolver;
        $this->translationLoader = $translationLoader;
        $this->translationUpdater = $translationUpdater;
        $this->translationCleaner = $translationCleaner;
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $event
     */
    public function postLoad(LifecycleEventArgs $event): void
    {
        $object = $event->getObject();
        if (false === $this->isTranslatable($object)) {
            return;
        }

        $this->initializeProxy($object);
        $this->translationLoader->loadFromLocale($object, $this->localeProvider->getLocale());
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $event
     */
    public function preRemove(LifecycleEventArgs $event): void
    {
        $object = $event->getObject();
        if (false === $this->isTranslatable($object)) {
            return;
        }

        $this->initializeProxy($object);
        $this->translationCleaner->clean($object);
    }

    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = true === method_exists($eventArgs, 'getObjectManager')
            ? $eventArgs->getObjectManager()
            : $eventArgs->getEntityManager()
        ;

        $uow = $manager->getUnitOfWork();
        if (true === $this->isDeepNestedTransaction($manager)) {
            return;
        }

        $locale = $this->localeProvider->getLocale();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        array_walk(
            $scheduledInsertions,
            function (object $entity, $key, string $locale): void {
                if (false === $this->isTranslatable($entity)) {
                    return;
                }

                if (true === $this->isDisabledTranslationsAutoUpdate($entity)) {
                    return;
                }

                $this->initializeProxy($entity);
                $this->setEntityLocaleIfIsNull($entity, $locale);
                $this->translationUpdater->update($entity);
            },
            $locale
        );

        $identityMap = $uow->getIdentityMap();
        $scheduledDeletions = $uow->getScheduledEntityDeletions();
        array_walk($identityMap, function (array $entities) use ($scheduledInsertions, $scheduledDeletions): void {
            array_walk($entities, function (?object $entity) use ($scheduledInsertions, $scheduledDeletions): void {
                if (null === $entity) {
                    return;
                }

                if (true === in_array($entity, $scheduledInsertions, true)) {
                    return;
                }

                if (true === in_array($entity, $scheduledDeletions, true)) {
                    return;
                }

                if (false === $this->isTranslatable($entity)) {
                    return;
                }

                if (true === $this->isDisabledTranslationsAutoUpdate($entity)) {
                    return;
                }

                $this->initializeProxy($entity);
                $this->translationUpdater->update($entity);
            });
        });
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = true === method_exists($eventArgs, 'getObjectManager')
            ? $eventArgs->getObjectManager()
            : $eventArgs->getEntityManager()
        ;

        if (true === $this->isDeepNestedTransaction($manager)) {
            return;
        }

        $locale = $this->localeProvider->getLocale();
        $scheduledInsertions = $manager->getUnitOfWork()->getScheduledEntityInsertions();
        array_walk(
            $scheduledInsertions,
            function (object $entity) use ($locale): void {
                $this->initializeEmptyTranslatableForNewTranslation($entity, $locale);
            }
        );
    }

    private function initializeEmptyTranslatableForNewTranslation(object $translation, string $locale): void
    {
        if (false === $this->isTranslation($translation)) {
            return;
        }

        $this->initializeProxy($translation);
        $translationConfiguration = $this->entityConfigurationResolver->resolveTranslation($translation);
        $translationLocale = $translationConfiguration->getLocaleForEntity($translation);
        Assertion::notNull(
            $translationLocale,
            sprintf('No locale for translation of class "%s".', get_class($translation))
        );

        if ($translationLocale !== $locale) {
            return;
        }

        $entity = $translationConfiguration->getRelationValueForEntity($translation);
        Assertion::notNull(
            $entity,
            sprintf('No relation entity for translation of class "%s".', get_class($translation))
        );

        $translatableConfiguration = $this->entityConfigurationResolver->resolveTranslatable($entity);
        if ($translatableConfiguration->getLocale($entity) !== $translationLocale) {
            return;
        }

        $this->translationLoader->loadFromTranslation($entity, $translation);
    }

    private function setEntityLocaleIfIsNull(object $entity, string $locale): void
    {
        $configuration = $this->entityConfigurationResolver->resolveTranslatable($entity);
        if (null !== $configuration->getLocale($entity)) {
            return;
        }

        $configuration->setLocale($entity, $locale);
    }

    private function isDeepNestedTransaction(EntityManagerInterface $manager): bool
    {
        return 1 < $manager->getConnection()->getTransactionNestingLevel();
    }

    public function isDisabledTranslationsAutoUpdate(object $entity): bool
    {
        return $this->entityConfigurationResolver
            ->resolveTranslatable($entity)
            ->isDisabledAutoTranslationsUpdate()
        ;
    }

    private function isTranslatable(object $object): bool
    {
        return $this->entityConfigurationResolver->isTranslatable($object);
    }

    private function isTranslation(object $object): bool
    {
        return $this->entityConfigurationResolver->isTranslation($object);
    }

    private function initializeProxy(object $object): void
    {
        if (true === $object instanceof Proxy && false === $object->__isInitialized()) {
            $object->__load();
        }
    }
}
