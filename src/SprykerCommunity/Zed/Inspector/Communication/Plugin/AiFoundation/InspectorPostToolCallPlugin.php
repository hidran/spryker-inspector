<?php

/**
 * This file is part of the Spryker Commerce OS.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation;

use Generated\Shared\Transfer\AiToolCallTransfer;
use Spryker\Zed\AiFoundation\Dependency\Plugin\PostToolCallPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * Closes the Inspector segment opened by InspectorPreToolCallPlugin and attaches the
 * tool arguments and result to it.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorPostToolCallPlugin extends AbstractPlugin implements PostToolCallPluginInterface
{
    /**
     * Matches the tool segment type emitted by Inspector's own Neuron observer, so tool calls are
     * grouped with agent activity in the dashboard rather than appearing as generic segments.
     */
    public const string SEGMENT_TYPE = 'agent.tool';

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function postToolCall(AiToolCallTransfer $aiToolCallTransfer): void
    {
        $this->getFactory()->getInspectorService()->endOpenSegment(
            static::SEGMENT_TYPE,
            (string)$aiToolCallTransfer->getToolName(),
            [
                'arguments' => $aiToolCallTransfer->getToolArguments(),
                'result' => $aiToolCallTransfer->getToolResult(),
                'is_execution_allowed' => $aiToolCallTransfer->getIsExecutionAllowed(),
            ],
        );
    }
}
