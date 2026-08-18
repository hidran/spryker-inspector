<?php

/**
 * This file is part of the Spryker Commerce OS.
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
}
