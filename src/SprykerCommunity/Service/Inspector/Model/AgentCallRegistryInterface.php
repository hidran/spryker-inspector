<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Model;

use Inspector\Models\Segment;

/**
 * Collects the segments recorded while an AI prompt is running, so they can be nested under the
 * segment that represents the call once it finishes.
 *
 * Inspector nests segments by the order they are opened and closed, but Spryker exposes no
 * pre-prompt extension point: the call is only observable after it has completed, by which time
 * its tool segments have already been opened and closed. They are therefore held here and
 * re-parented afterwards, which Inspector allows because segments are serialized at flush time.
 */
interface AgentCallRegistryInterface
{
    public function addToolSegment(Segment $segment): void;

    /**
     * Returns the segments collected since the last call and empties the registry, so the next
     * prompt in the same request starts clean.
     *
     * @return array<int, \Inspector\Models\Segment>
     */
    public function pullToolSegments(): array;
}
