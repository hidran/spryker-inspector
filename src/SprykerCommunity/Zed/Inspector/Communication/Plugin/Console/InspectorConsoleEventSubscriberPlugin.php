<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Plugin\Console;

use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Opens the Inspector transaction when a console command starts, mirroring
 * \Inspector\Symfony\Bundle\Listeners\ConsoleEventsSubscriber.
 *
 * Spryker reports console transactions on ConsoleTerminateEvent, once the command has already
 * finished. Nothing the command does can be recorded against a transaction that does not exist
 * yet, so without this plugin console runs arrive as a bare duration: no segments for database
 * statements, outbound requests, AI prompts or tool calls.
 *
 * That covers queue workers too, which Spryker runs as console commands.
 *
 * Spryker renames the transaction to its own "vendor/bin/console <command>" form at terminate,
 * so registering this does not change how transactions are named.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorConsoleEventSubscriberPlugin extends AbstractPlugin implements EventSubscriberInterface
{
    protected const string TRANSACTION_TYPE_COMMAND = 'command';

    protected const string CONTEXT_KEY_COMMAND = 'Command';

    protected const string RESULT_SUCCESS = 'success';

    protected const string RESULT_ERROR = 'error';

    /**
     * Runs before the command, so its work lands inside the transaction.
     */
    protected const int PRIORITY_FIRST = 9999;

    /**
     * Runs after Spryker's own monitoring subscriber has renamed the transaction.
     */
    protected const int PRIORITY_LAST = -9999;

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', static::PRIORITY_FIRST],
            ConsoleEvents::ERROR => ['onConsoleError', static::PRIORITY_FIRST],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', static::PRIORITY_LAST],
        ];
    }

    /**
     * @api
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $commandName = (string)$event->getCommand()?->getName();
        $inspectorService = $this->getFactory()->getInspectorService();

        if ($commandName === '' || $inspectorService->isCommandIgnored($commandName)) {
            return;
        }

        $inspectorService->ensureTransaction($commandName);
        $inspectorService->setTransactionType(static::TRANSACTION_TYPE_COMMAND);
        $inspectorService->addTransactionContext(static::CONTEXT_KEY_COMMAND, [
            'arguments' => $event->getInput()->getArguments(),
            'options' => $event->getInput()->getOptions(),
        ]);
    }

    /**
     * @api
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->getFactory()->getInspectorService()->reportError($event->getError()->getMessage(), $event->getError());
    }

    /**
     * Flushing here rather than leaving it to the SDK shutdown handler is what keeps long-running
     * workers from holding a whole shift of data in memory.
     *
     * @api
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $inspectorService = $this->getFactory()->getInspectorService();

        $inspectorService->setTransactionResult(
            $event->getExitCode() === 0 ? static::RESULT_SUCCESS : static::RESULT_ERROR,
        );
        $inspectorService->flush();
    }
}
