<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Persistence\Propel;

use Propel\Runtime\Connection\StatementWrapper;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use Throwable;

/**
 * Times each executed statement and reports it as a segment.
 *
 * Created by InspectorConnectionWrapper, which owns the Inspector service this reads back.
 */
class InspectorStatementWrapper extends StatementWrapper
{
    protected const string SEGMENT_TYPE = 'db.propel';

    /**
     * Long statements are truncated in the label so the dashboard stays readable; the full
     * statement is always kept in the segment context.
     */
    protected const int LABEL_MAX_LENGTH = 120;

    protected const string LABEL_SUFFIX = '...';

    protected const float MILLISECONDS_PER_SECOND = 1000.0;

    protected const string UNKNOWN_VALUE = 'undefined';

    /**
     * @var array<string, string>
     */
    protected const array OPERATION_KEYWORDS = [
        'SELECT' => 'FROM',
        'INSERT' => 'INTO',
        'UPDATE' => 'UPDATE',
        'DELETE' => 'FROM',
    ];

    /**
     * @param array<int|string, mixed>|null $inputParameters
     */
    public function execute(?array $inputParameters = null): bool
    {
        $inspectorService = $this->resolveInspectorService();

        if ($inspectorService === null || !$inspectorService->canAddSegments()) {
            return parent::execute($inputParameters);
        }

        $startedAt = microtime(true);

        try {
            return parent::execute($inputParameters);
        } finally {
            $this->recordStatement(
                $inspectorService,
                $this->buildStatement($inspectorService, $inputParameters),
                (microtime(true) - $startedAt) * static::MILLISECONDS_PER_SECOND,
            );
        }
    }

    public function query(): DataFetcherInterface
    {
        $inspectorService = $this->resolveInspectorService();

        if ($inspectorService === null || !$inspectorService->canAddSegments()) {
            return parent::query();
        }

        $startedAt = microtime(true);

        try {
            return parent::query();
        } finally {
            $this->recordStatement(
                $inspectorService,
                $this->sql,
                (microtime(true) - $startedAt) * static::MILLISECONDS_PER_SECOND,
            );
        }
    }

    /**
     * @param array<int|string, mixed>|null $inputParameters
     */
    protected function buildStatement(InspectorServiceInterface $inspectorService, ?array $inputParameters): string
    {
        if (!$inspectorService->isQueryBindingsTrackingEnabled()) {
            return $this->sql;
        }

        try {
            return $this->getExecutedQueryString($inputParameters);
        } catch (Throwable $throwable) {
            return $this->sql;
        }
    }

    protected function recordStatement(
        InspectorServiceInterface $inspectorService,
        string $statement,
        float $durationInMilliseconds,
    ): void {
        if ($durationInMilliseconds < $inspectorService->getPropelSlowQueryThresholdMilliseconds()) {
            return;
        }

        try {
            $inspectorService->addCompletedSegment(
                static::SEGMENT_TYPE,
                $this->buildLabel($statement),
                $durationInMilliseconds,
                ['sql' => $statement] + $this->buildStatementContext($statement),
            );
        } catch (Throwable $throwable) {
            $this->disableTracking();
        }
    }

    protected function buildLabel(string $statement): string
    {
        $normalizedStatement = (string)preg_replace('/\s+/', ' ', trim($statement));

        if (mb_strlen($normalizedStatement) <= static::LABEL_MAX_LENGTH) {
            return $normalizedStatement;
        }

        return mb_substr($normalizedStatement, 0, static::LABEL_MAX_LENGTH) . static::LABEL_SUFFIX;
    }

    /**
     * Operation and table are what make the segments groupable in the dashboard, so they are
     * derived here rather than left to be read out of the statement by eye.
     *
     * @return array<string, string>
     */
    protected function buildStatementContext(string $statement): array
    {
        $normalizedStatement = ltrim(str_replace('`', '', $statement));

        foreach (static::OPERATION_KEYWORDS as $operation => $keyword) {
            if (stripos($normalizedStatement, $operation) !== 0) {
                continue;
            }

            return [
                'operation' => $operation,
                'table' => $this->extractTableName($normalizedStatement, $keyword),
            ];
        }

        return ['operation' => static::UNKNOWN_VALUE, 'table' => static::UNKNOWN_VALUE];
    }

    protected function extractTableName(string $statement, string $keyword): string
    {
        // Dots are part of the name so that schema-qualified tables stay intact, e.g.
        // "information_schema.tables" rather than "information_schema".
        $isMatched = preg_match(sprintf('/\b%s\s+([\w.-]+)/i', preg_quote($keyword, '/')), $statement, $matches);

        if (!$isMatched) {
            return static::UNKNOWN_VALUE;
        }

        return $matches[1];
    }

    protected function resolveInspectorService(): ?InspectorServiceInterface
    {
        if (!$this->connection instanceof InspectorConnectionWrapper) {
            return null;
        }

        return $this->connection->getInspectorService();
    }

    protected function disableTracking(): void
    {
        if (!$this->connection instanceof InspectorConnectionWrapper) {
            return;
        }

        $this->connection->disableTracking();
    }
}
