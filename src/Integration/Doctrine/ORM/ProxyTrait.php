<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Translatable\Integration\Doctrine\ORM;

use Doctrine\Persistence\Proxy;

trait ProxyTrait
{
    private function initializeProxy(): static
    {
        if ($this instanceof Proxy) {
            $this->__load();
        }

        return $this;
    }
}
