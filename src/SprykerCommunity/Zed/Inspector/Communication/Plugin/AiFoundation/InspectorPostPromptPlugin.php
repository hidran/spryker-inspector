<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation;

use Generated\Shared\Transfer\PromptRequestTransfer;
use Generated\Shared\Transfer\PromptResponseTransfer;
use Spryker\Zed\AiFoundation\Dependency\Plugin\PostPromptPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * Records each completed AI prompt as an Inspector segment inside the surrounding
 * Zed transaction. The segment is backdated by PromptResponse.inferenceTimeMs, which is
 * the only timing Spryker exposes for a prompt: there is no pre-prompt extension point.
 *
 * @method \SprykerCommunity\Zed\Inspector\Communication\InspectorCommunicationFactory getFactory()
 */
class InspectorPostPromptPlugin extends AbstractPlugin implements PostPromptPluginInterface
{
    /**
     * Inspector classifies AI activity by the "agent." segment type prefix used by its own
     * Neuron observer (\Inspector\Neuron\InspectorObserver::SEGMENT_TYPE). Segments outside that
     * namespace still appear in the trace, but not under the dashboard's Agent section.
     */
    protected const string SEGMENT_TYPE = 'agent.inference';

    protected const string UNKNOWN_VALUE = 'unknown';

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function postPrompt(
        PromptRequestTransfer $promptRequestTransfer,
        PromptResponseTransfer $promptResponseTransfer,
    ): void {
        $this->getFactory()->getInspectorService()->addCompletedSegment(
            static::SEGMENT_TYPE,
            $this->buildLabel($promptResponseTransfer),
            (float)$promptResponseTransfer->getInferenceTimeMs(),
            $this->buildContext($promptRequestTransfer, $promptResponseTransfer),
        );

        $this->recordTokenUsage($promptResponseTransfer);
    }

    /**
     * Token usage is reported as a dedicated Inspector Token entry, which is what its AI
     * token and cost reporting consumes. The counts in the segment context are for the trace view.
     */
    protected function recordTokenUsage(PromptResponseTransfer $promptResponseTransfer): void
    {
        $usageTransfer = $promptResponseTransfer->getMessage()?->getUsage();

        if ($usageTransfer === null) {
            return;
        }

        $this->getFactory()->getInspectorService()->recordAiTokenUsage(
            $this->buildLabel($promptResponseTransfer),
            (int)$usageTransfer->getInputTokens(),
            (int)$usageTransfer->getOutputTokens(),
        );
    }

    protected function buildLabel(PromptResponseTransfer $promptResponseTransfer): string
    {
        return sprintf(
            '%s/%s',
            $promptResponseTransfer->getProvider() ?? static::UNKNOWN_VALUE,
            $promptResponseTransfer->getModel() ?? static::UNKNOWN_VALUE,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(
        PromptRequestTransfer $promptRequestTransfer,
        PromptResponseTransfer $promptResponseTransfer,
    ): array {
        $usageTransfer = $promptResponseTransfer->getMessage()?->getUsage();

        return [
            'configuration' => $promptRequestTransfer->getAiConfigurationName(),
            'provider' => $promptResponseTransfer->getProvider(),
            'model' => $promptResponseTransfer->getModel(),
            'is_successful' => $promptResponseTransfer->getIsSuccessful(),
            'tool_sets' => $promptRequestTransfer->getToolSetNames(),
            'input_tokens' => $usageTransfer?->getInputTokens(),
            'output_tokens' => $usageTransfer?->getOutputTokens(),
            'errors' => $this->extractErrorMessages($promptResponseTransfer),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    protected function extractErrorMessages(PromptResponseTransfer $promptResponseTransfer): array
    {
        $errorMessages = [];

        foreach ($promptResponseTransfer->getErrors() as $errorTransfer) {
            $errorMessages[] = $errorTransfer->getMessage();
        }

        return $errorMessages;
    }
}
