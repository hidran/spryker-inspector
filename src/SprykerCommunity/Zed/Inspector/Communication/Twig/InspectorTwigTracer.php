<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Twig;

use SprykerCommunity\Service\Inspector\InspectorServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\Profiler\NodeVisitor\ProfilerNodeVisitor;
use Twig\Profiler\Profile;

/**
 * Reports each rendered template as a segment, mirroring
 * \Inspector\Symfony\Bundle\Twig\TwigTracer.
 *
 * Twig invokes enter()/leave() for every block and macro as well, but only templates become
 * segments: a Back Office page renders hundreds of blocks, which would exhaust the per-transaction
 * entry limit and bury everything else in the trace.
 */
class InspectorTwigTracer extends AbstractExtension
{
    protected const string SEGMENT_TYPE = 'view.twig';

    public function __construct(protected InspectorServiceInterface $inspectorService)
    {
    }

    public function enter(Profile $profile): void
    {
        $profile->enter();

        if (!$this->isTrackedProfile($profile) || !$this->inspectorService->canAddSegments()) {
            return;
        }

        $this->inspectorService->startSegment(static::SEGMENT_TYPE, $this->buildLabel($profile));
    }

    public function leave(Profile $profile): void
    {
        $profile->leave();

        if (!$this->isTrackedProfile($profile)) {
            return;
        }

        $this->inspectorService->endOpenSegment(static::SEGMENT_TYPE, $this->buildLabel($profile), [
            'template' => $profile->getTemplate(),
            'type' => $profile->getType(),
            'memory_usage' => $profile->getMemoryUsage(),
            'peak_memory_usage' => $profile->getPeakMemoryUsage(),
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, \Twig\NodeVisitor\NodeVisitorInterface>
     */
    public function getNodeVisitors(): array
    {
        return [new ProfilerNodeVisitor(static::class)];
    }

    protected function isTrackedProfile(Profile $profile): bool
    {
        return $profile->isRoot() || $profile->isTemplate();
    }

    /**
     * enter() and leave() must derive the same label from the same profile, because the label is
     * half of the key the segment is stored under while it is open.
     */
    protected function buildLabel(Profile $profile): string
    {
        if ($profile->isRoot()) {
            return $profile->getName();
        }

        return $profile->getTemplate();
    }
}
