<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector;

use GuzzleHttp\HandlerStack;
use Inspector\Configuration;
use Inspector\Inspector;
use Inspector\Models\Token;
use Inspector\Models\Transaction;
use Spryker\Service\Kernel\AbstractService;
use Throwable;

/**
 * @method \SprykerCommunity\Service\Inspector\InspectorServiceFactory getFactory()
 */
class InspectorService extends AbstractService implements InspectorServiceInterface
{
    protected const float MILLISECONDS_PER_SECOND = 1000.0;

    /**
     * @var array<int, string>
     */
    protected const array SENSITIVE_HEADER_NAMES = [
        'cookie',
        'set-cookie',
        'authorization',
        'proxy-authorization',
        'x-auth-token',
        'x-api-key',
    ];

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getInspector(): Inspector
    {
        return $this->getFactory()->getInspector();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getInspectorConfiguration(): Configuration
    {
        return $this->getFactory()->getInspectorConfiguration();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isRecording(): bool
    {
        return $this->getFactory()->getInspector()->isRecording();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function canAddSegments(): bool
    {
        return $this->getFactory()->getInspector()->canAddSegments();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isCommandIgnored(string $commandName): bool
    {
        return $this->getFactory()->getConfig()->isCommandIgnored($commandName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isTwigTrackingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isTwigTrackingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isPropelTrackingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isPropelTrackingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getPropelSlowQueryThresholdMilliseconds(): float
    {
        return $this->getFactory()->getConfig()->getPropelSlowQueryThresholdMilliseconds();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isQueryBindingsTrackingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isQueryBindingsTrackingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isHttpClientTrackingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isHttpClientTrackingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isHttpQueryTrackingEnabled(): bool
    {
        return $this->getFactory()->getConfig()->isHttpQueryTrackingEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function createGuzzleHandlerStack(?HandlerStack $handlerStack = null): HandlerStack
    {
        $handlerStack = $handlerStack ?? HandlerStack::create();

        $handlerStack->push($this->getFactory()->createGuzzleMiddleware());

        return $handlerStack;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function ensureTransaction(string $name): void
    {
        $inspector = $this->getFactory()->getInspector();

        if (!$inspector->isRecording() || $inspector->hasTransaction()) {
            return;
        }

        $inspector->startTransaction($name);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setTransactionName(string $name): void
    {
        $this->ensureTransaction($name);

        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null) {
            return;
        }

        $transaction->name = $name;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setTransactionType(string $type): void
    {
        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null) {
            return;
        }

        $transaction->setType($type);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function markTransactionAsHttpRequest(): void
    {
        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null || $transaction->http !== null) {
            return;
        }

        $transaction->markAsRequest();

        $this->removeSensitiveRequestData($transaction);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setTransactionUser(int|string $userId, ?string $name = null, ?string $email = null): void
    {
        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null) {
            return;
        }

        $transaction->withUser($userId, $name, $email);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function setTransactionResult(string $result): void
    {
        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null) {
            return;
        }

        $transaction->setResult($result);
    }

    /**
     * Transaction::markAsRequest() captures apache_request_headers() and $_COOKIE verbatim,
     * which in Zed includes the Back Office session cookie. Those must not be shipped.
     */
    protected function removeSensitiveRequestData(Transaction $transaction): void
    {
        if ($transaction->http === null) {
            return;
        }

        $transaction->http->request->cookies = [];

        $headers = $transaction->http->request->headers;

        if ($headers === null) {
            return;
        }

        foreach (array_keys($headers) as $headerName) {
            if (!in_array(strtolower((string)$headerName), static::SENSITIVE_HEADER_NAMES, true)) {
                continue;
            }

            unset($headers[$headerName]);
        }

        $transaction->http->request->headers = $headers;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function addTransactionContext(string $key, mixed $value): void
    {
        $transaction = $this->getFactory()->getInspector()->transaction();

        if ($transaction === null) {
            return;
        }

        $transaction->addContext($key, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function reportError(string $message, Throwable $throwable): void
    {
        $inspector = $this->getFactory()->getInspector();

        if (!$inspector->isRecording()) {
            return;
        }

        $this->ensureTransaction($message);
        $inspector->reportException($throwable);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function ignoreTransaction(): void
    {
        $this->getFactory()->getInspector()->reset();

        // Segments opened against the discarded transaction can never be ended meaningfully,
        // and would otherwise be closed against whatever transaction is opened next.
        $this->getFactory()->getOpenSegmentRegistry()->clear();
    }

    /**
     * {@inheritDoc}
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
    ): void {
        $inspector = $this->getFactory()->getInspector();

        if (!$inspector->canAddSegments()) {
            return;
        }

        $segment = $inspector->startSegment($type, $label);
        $segment->start(microtime(true) - ($durationInMilliseconds / static::MILLISECONDS_PER_SECOND));

        foreach ($context as $contextKey => $contextValue) {
            $segment->addContext($contextKey, $contextValue);
        }

        $segment->end($durationInMilliseconds);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function startSegment(string $type, string $label): void
    {
        $inspector = $this->getFactory()->getInspector();

        if (!$inspector->canAddSegments()) {
            return;
        }

        $this->getFactory()->getOpenSegmentRegistry()->add(
            $this->buildSegmentKey($type, $label),
            $inspector->startSegment($type, $label),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed> $context
     */
    public function endOpenSegment(string $type, string $label, array $context = []): void
    {
        $segment = $this->getFactory()->getOpenSegmentRegistry()->pull($this->buildSegmentKey($type, $label));

        if ($segment === null) {
            return;
        }

        foreach ($context as $contextKey => $contextValue) {
            $segment->addContext($contextKey, $contextValue);
        }

        $segment->end();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function recordAiTokenUsage(string $agent, int $inputTokens, int $outputTokens): void
    {
        $inspector = $this->getFactory()->getInspector();
        $transaction = $inspector->transaction();

        if (!$inspector->isRecording() || $transaction === null) {
            return;
        }

        $token = (new Token($transaction))
            ->setInputTokens($inputTokens)
            ->setOutputTokens($outputTokens);

        $inspector->addEntries($token->setAgent($agent));
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function flush(): void
    {
        $this->getFactory()->getInspector()->flush();
    }

    protected function buildSegmentKey(string $type, string $label): string
    {
        return sprintf('%s::%s', $type, $label);
    }
}
