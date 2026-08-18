<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use Spryker\Zed\User\Business\UserFacadeInterface;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;

class InspectorDependencyProvider extends AbstractBundleDependencyProvider
{
    public const string SERVICE_INSPECTOR = 'SERVICE_INSPECTOR';

    public const string FACADE_USER = 'FACADE_USER';

    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addUserFacade($container);

        return $this->addInspectorService($container);
    }

    protected function addUserFacade(Container $container): Container
    {
        $container->set(static::FACADE_USER, function (Container $container): UserFacadeInterface {
            return $container->getLocator()->user()->facade();
        });

        return $container;
    }

    protected function addInspectorService(Container $container): Container
    {
        $container->set(static::SERVICE_INSPECTOR, function (Container $container): InspectorServiceInterface {
            return $container->getLocator()->inspector()->service();
        });

        return $container;
    }
}
