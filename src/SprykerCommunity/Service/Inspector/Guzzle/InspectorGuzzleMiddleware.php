<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Guzzle;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spryker\Service\Kernel\Locator;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use Throwable;

/**
 * Reports outbound Guzzle requests as "http.client" segments, the equivalent of
 * \Inspector\Symfony\Bundle\HttpClient\TraceableHttpClient.
 *
 * The Symfony bundle decorates one HttpClientInterface service and covers the whole application
 * with it. Spryker has no such service — spryker/guzzle is a metapackage with no code and every
 * module builds its own client — so this is a middleware that has to be pushed onto the handler
 * stack of the clients you want traced. See the README for the wiring.
 *
 * Segments are recorded when the promise settles rather than held open, so concurrent requests
 * cannot close each other's segments.
 */
class InspectorGuzzleMiddleware
{
    protected const string SEGMENT_TYPE = 'http.client';

    protected const float MILLISECONDS_PER_SECOND = 1000.0;

    protected bool $isResolutionAttempted = false;

    /**
     * The service is injected when this is built through the factory. Handler stacks are often
     * assembled in configuration files, where no container exists yet, so it is resolved lazily
     * when it was not supplied.
     */
    public function __construct(protected ?InspectorServiceInterface $inspectorService = null)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $inspectorService = $this->resolveInspectorService();

            if ($inspectorService === null || !$inspectorService->canAddSegments()) {
                return $handler($request, $options);
            }

            $startedAt = microtime(true);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($inspectorService, $request, $startedAt): ResponseInterface {
                    $this->recordRequest($inspectorService, $request, $startedAt, $response->getStatusCode());

                    return $response;
                },
                function ($reason) use ($inspectorService, $request, $startedAt): PromiseInterface {
                    $this->recordRequest(
                        $inspectorService,
                        $request,
                        $startedAt,
                        $this->extractStatusCode($reason),
                        $reason,
                    );

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    protected function recordRequest(
        InspectorServiceInterface $inspectorService,
        RequestInterface $request,
        float $startedAt,
        ?int $statusCode,
        mixed $reason = null,
    ): void {
        $uri = $request->getUri();

        $context = [
            'method' => $request->getMethod(),
            'url' => $this->buildUrl($inspectorService, $uri),
            'host' => $uri->getHost() . ($uri->getPort() !== null ? ':' . $uri->getPort() : ''),
            'status_code' => $statusCode,
        ];

        if ($reason instanceof Throwable) {
            $context['error'] = sprintf('%s: %s', $reason::class, $reason->getMessage());
        }

        try {
            $inspectorService->addCompletedSegment(
                static::SEGMENT_TYPE,
                $this->buildLabel($request, $uri),
                (microtime(true) - $startedAt) * static::MILLISECONDS_PER_SECOND,
                $context,
            );
        } catch (Throwable $throwable) {
            // Monitoring must never turn a working outbound call into a failing one.
        }
    }

    /**
     * The label deliberately carries no query string, so that requests to the same endpoint group
     * together in the dashboard instead of appearing as one entry per parameter combination.
     */
    protected function buildLabel(RequestInterface $request, UriInterface $uri): string
    {
        return sprintf(
            '%s %s://%s%s',
            $request->getMethod(),
            $uri->getScheme(),
            $uri->getHost(),
            $uri->getPath(),
        );
    }

    /**
     * Credentials in the userinfo component are always removed. Query strings are dropped as well
     * unless explicitly enabled, because they routinely carry API keys, tokens and personal data
     * that would otherwise be transmitted to Inspector.
     */
    protected function buildUrl(InspectorServiceInterface $inspectorService, UriInterface $uri): string
    {
        $safeUri = $uri->withUserInfo('');

        if (!$inspectorService->isHttpQueryTrackingEnabled()) {
            $safeUri = $safeUri->withQuery('');
        }

        return (string)$safeUri;
    }

    protected function extractStatusCode(mixed $reason): ?int
    {
        if (!$reason instanceof RequestException) {
            return null;
        }

        return $reason->getResponse()?->getStatusCode();
    }

    protected function resolveInspectorService(): ?InspectorServiceInterface
    {
        if ($this->inspectorService !== null || $this->isResolutionAttempted) {
            return $this->inspectorService;
        }

        $this->isResolutionAttempted = true;

        try {
            /** @var \SprykerCommunity\Service\Inspector\InspectorServiceInterface $inspectorService */
            $inspectorService = Locator::getInstance()->inspector()->service();
        } catch (Throwable $throwable) {
            return null;
        }

        if (!$inspectorService->isHttpClientTrackingEnabled()) {
            return null;
        }

        $this->inspectorService = $inspectorService;

        return $this->inspectorService;
    }
}
