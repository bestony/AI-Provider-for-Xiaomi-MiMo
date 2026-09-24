<?php

/**
 * Xiaomi MiMo provider class file.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Provider;

use XiaomiMiMo\AiProvider\Metadata\XiaomimimoModelMetadataDirectory;
use XiaomiMiMo\AiProvider\Models\XiaomimimoTextGenerationModel;
use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the Xiaomi MiMo provider.
 *
 * MiMo serves an OpenAI-compatible API: `POST /chat/completions` for text and multimodal
 * understanding, and `GET /models` to list models. Both accept `api-key` and
 * `Authorization: Bearer <key>`; the SDK's default API key authentication sends the latter, so no
 * custom authentication class is needed.
 */
class XiaomimimoProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @return string The base URL for the Xiaomi MiMo API.
     */
    protected static function baseUrl(): string
    {
        return XiaomimimoConfig::getBaseUrl();
    }

    /**
     * {@inheritDoc}
     *
     * @param ModelMetadata $modelMetadata The model metadata.
     * @param ProviderMetadata $providerMetadata The provider metadata.
     * @return ModelInterface The model instance.
     * @throws RuntimeException If the model has no supported capability for this provider.
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if (!$capability->isTextGeneration()) {
                continue;
            }

            $model = new XiaomimimoTextGenerationModel($modelMetadata, $providerMetadata);

            /*
             * MiMo requests, especially with thinking enabled, routinely run for tens of seconds.
             * Without this the request is sent with WordPress' 5 second default and times out.
             */
            $model->setRequestOptions(XiaomimimoConfig::createRequestOptions());

            return $model;
        }

        throw new RuntimeException(
            sprintf(
                /* translators: %s: model ID. */
                esc_html__('The model "%s" has no supported capability for Xiaomi MiMo.', 'bestony-ai-provider-for-xiaomi-mimo'),
                esc_html($modelMetadata->getId())
            )
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return ProviderMetadata The provider metadata.
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $args = [
            XiaomimimoConfig::PROVIDER_ID,
            'Xiaomi MiMo',
            ProviderTypeEnum::cloud(),
            'https://platform.xiaomimimo.com/console/api-key',
            RequestAuthenticationMethod::apiKey(),
        ];

        // Provider description support was added in SDK 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            $description = 'Text and multimodal understanding with Xiaomi MiMo (小米 MiMo) models.';
            $args[] = function_exists('__')
                ? __('Text and multimodal understanding with Xiaomi MiMo (小米 MiMo) models.', 'bestony-ai-provider-for-xiaomi-mimo')
                : $description;
        }

        // Provider logoPath support was added in SDK 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $args[] = dirname(__DIR__, 2) . '/assets/images/xiaomimimo.svg';
        }

        return new ProviderMetadata(...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @return ProviderAvailabilityInterface The provider availability check.
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Valid credentials are confirmed by listing models, which requires the API key.
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }

    /**
     * {@inheritDoc}
     *
     * @return ModelMetadataDirectoryInterface The model metadata directory.
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new XiaomimimoModelMetadataDirectory();
    }
}
