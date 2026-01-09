<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Translatable\Integration\Symfony;

use FSi\Component\Translatable\Integration\Symfony\DependencyInjection\TranslatableExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function sprintf;

final class TranslatableBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader(
            $container,
            new FileLocator(sprintf('%s/Resources/config/services', __DIR__))
        );

        if (true === $container->hasExtension('doctrine')) {
            $loader->load('doctrine.xml');
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (false === $this->extension instanceof TranslatableExtension) {
            $this->extension = new TranslatableExtension();
        }

        return $this->extension;
    }
}
