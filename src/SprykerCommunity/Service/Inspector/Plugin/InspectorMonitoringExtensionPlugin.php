<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Plugin;

use Spryker\Service\Kernel\AbstractPlugin;
use Spryker\Service\MonitoringExtension\Dependency\Plugin\MonitoringExtensionPluginInterface;

/**
 * Bridges Spryker monitoring events onto Inspector for every runtime that already
 * reports to the Monitoring service: Zed HTTP, console commands and queue workers.
 *
 * Unlike New Relic, Inspector has no ambient process transaction, so a transaction is
 * opened lazily on the first monitoring call instead of waiting for a dedicated start
 * event. Spryker names console transactions only on ConsoleTerminateEvent, which is too
 * late for AI segments recorded during the command.
 *
 * @method \SprykerCommunity\Service\Inspector\InspectorServiceInterface getService()
 * @method \SprykerCommunity\Service\Inspector\InspectorConfig getConfig()
 */
class InspectorMonitoringExtensionPlugin extends AbstractPlugin implements MonitoringExtensionPluginInterface
{
    /**
     * Matches the transaction type the Inspector Symfony bundle uses for console commands
     * (\Inspector\Symfony\Bundle\Listeners\ConsoleEventsSubscriber), so commands are
     * classified the same way regardless of which integration reported them.
     */
    protected const string TRANSACTION_TYPE_COMMAND = 'command';

    protected const string DEFAULT_TRANSACTION_NAME = 'zed';

    protected const string CONTEXT_KEY_APPLICATION = 'application';

    protected const string RESULT_SUCCESS = 'success';

    protected const string RESULT_ERROR = 'error';

    protected const string SAPI_CLI = 'cli';

    /**
     * @see \Spryker\Zed\Monitoring\Communication\Plugin\MonitoringConsolePlugin::TRANSACTION_NAME_PREFIX
     */
    protected const string CONSOLE_TRANSACTION_NAME_PREFIX = 'vendor/bin/console ';

    protected ?string $applicationName = null;

    protected bool $hasError = false;

    protected bool $isHttpContextAttached = false;

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Exception|\Throwable $exception
     */
    public function setError(string $message, $exception): void
    {
        $this->hasError = true;

        $this->getService()->reportError($message, $exception);
        $this->getService()->setTransactionResult(static::RESULT_ERROR);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setApplicationName(?string $application = null, ?string $store = null, ?string $environment = null): void
    {
        $this->applicationName = sprintf('%s-%s (%s)', (string)$application, (string)$store, (string)$environment);

        $this->getService()->addTransactionContext(static::CONTEXT_KEY_APPLICATION, $this->applicationName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setTransactionName(string $name): void
    {
        if ($this->isIgnored($name)) {
            $this->getService()->ignoreTransaction();

            return;
        }

        $this->getService()->setTransactionName($name);

        $this->initializeTransaction();
    }

    /**
     * Discarding here also drops anything already recorded for the transaction, which is the
     * intent for scheduler-driven commands and for noisy endpoints such as health checks.
     */
    protected function isIgnored(string $transactionName): bool
    {
        if (PHP_SAPI !== static::SAPI_CLI) {
            return $this->getConfig()->isTransactionIgnored($transactionName);
        }

        return $this->getConfig()->isCommandIgnored($this->extractCommandName($transactionName));
    }

    /**
     * Spryker names console transactions on ConsoleTerminateEvent, prefixed with the binary path,
     * so the command name has to be recovered from it before matching the ignore patterns.
     */
    protected function extractCommandName(string $transactionName): string
    {
        if (!str_starts_with($transactionName, static::CONSOLE_TRANSACTION_NAME_PREFIX)) {
            return trim($transactionName);
        }

        return trim(substr($transactionName, strlen(static::CONSOLE_TRANSACTION_NAME_PREFIX)));
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function markStartTransaction(): void
    {
        $this->getService()->ensureTransaction($this->applicationName ?? static::DEFAULT_TRANSACTION_NAME);

        $this->initializeTransaction();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function markEndOfTransaction(): void
    {
        $this->getService()->flush();
    }

    /**
     * Spryker never calls markStartTransaction()/markEndOfTransaction() in Zed — setTransactionName()
     * is the only method it invokes — so the transaction has to be completed from here. The result is
     * set eagerly and downgraded by setError(), because nothing signals the end of a Zed request
     * before Inspector flushes on shutdown.
     *
     * When InspectorEventDispatcherPlugin is registered it overwrites this result with the actual
     * HTTP status code on kernel.terminate. This eager value is what remains without it, and for
     * console commands, where no terminate event reaches this plugin.
     */
    protected function initializeTransaction(): void
    {
        $this->getService()->setTransactionResult($this->hasError ? static::RESULT_ERROR : static::RESULT_SUCCESS);

        if ($this->isHttpContextAttached || PHP_SAPI === static::SAPI_CLI) {
            return;
        }

        $this->getService()->markTransactionAsHttpRequest();
        $this->isHttpContextAttached = true;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function markIgnoreTransaction(): void
    {
        $this->getService()->ignoreTransaction();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function markAsConsoleCommand(): void
    {
        $this->getService()->ensureTransaction($this->applicationName ?? static::DEFAULT_TRANSACTION_NAME);
        $this->getService()->setTransactionType(static::TRANSACTION_TYPE_COMMAND);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param mixed $value
     */
    public function addCustomParameter(string $key, $value): void
    {
        $this->getService()->addTransactionContext($key, $value);
    }

    /**
     * {@inheritDoc}
     *
     * Inspector has no equivalent of a custom tracer: segments are created explicitly
     * rather than by instrumenting a named function, so there is nothing to register.
     *
     * @api
     */
    public function addCustomTracer(string $tracer): void // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter
    {
    }
}
