<?php

/**
 * This file is part of the Spryker Commerce OS.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector;

use Inspector\Configuration;
use Inspector\Inspector;
use Spryker\Service\Kernel\AbstractServiceFactory;
use SprykerCommunity\Service\Inspector\Model\OpenSegmentRegistryInterface;

/**
 * @method \SprykerCommunity\Service\Inspector\InspectorConfig getConfig()
 */
class InspectorServiceFactory extends AbstractServiceFactory
{
    public function getInspector(): Inspector
    {
        return $this->getProvidedDependency(InspectorDependencyProvider::INSPECTOR);
    }

    public function getInspectorConfiguration(): Configuration
    {
        return $this->getProvidedDependency(InspectorDependencyProvider::INSPECTOR_CONFIGURATION);
    }

    public function getOpenSegmentRegistry(): OpenSegmentRegistryInterface
    {
        return $this->getProvidedDependency(InspectorDependencyProvider::OPEN_SEGMENT_REGISTRY);
    }
}
