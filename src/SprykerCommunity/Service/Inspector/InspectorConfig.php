<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector;

use Inspector\Symfony\Bundle\Filters;
use Spryker\Service\Kernel\AbstractBundleConfig;
use SprykerCommunity\Shared\Inspector\InspectorConstants;

class InspectorConfig extends AbstractBundleConfig
{
    protected const string DEFAULT_URL = 'https://ingest.inspector.dev';

    protected const string DEFAULT_TRANSPORT = 'async';

    protected const bool DEFAULT_IS_ENABLED = true;

    /**
     * Mirrors \Inspector\Configuration::$maxItems, so the default stays a no-op.
     */
    protected const int DEFAULT_MAX_ITEMS = 150;

    /**
     * @var array<int, string>
     */
    protected const array DEFAULT_ENABLED_APPLICATIONS = ['ZED'];

    public function getIngestionKey(): string
    {
        return trim((string)$this->get(InspectorConstants::INGESTION_KEY, ''));
    }

    public function getUrl(): string
    {
        return trim((string)$this->get(InspectorConstants::URL, static::DEFAULT_URL)) ?: static::DEFAULT_URL;
    }

    /**
     * An invalid URL would make the Inspector configuration throw on every request,
     * so callers must skip applying it rather than let it reach the SDK.
     */
    public function hasValidUrl(): bool
    {
        return filter_var($this->getUrl(), FILTER_VALIDATE_URL) !== false;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_ENABLED, static::DEFAULT_IS_ENABLED);
    }

    public function getTransport(): string
    {
        return trim((string)$this->get(InspectorConstants::TRANSPORT, static::DEFAULT_TRANSPORT)) ?: static::DEFAULT_TRANSPORT;
    }

    /**
     * @return array<int, string>
     */
    public function getIgnoredCommands(): array
    {
        return (array)$this->get(InspectorConstants::IGNORED_COMMANDS, []);
    }

    public function isCommandIgnored(string $commandName): bool
    {
        return $this->matchesAnyPattern($commandName, $this->getIgnoredCommands());
    }

    /**
     * @return array<int, string>
     */
    public function getIgnoredTransactions(): array
    {
        return (array)$this->get(InspectorConstants::IGNORED_TRANSACTIONS, []);
    }

    public function isTransactionIgnored(string $transactionName): bool
    {
        return $this->matchesAnyPattern($transactionName, $this->getIgnoredTransactions());
    }

    public function getMaxItems(): int
    {
        return (int)$this->get(InspectorConstants::MAX_ITEMS, static::DEFAULT_MAX_ITEMS);
    }

    public function isTwigTrackingEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_TWIG_TRACKING_ENABLED, false);
    }

    public function isPropelTrackingEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_PROPEL_TRACKING_ENABLED, false);
    }

    public function getPropelSlowQueryThresholdMilliseconds(): float
    {
        return (float)$this->get(InspectorConstants::PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS, 0.0);
    }

    public function isQueryBindingsTrackingEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_QUERY_BINDINGS_TRACKING_ENABLED, false);
    }

    public function isAgentTransactionTypeEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_AGENT_TRANSACTION_TYPE_ENABLED, true);
    }

    public function isHttpClientTrackingEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_HTTP_CLIENT_TRACKING_ENABLED, false);
    }

    public function isHttpQueryTrackingEnabled(): bool
    {
        return (bool)$this->get(InspectorConstants::IS_HTTP_QUERY_TRACKING_ENABLED, false);
    }

    /**
     * Reuses the wildcard matcher shipped with the Inspector Symfony bundle so that patterns
     * behave exactly as they do for the bundle's own ignore_commands and ignore_routes options.
     *
     * @param array<int, string> $patterns
     */
    protected function matchesAnyPattern(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Filters::matchWithWildcard((string)$pattern, $subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getEnabledApplications(): array
    {
        return (array)$this->get(InspectorConstants::ENABLED_APPLICATIONS, static::DEFAULT_ENABLED_APPLICATIONS);
    }

    /**
     * The monitoring plugin lives in the Service layer, which Yves, Glue, the Merchant Portal and
     * Zed all share, so the running application decides whether anything is recorded at all.
     */
    public function isCurrentApplicationEnabled(): bool
    {
        if (!defined('APPLICATION')) {
            return false;
        }

        return in_array(APPLICATION, $this->getEnabledApplications(), true);
    }
}
