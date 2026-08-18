<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Model;

use Inspector\Models\Segment;

/**
 * Holds segments that were opened at one observation point and are closed at another,
 * such as a pre/post plugin pair around an AI tool call.
 *
 * Inspector::getOpenSegments() only exposes informational arrays rather than the Segment
 * objects themselves, so the objects have to be kept here to be able to end them later.
 */
interface OpenSegmentRegistryInterface
{
    public function add(string $key, Segment $segment): void;

    /**
     * Removes and returns the most recently added segment for the key, so that nested
     * calls using the same key are closed in reverse order.
     */
    public function pull(string $key): ?Segment;
}
