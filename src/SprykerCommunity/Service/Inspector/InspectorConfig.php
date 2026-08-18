<?php

/**
 * This file is part of the Spryker Commerce OS.
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

    /**
     * Reuses the wildcard matcher shipped with the Inspector Symfony bundle so that patterns
     * behave exactly as they do for the bundle's own ignore_commands option.
     */
    public function isCommandIgnored(string $commandName): bool
    {
        foreach ($this->getIgnoredCommands() as $pattern) {
            if (Filters::matchWithWildcard((string)$pattern, $commandName)) {
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
