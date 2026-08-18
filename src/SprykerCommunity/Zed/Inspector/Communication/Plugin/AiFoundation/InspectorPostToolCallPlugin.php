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
    public const string SEGMENT_TYPE = 'ai-tool';

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
