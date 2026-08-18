<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector;

use Inspector\Configuration;
use Inspector\Inspector;
use Spryker\Service\Kernel\AbstractBundleDependencyProvider;
use Spryker\Service\Kernel\Container;
use SprykerCommunity\Service\Inspector\Model\OpenSegmentRegistry;
use SprykerCommunity\Service\Inspector\Model\OpenSegmentRegistryInterface;

/**
 * The Inspector instance is registered as a container service so that every runtime
 * (Zed HTTP, console, queue workers) shares one instance per process. Segments added by
 * the AiFoundation plugins must attach to the transaction opened by the monitoring plugin.
 *
 * @method \SprykerCommunity\Service\Inspector\InspectorConfig getConfig()
 */
class InspectorDependencyProvider extends AbstractBundleDependencyProvider
{
    public const string INSPECTOR = 'INSPECTOR';

    public const string INSPECTOR_CONFIGURATION = 'INSPECTOR_CONFIGURATION';

    public const string OPEN_SEGMENT_REGISTRY = 'OPEN_SEGMENT_REGISTRY';

    public function provideServiceDependencies(Container $container): Container
    {
        $container = parent::provideServiceDependencies($container);
        $container = $this->addInspectorConfiguration($container);
        $container = $this->addOpenSegmentRegistry($container);

        return $this->addInspector($container);
    }

    protected function addOpenSegmentRegistry(Container $container): Container
    {
        $container->set(static::OPEN_SEGMENT_REGISTRY, function (): OpenSegmentRegistryInterface {
            return new OpenSegmentRegistry();
        });

        return $container;
    }

    protected function addInspectorConfiguration(Container $container): Container
    {
        $container->set(static::INSPECTOR_CONFIGURATION, function (): Configuration {
            return $this->createInspectorConfiguration();
        });

        return $container;
    }

    protected function addInspector(Container $container): Container
    {
        $container->set(static::INSPECTOR, function (Container $container): Inspector {
            return new Inspector($container->get(static::INSPECTOR_CONFIGURATION));
        });

        return $container;
    }

    protected function createInspectorConfiguration(): Configuration
    {
        $isEnabled = $this->getConfig()->isEnabled() && $this->getConfig()->isCurrentApplicationEnabled();

        $configuration = (new Configuration($this->getConfig()->getIngestionKey()))
            ->setEnabled($isEnabled)
            ->setMaxItems($this->getConfig()->getMaxItems())
            ->setTransport($this->getConfig()->getTransport());

        if (!$this->getConfig()->hasValidUrl()) {
            return $configuration;
        }

        return $configuration->setUrl($this->getConfig()->getUrl());
    }
}
