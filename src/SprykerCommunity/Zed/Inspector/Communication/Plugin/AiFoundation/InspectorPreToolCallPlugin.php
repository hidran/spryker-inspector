<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation;

use Generated\Shared\Transfer\AiToolCallTransfer;
use Spryker\Zed\AiFoundation\Dependency\Plugin\PreToolCallPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * Opens an Inspector segment for a tool call, which InspectorPostToolCallPlugin closes.
 * Pairing the two plugins is what produces a real tool duration; the AiToolCall transfer
 * carries no timing of its own and must not be mutated to hold one.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorPreToolCallPlugin extends AbstractPlugin implements PreToolCallPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function preToolCall(AiToolCallTransfer $aiToolCallTransfer): AiToolCallTransfer
    {
        $this->getFactory()->getInspectorService()->startSegment(
            InspectorPostToolCallPlugin::SEGMENT_TYPE,
            (string)$aiToolCallTransfer->getToolName(),
        );

        return $aiToolCallTransfer;
    }
}
