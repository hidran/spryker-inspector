<?php

/**
 * MIT License
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
 * The segment is held after closing so that InspectorPostPromptPlugin can nest it under the
 * agent call it belongs to, which is what lets the dashboard present one call that opens up to
 * reveal its tools instead of listing tools alongside it.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorPostToolCallPlugin extends AbstractPlugin implements PostToolCallPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function postToolCall(AiToolCallTransfer $aiToolCallTransfer): void
    {
        $this->getFactory()->getInspectorService()->endAgentToolSegment(
            (string)$aiToolCallTransfer->getToolName(),
            [
                'Inputs' => $aiToolCallTransfer->getToolArguments(),
                'Output' => $aiToolCallTransfer->getToolResult(),
                'is_execution_allowed' => $aiToolCallTransfer->getIsExecutionAllowed(),
            ],
        );
    }
}
