<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication;

use Inspector\Symfony\Bundle\Command\InspectorTestCommand;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use SprykerCommunity\Zed\Inspector\InspectorDependencyProvider;

class InspectorCommunicationFactory extends AbstractCommunicationFactory
{
    use LoggerTrait;

    /**
     * Builds the packaged command against the process-wide Inspector client so that
     * inspector:test verifies the same client and configuration the application uses.
     */
    public function createInspectorTestCommand(): InspectorTestCommand
    {
        return new InspectorTestCommand(
            $this->getInspectorService()->getInspector(),
            $this->getLogger(),
            $this->getInspectorService()->getInspectorConfiguration(),
        );
    }

    public function getInspectorService(): InspectorServiceInterface
    {
        return $this->getProvidedDependency(InspectorDependencyProvider::SERVICE_INSPECTOR);
    }
}
