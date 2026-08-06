<?php

/**
 * Unit test bootstrap.
 *
 * Some Magento factory classes are not shipped with magento/framework; a full
 * Magento installation generates them into generated/code. When the unit suite
 * runs against the standalone Composer package (for example in CI), those
 * classes do not exist, so tests cannot mock them. Define minimal equivalents
 * here; in a full Magento installation the generated classes win and this file
 * does nothing.
 */

declare(strict_types=1);

namespace Magento\Framework\Controller\Result;

use Magento\Framework\ObjectManagerInterface;

if (!\class_exists(RawFactory::class)) {
    class RawFactory
    {
        public function __construct(
            private readonly ?ObjectManagerInterface $objectManager = null,
            private readonly string $instanceName = Raw::class,
        ) {
        }

        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Raw
        {
            return $this->objectManager->create($this->instanceName, $data);
        }
    }
}
