<?php

/**
 * Xiaomi MiMo model metadata directory class file.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Metadata;

use XiaomiMiMo\AiProvider\Provider\XiaomimimoProvider;
use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use XiaomiMiMo\AiProvider\Util\XiaomimimoModelCatalog;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the Xiaomi MiMo model metadata directory.
 *
 * MiMo's `/models` endpoint returns `{id, object, owned_by}` and no capability information, so
 * capabilities and options are declared here. That declaration is the single source of truth: the SDK
 * decides which model may serve a request by matching it against these values, so under-declaring
 * makes a model unusable and over-declaring turns into a 400 from the upstream.
 *
 * Only options MiMo documents are declared. `n`, `logprobs`, `top_logprobs`, `top_k` and `web_search`
 * are absent from the Chat Completions reference and are therefore not declared, even though the
 * OpenAI-compatible base class would happily send some of them.
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string, object?: string, owned_by?: string}>
 * }
 */
class XiaomimimoModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URL.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        /*
         * The base class does not pass request options here, so without this the model list request
         * is sent with WordPress' 5 second HTTP default.
         */
        return new Request(
            $method,
            XiaomimimoProvider::url($path),
            $headers,
            $data,
            XiaomimimoConfig::createRequestOptions()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param Response $response The response from the API endpoint to list models.
     * @return list<ModelMetadata> List of model metadata objects.
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !is_array($responseData['data']) || !$responseData['data']) {
            throw ResponseException::fromMissingData('Xiaomi MiMo', 'data');
        }

        $preferredModelId = XiaomimimoConfig::getDefaultModelId();

        $models = [];
        foreach ($responseData['data'] as $modelData) {
            if (!is_array($modelData) || !isset($modelData['id']) || !is_string($modelData['id'])) {
                continue;
            }

            $modelId = $modelData['id'];

            if (XiaomimimoModelCatalog::isTextModel($modelId)) {
                $capabilities = [
                    CapabilityEnum::textGeneration(),
                    CapabilityEnum::chatHistory(),
                ];
                $options = $this->createTextOptions($modelId);
            } else {
                /*
                 * The speech (ASR/TTS) families and any future non-chat model. The model stays in the
                 * list — so it is visible and does not look like the API is hiding something — but
                 * with no capability it can never be selected for a request.
                 */
                $capabilities = [];
                $options = [];
            }

            $models[] = new ModelMetadata($modelId, $modelId, $capabilities, $options);
        }

        usort(
            $models,
            static function (ModelMetadata $a, ModelMetadata $b) use ($preferredModelId): int {
                // An explicitly configured model is pinned to the top of every picker.
                if ($preferredModelId !== '') {
                    $aPreferred = $a->getId() === $preferredModelId ? 0 : 1;
                    $bPreferred = $b->getId() === $preferredModelId ? 0 : 1;
                    if ($aPreferred !== $bPreferred) {
                        return $aPreferred <=> $bPreferred;
                    }
                }

                return XiaomimimoModelCatalog::compareModelIds($a->getId(), $b->getId());
            }
        );

        return $models;
    }

    /**
     * Builds the supported options for a text generation model.
     *
     * @param string $modelId The model ID.
     * @return list<SupportedOption> The supported options.
     */
    private function createTextOptions(string $modelId): array
    {
        $inputModalities = [[ModalityEnum::text()]];
        // Either the catalog knows this model takes images, or the deployment asserts it does.
        if (
            XiaomimimoModelCatalog::supportsImageInput($modelId)
            || XiaomimimoConfig::declaresImageInput()
        ) {
            $inputModalities[] = [ModalityEnum::text(), ModalityEnum::image()];
        }

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            /*
             * MiMo only implements JSON mode, not schema-constrained output. `outputSchema` is still
             * declared so the AI plugin's JSON features route to MiMo at all; the model downgrades the
             * request to `json_object` (see XiaomimimoTextGenerationModel). Without this declaration
             * those features would report "no available model".
             */
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::presencePenalty()),
        ];

        return $options;
    }
}
