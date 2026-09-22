<?php

/**
 * Xiaomi MiMo text generation model class file.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Models;

use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model for Xiaomi MiMo chat models.
 *
 * Everything below the request body — message mapping, vision input, tool calls, response parsing,
 * reasoning content, token usage — is handled by the SDK base class. Only MiMo's request shape quirks
 * need overriding.
 */
class XiaomimimoTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    use XiaomimimoRequestTrait;

    /**
     * {@inheritDoc}
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);

        /*
         * An empty array signals "send no response_format" (see prepareResponseFormatParam()); leaving
         * the key in place would send `"response_format": []`, which is a 400.
         */
        if (isset($params['response_format']) && $params['response_format'] === []) {
            unset($params['response_format']);
        }

        /*
         * MiMo documents the completion cap as `max_completion_tokens`; the SDK base class emits the
         * legacy `max_tokens` name. Rename it so the model actually receives the cap.
         */
        if (isset($params['max_tokens'])) {
            $params['max_completion_tokens'] = $params['max_tokens'];
            unset($params['max_tokens']);
        }

        /*
         * Thinking is on by default upstream and returns `reasoning_content` plus (in thinking mode)
         * ignores `temperature`/`top_p`. `default` leaves that alone; the other two values are passed
         * through so a deployment can turn it off for cheaper, faster, temperature-respecting calls.
         */
        $thinking = XiaomimimoConfig::getThinkingMode();
        if ($thinking === 'enabled' || $thinking === 'disabled') {
            $params['thinking'] = ['type' => $thinking];
        }

        return $params;
    }

    /**
     * {@inheritDoc}
     *
     * Shapes the request for structured output.
     *
     * MiMo implements OpenAI's JSON mode only: `response_format` accepts `{"type":"json_object"}` and
     * rejects a JSON schema. The SDK base class would send `{"type":"json_schema","json_schema":...}`
     * — which is a 400 here — so the schema is dropped and only JSON mode is requested. The calling
     * feature's prompt is still responsible for describing the fields it wants.
     *
     * @param array<string, mixed>|null $outputSchema The output schema, ignored by MiMo.
     * @return array<string, mixed> The response format parameter, or an empty array to send none.
     */
    protected function prepareResponseFormatParam(?array $outputSchema): array
    {
        if (XiaomimimoConfig::getStructuredOutputMode() === 'none') {
            return [];
        }

        return ['type' => 'json_object'];
    }
}
