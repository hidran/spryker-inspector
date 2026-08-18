<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Model;

use Inspector\Models\Segment;

class OpenSegmentRegistry implements OpenSegmentRegistryInterface
{
    /**
     * @var array<string, array<int, \Inspector\Models\Segment>>
     */
    protected array $segments = [];

    public function add(string $key, Segment $segment): void
    {
        $this->segments[$key][] = $segment;
    }

    public function pull(string $key): ?Segment
    {
        if (!isset($this->segments[$key]) || $this->segments[$key] === []) {
            return null;
        }

        return array_pop($this->segments[$key]);
    }

    public function clear(): void
    {
        $this->segments = [];
    }
}
