<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Plugin\Twig;

use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\TwigExtension\Dependency\Plugin\TwigPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Twig\Environment;

/**
 * Reports rendered templates as "view.twig" segments.
 *
 * Twig instruments templates at compile time, so the Twig cache must be rebuilt after enabling or
 * disabling INSPECTOR:IS_TWIG_TRACKING_ENABLED. Templates compiled while it was disabled contain
 * no profiling calls and stay silent until they are recompiled.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorTwigPlugin extends AbstractPlugin implements TwigPluginInterface
{
    /**
     * {@inheritDoc}
     * - Registers the Inspector Twig tracer when template tracking is enabled.
     *
     * @api
     *
     * @param \Twig\Environment $twig
     * @param \Spryker\Service\Container\ContainerInterface $container
     *
     * @return \Twig\Environment
     */
    public function extend(Environment $twig, ContainerInterface $container): Environment
    {
        if (!$this->getFactory()->getInspectorService()->isTwigTrackingEnabled()) {
            return $twig;
        }

        $twig->addExtension($this->getFactory()->createInspectorTwigTracer());

        return $twig;
    }
}
