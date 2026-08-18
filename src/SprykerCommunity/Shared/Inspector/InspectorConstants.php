<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\Inspector;

/**
 * Declares global environment configuration keys. Do not use it for other class constants.
 */
interface InspectorConstants
{
    /**
     * Specification:
     * - Ingestion key used to authenticate against the Inspector APM ingestion endpoint.
     * - Monitoring stays disabled as long as this value is empty.
     *
     * @api
     */
    public const string INGESTION_KEY = 'INSPECTOR:INGESTION_KEY';

    /**
     * Specification:
     * - Base URL of the Inspector APM ingestion endpoint.
     *
     * @api
     */
    public const string URL = 'INSPECTOR:URL';

    /**
     * Specification:
     * - Enables or disables sending data to Inspector APM.
     *
     * @api
     */
    public const string IS_ENABLED = 'INSPECTOR:IS_ENABLED';

    /**
     * Specification:
     * - Transport used to deliver data to Inspector APM.
     * - Supported values are "async" (non-blocking, requires proc_open) and "sync" (blocking cURL call).
     *
     * @api
     */
    public const string TRANSPORT = 'INSPECTOR:TRANSPORT';

    /**
     * Specification:
     * - Console commands that must not be reported, as a list of wildcard patterns, e.g. "queue:*".
     * - Matched against the command name only, without the "vendor/bin/console " prefix Spryker adds.
     * - Intended for scheduler-driven commands that run on a tight cron and would otherwise
     *   produce a transaction per execution.
     *
     * @api
     */
    public const string IGNORED_COMMANDS = 'INSPECTOR:IGNORED_COMMANDS';

    /**
     * Specification:
     * - Applications that report to Inspector, matched against the APPLICATION constant.
     * - The monitoring plugin is registered in the Service layer, which every application shares,
     *   so without this list the storefront and API applications would be monitored as well.
     * - "ZED" covers the Back Office, Zed, the Backend Gateway, console commands and queue workers.
     *
     * @api
     */
    public const string ENABLED_APPLICATIONS = 'INSPECTOR:ENABLED_APPLICATIONS';

    /**
     * Specification:
     * - Zed transactions that must not be reported, as a list of wildcard patterns.
     * - Matched against the Spryker transaction name, which is "module/controller/action",
     *   e.g. "heartbeat/index/index" or "heartbeat/*" to ignore a whole module.
     * - This is the request-side counterpart of INSPECTOR:IGNORED_COMMANDS.
     *
     * @api
     */
    public const string IGNORED_TRANSACTIONS = 'INSPECTOR:IGNORED_TRANSACTIONS';

    /**
     * Specification:
     * - Maximum number of entries (segments, tokens) reported per transaction.
     * - Entries beyond the limit are dropped, errors excluded.
     * - Raise it when database or template tracking is enabled, as a single Back Office
     *   request can produce several hundred query segments.
     *
     * @api
     */
    public const string MAX_ITEMS = 'INSPECTOR:MAX_ITEMS';

    /**
     * Specification:
     * - Reports every rendered Twig template as a "view.twig" segment.
     * - Requires SprykerCommunity\Zed\Inspector\Communication\Plugin\Twig\InspectorTwigPlugin.
     * - Templates are instrumented at compile time, so the Twig cache must be rebuilt
     *   after changing this value.
     *
     * @api
     */
    public const string IS_TWIG_TRACKING_ENABLED = 'INSPECTOR:IS_TWIG_TRACKING_ENABLED';

    /**
     * Specification:
     * - Reports every executed Propel statement as a "db.propel" segment.
     * - Requires the Propel connection classname to be set to
     *   SprykerCommunity\Zed\Inspector\Persistence\Propel\InspectorConnectionWrapper.
     *
     * @api
     */
    public const string IS_PROPEL_TRACKING_ENABLED = 'INSPECTOR:IS_PROPEL_TRACKING_ENABLED';

    /**
     * Specification:
     * - Minimum duration in milliseconds a Propel statement must take to be reported.
     * - 0 reports every statement, which can exceed INSPECTOR:MAX_ITEMS on a single request.
     *
     * @api
     */
    public const string PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS = 'INSPECTOR:PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS';

    /**
     * Specification:
     * - Reports Propel statements with their bound values interpolated instead of placeholders.
     * - Bound values routinely contain personal data (customer emails, addresses, order references)
     *   and are transmitted to Inspector as-is, so this stays disabled unless explicitly enabled.
     * - Interpolated statements no longer group in the dashboard, as each set of values
     *   produces a distinct segment label.
     *
     * @api
     */
    public const string IS_QUERY_BINDINGS_TRACKING_ENABLED = 'INSPECTOR:IS_QUERY_BINDINGS_TRACKING_ENABLED';
}
