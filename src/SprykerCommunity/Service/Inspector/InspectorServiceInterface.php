<?php

/**
 * This file is part of the Spryker Commerce OS.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector;

use Inspector\Configuration;
use Inspector\Inspector;
use Throwable;

interface InspectorServiceInterface
{
    /**
     * Specification:
     * - Returns the process-wide Inspector client.
     * - Interoperability escape hatch for packaged Inspector components that require the SDK
     *   object itself, such as the bundled inspector:test command. Prefer the methods below.
     *
     * @api
     */
    public function getInspector(): Inspector;

    /**
     * Specification:
     * - Returns the Configuration backing the process-wide Inspector client.
     * - Interoperability escape hatch, see getInspector().
     *
     * @api
     */
    public function getInspectorConfiguration(): Configuration;

    /**
     * Specification:
     * - Returns true when an ingestion key is configured and monitoring is enabled.
     *
     * @api
     */
    public function isRecording(): bool;

    /**
     * Specification:
     * - Opens a transaction when none is open yet, otherwise does nothing.
     * - Required before any segment can be recorded.
     *
     * @api
     */
    public function ensureTransaction(string $name): void;

    /**
     * Specification:
     * - Opens a transaction when needed and renames the currently open one.
     *
     * @api
     */
    public function setTransactionName(string $name): void;

    /**
     * Specification:
     * - Sets the type of the currently open transaction, e.g. request or console.
     *
     * @api
     */
    public function setTransactionType(string $type): void;

    /**
     * Specification:
     * - Marks the open transaction as an HTTP request, attaching method, URL and headers.
     * - Strips cookies and authorization headers before they can leave the application.
     *
     * @api
     */
    public function markTransactionAsHttpRequest(): void;

    /**
     * Specification:
     * - Sets the outcome of the open transaction, e.g. success or error.
     *
     * @api
     */
    public function setTransactionResult(string $result): void;

    /**
     * Specification:
     * - Attaches a key-value pair to the currently open transaction.
     *
     * @api
     */
    public function addTransactionContext(string $key, mixed $value): void;

    /**
     * Specification:
     * - Reports a throwable to Inspector against the currently open transaction.
     *
     * @api
     */
    public function reportError(string $message, Throwable $throwable): void;

    /**
     * Specification:
     * - Discards the currently open transaction and everything recorded for it.
     *
     * @api
     */
    public function ignoreTransaction(): void;

    /**
     * Specification:
     * - Records an already-completed unit of work as a segment with a known duration.
     * - The segment is backdated by the given duration so it lines up with when the work ran.
     * - Does nothing when monitoring is off or no transaction is open.
     *
     * @api
     *
     * @param array<string, mixed> $context
     */
    public function addCompletedSegment(
        string $type,
        string $label,
        float $durationInMilliseconds,
        array $context = [],
    ): void;

    /**
     * Specification:
     * - Opens a segment that stays on Inspector's open-segment stack until it is ended.
     * - Use when start and end are observed at two separate points, e.g. a pre/post plugin pair.
     * - Does nothing when monitoring is off or no transaction is open.
     *
     * @api
     */
    public function startSegment(string $type, string $label): void;

    /**
     * Specification:
     * - Ends the most recently opened segment matching the given type and label.
     * - Does nothing when no matching segment is open.
     *
     * @api
     *
     * @param array<string, mixed> $context
     */
    public function endOpenSegment(string $type, string $label, array $context = []): void;

    /**
     * Specification:
     * - Records LLM token usage as an Inspector Token entry against the open transaction.
     * - This is what feeds Inspector's AI token and cost reporting; token counts placed only
     *   in segment context are not picked up by it.
     * - Does nothing when monitoring is off or no transaction is open.
     *
     * @api
     */
    public function recordAiTokenUsage(string $agent, int $inputTokens, int $outputTokens): void;

    /**
     * Specification:
     * - Sends everything recorded so far to Inspector and closes the transaction.
     *
     * @api
     */
    public function flush(): void;
}
