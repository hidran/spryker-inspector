<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Service\Inspector\Model;

use Inspector\Models\Segment;

class AgentCallRegistry implements AgentCallRegistryInterface
{
    /**
     * @var array<int, \Inspector\Models\Segment>
     */
    protected array $toolSegments = [];

    public function addToolSegment(Segment $segment): void
    {
        $this->toolSegments[] = $segment;
    }

    /**
     * @return array<int, \Inspector\Models\Segment>
     */
    public function pullToolSegments(): array
    {
        $toolSegments = $this->toolSegments;

        $this->toolSegments = [];

        return $toolSegments;
    }
}
