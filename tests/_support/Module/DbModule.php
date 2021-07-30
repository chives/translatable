<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\FSi\Module;

use Codeception\Module;
use Codeception\Module\Symfony;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

use function file_exists;
use function sprintf;
use function unlink;

final class DbModule extends Module
{
    /**
     * @param array<string, mixed> $settings
     * @phpcs:disable
     */
    public function _beforeSuite($settings = []): void
    {
        $this->removeDatabaseFile();

        /** @var Symfony $symfony */
        $symfony = $this->getModule('Symfony');

        /** @var EntityManagerInterface $manager */
        $manager = $symfony->_getEntityManager();

        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * @phpcs:disable
     */
    public function _afterSuite(): void
    {
        $this->removeDatabaseFile();
    }

    private function removeDatabaseFile(): void
    {
        $filePath = sprintf('%s/../project/var/data.sqlite', __DIR__);
        if (false === file_exists($filePath)) {
            return;
        }

        unlink($filePath);
    }
}
