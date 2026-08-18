<?php

/**
 * This file is part of the Spryker Commerce OS.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorTestConsole extends Console
{
    protected const string COMMAND_NAME = 'inspector:test';

    protected const string DESCRIPTION = 'Verifies the Inspector APM integration by sending a sample transaction.';

    protected const string TRANSACTION_TYPE = 'command';

    protected const string MESSAGE_NOT_RECORDING = 'Inspector is not recording. Set the INSPECTOR_INGESTION_KEY environment variable and make sure INSPECTOR_IS_ENABLED is not disabled.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)->setDescription(static::DESCRIPTION);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter
    {
        $inspectorService = $this->getFactory()->getInspectorService();

        if (!$inspectorService->isRecording()) {
            $this->error(static::MESSAGE_NOT_RECORDING);

            return static::CODE_ERROR;
        }

        return $this->runInspectorTestCommand($output);
    }

    /**
     * The packaged command closes a transaction that is opened by the Symfony event subscriber,
     * which is not part of the Spryker console stack, so it is opened here instead.
     */
    protected function runInspectorTestCommand(OutputInterface $output): int
    {
        $inspectorService = $this->getFactory()->getInspectorService();

        $inspectorService->ensureTransaction(static::COMMAND_NAME);
        $inspectorService->setTransactionType(static::TRANSACTION_TYPE);

        $exitCode = $this->getFactory()
            ->createInspectorTestCommand()
            ->run(new ArrayInput([]), $output);

        $inspectorService->flush();

        return $exitCode;
    }
}
