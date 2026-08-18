<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Subscriber;

use Spryker\Zed\User\Business\UserFacadeInterface;
use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Breaks a Zed request into Inspector segments and resolves the transaction outcome from the
 * response, mirroring \Inspector\Symfony\Bundle\Listeners\KernelEventsSubscriber.
 *
 * The transaction is opened here rather than by the monitoring plugin because Spryker names
 * transactions on kernel.controller, by which point routing and session start have already run
 * unmeasured. The provisional name is replaced as soon as the monitoring plugin reports the real
 * "module/controller/action" name.
 */
class InspectorRequestEventSubscriber implements EventSubscriberInterface
{
    protected const string SEGMENT_TYPE_PROCESS = 'process';

    protected const string SEGMENT_TYPE_CONTROLLER = 'controller';

    protected const string SEGMENT_LABEL_REQUEST = 'kernel.request';

    protected const string SEGMENT_LABEL_RESPONSE = 'kernel.response';

    protected const string CONTEXT_KEY_RESPONSE = 'Response';

    protected const string UNKNOWN_VALUE = 'n/a';

    /**
     * Runs before the listeners that do the work, so their cost lands inside a segment.
     */
    protected const int PRIORITY_FIRST = 9999;

    /**
     * Runs after every other listener, so the response is final.
     */
    protected const int PRIORITY_LAST = -9999;

    protected ?string $controllerLabel = null;

    public function __construct(
        protected InspectorServiceInterface $inspectorService,
        protected UserFacadeInterface $userFacade,
    ) {
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', static::PRIORITY_FIRST],
            KernelEvents::CONTROLLER => ['onKernelController', static::PRIORITY_FIRST],
            KernelEvents::RESPONSE => ['onKernelResponse', static::PRIORITY_FIRST],
            KernelEvents::EXCEPTION => ['onKernelException', static::PRIORITY_FIRST],
            KernelEvents::TERMINATE => ['onKernelTerminate', static::PRIORITY_LAST],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->inspectorService->isRecording() || !$event->isMainRequest()) {
            return;
        }

        $this->inspectorService->ensureTransaction($this->buildProvisionalTransactionName($event->getRequest()));
        $this->inspectorService->markTransactionAsHttpRequest();
        $this->inspectorService->startSegment(static::SEGMENT_TYPE_PROCESS, static::SEGMENT_LABEL_REQUEST);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->inspectorService->canAddSegments() || !$event->isMainRequest()) {
            return;
        }

        $this->inspectorService->endOpenSegment(static::SEGMENT_TYPE_PROCESS, static::SEGMENT_LABEL_REQUEST);

        $this->controllerLabel = $this->buildControllerLabel($event->getRequest());
        $this->inspectorService->startSegment(static::SEGMENT_TYPE_CONTROLLER, $this->controllerLabel);

        $this->attachBackofficeUser();
    }

    /**
     * The session is only readable once routing and the session listener have run, which is why
     * this happens on kernel.controller rather than on kernel.request.
     */
    protected function attachBackofficeUser(): void
    {
        if (!$this->userFacade->hasCurrentUser()) {
            return;
        }

        $userTransfer = $this->userFacade->getCurrentUser();

        $this->inspectorService->setTransactionUser(
            (int)$userTransfer->getIdUser(),
            trim(sprintf('%s %s', (string)$userTransfer->getFirstName(), (string)$userTransfer->getLastName())) ?: null,
            $userTransfer->getUsername(),
        );
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->inspectorService->canAddSegments() || !$event->isMainRequest()) {
            return;
        }

        if ($this->controllerLabel !== null) {
            $this->inspectorService->endOpenSegment(static::SEGMENT_TYPE_CONTROLLER, $this->controllerLabel);
            $this->controllerLabel = null;
        }

        $response = $event->getResponse();

        $this->inspectorService->addTransactionContext(static::CONTEXT_KEY_RESPONSE, [
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('content-type'),
            'protocol_version' => $response->getProtocolVersion(),
        ]);

        $this->inspectorService->startSegment(static::SEGMENT_TYPE_PROCESS, static::SEGMENT_LABEL_RESPONSE);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->inspectorService->isRecording()) {
            return;
        }

        $throwable = $event->getThrowable();

        $this->inspectorService->reportError($throwable->getMessage(), $throwable);
    }

    /**
     * Reporting the status code as the transaction result is what lets the dashboard separate
     * failed requests from successful ones. Flushing explicitly rather than relying on the SDK
     * shutdown handler keeps long-running workers from accumulating unsent data.
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->inspectorService->isRecording()) {
            return;
        }

        $this->inspectorService->endOpenSegment(static::SEGMENT_TYPE_PROCESS, static::SEGMENT_LABEL_RESPONSE);
        $this->inspectorService->setTransactionResult((string)$event->getResponse()->getStatusCode());
        $this->inspectorService->flush();
    }

    /**
     * Spryker resolves module, controller and action into request attributes during routing,
     * which has not happened yet on kernel.request, so the path is all that identifies the
     * transaction until the monitoring plugin renames it.
     */
    protected function buildProvisionalTransactionName(Request $request): string
    {
        return sprintf('%s %s', $request->getMethod(), $request->getPathInfo());
    }

    protected function buildControllerLabel(Request $request): string
    {
        return sprintf(
            '%s/%s/%s',
            (string)$request->attributes->get('module', static::UNKNOWN_VALUE),
            (string)$request->attributes->get('controller', static::UNKNOWN_VALUE),
            (string)$request->attributes->get('action', static::UNKNOWN_VALUE),
        );
    }
}
