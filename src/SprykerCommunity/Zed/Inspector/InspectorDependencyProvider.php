<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;

class InspectorDependencyProvider extends AbstractBundleDependencyProvider
{
    public const string SERVICE_INSPECTOR = 'SERVICE_INSPECTOR';

    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);

        return $this->addInspectorService($container);
    }

    protected function addInspectorService(Container $container): Container
    {
        $container->set(static::SERVICE_INSPECTOR, function (Container $container): InspectorServiceInterface {
            return $container->getLocator()->inspector()->service();
        });

        return $container;
    }
}
