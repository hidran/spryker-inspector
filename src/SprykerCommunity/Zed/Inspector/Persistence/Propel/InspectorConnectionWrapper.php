<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Persistence\Propel;

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\StatementWrapper;
use Spryker\Service\Kernel\Locator;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use Throwable;

/**
 * Reports executed Propel statements as Inspector segments, filling the gap left by
 * \Inspector\Symfony\Bundle\Doctrine\Middleware\InspectorMiddleware, which cannot be used because
 * Spryker persists through Propel.
 *
 * Enable by pointing the Propel connection at this class in config/Shared/config_default.php:
 *
 *   $config[PropelConstants::PROPEL]['database']['connections']['default']['classname']
 *       = InspectorConnectionWrapper::class;
 *
 * This deliberately does not use Propel's own debug logging, which only emits after the fact
 * without a duration, calls debug_backtrace() per query and disables prepared statement caching.
 *
 * Propel builds connections through \Propel\Runtime\Connection\ConnectionFactory, which passes
 * only the adapter connection, so the Inspector service cannot be injected and is resolved here
 * instead. Propel keeps one connection per process, which is what makes this instance the right
 * place to hold it: the statement wrappers it creates read it back from here rather than each
 * keeping their own copy.
 */
class InspectorConnectionWrapper extends ConnectionWrapper
{
    protected ?InspectorServiceInterface $inspectorService = null;

    protected bool $isTrackingDisabled = false;

    protected function createStatementWrapper(string $sql): StatementWrapper
    {
        return new InspectorStatementWrapper($sql, $this);
    }

    /**
     * Returns null when tracking is switched off in configuration, or when reporting has failed
     * and was disabled for the remainder of the process.
     */
    public function getInspectorService(): ?InspectorServiceInterface
    {
        if ($this->isTrackingDisabled) {
            return null;
        }

        if ($this->inspectorService !== null) {
            return $this->inspectorService;
        }

        $inspectorService = $this->resolveInspectorService();

        if ($inspectorService === null || !$inspectorService->isPropelTrackingEnabled()) {
            $this->isTrackingDisabled = true;

            return null;
        }

        $this->inspectorService = $inspectorService;

        return $this->inspectorService;
    }

    /**
     * This class sits in the path of every database call the application makes, so a monitoring
     * failure must never become a query failure. Reporting is switched off for the rest of the
     * process rather than retried on the next query.
     */
    public function disableTracking(): void
    {
        $this->isTrackingDisabled = true;
    }

    protected function resolveInspectorService(): ?InspectorServiceInterface
    {
        try {
            /** @var \SprykerCommunity\Service\Inspector\InspectorServiceInterface $inspectorService */
            $inspectorService = Locator::getInstance()->inspector()->service();
        } catch (Throwable $throwable) {
            return null;
        }

        return $inspectorService;
    }
}
