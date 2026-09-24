<?php

/**
 * Plugin configuration reader.
 *
 * Loads outside WordPress (the self-check exercises it that way), so every WordPress call is guarded.
 * Values are resolved as: environment variable > PHP constant > stored option > built-in default.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Util;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Reads the plugin's optional configuration.
 */
final class XiaomimimoConfig
{
    /**
     * The plugin version, reported in the User-Agent header.
     *
     * @var string
     */
    public const VERSION = '1.0.1';

    /**
     * The option the Settings → Bestony AI Provider for Xiaomi MiMo page stores the chosen base URL in.
     *
     * @var string
     */
    public const OPTION_BASE_URL = 'xiaomimimo_base_url';

    /**
     * The option holding the thinking-mode choice.
     *
     * @var string
     */
    public const OPTION_THINKING = 'xiaomimimo_thinking';

    /**
     * The option holding the structured-output choice.
     *
     * @var string
     */
    public const OPTION_STRUCTURED_OUTPUT = 'xiaomimimo_structured_output';

    /**
     * Base URL of the pay-as-you-go Xiaomi MiMo API.
     *
     * @var string
     */
    public const BASE_URL_STANDARD = 'https://api.xiaomimimo.com/v1';

    /**
     * Base URL of the Token Plan API (mainland-China cluster).
     *
     * @var string
     */
    public const BASE_URL_TOKEN_PLAN_CN = 'https://token-plan-cn.xiaomimimo.com/v1';

    /**
     * Base URL of the Token Plan API (Singapore cluster).
     *
     * @var string
     */
    public const BASE_URL_TOKEN_PLAN_SGP = 'https://token-plan-sgp.xiaomimimo.com/v1';

    /**
     * Base URL of the Token Plan API (Europe cluster).
     *
     * @var string
     */
    public const BASE_URL_TOKEN_PLAN_AMS = 'https://token-plan-ams.xiaomimimo.com/v1';

    /**
     * The base URL used when nothing is configured.
     *
     * @var string
     */
    public const DEFAULT_BASE_URL = self::BASE_URL_STANDARD;

    /**
     * The provider ID used by the SDK registry, the Connectors option name and the filter tuples.
     *
     * Frozen: it decides `connectors_ai_xiaomimimo_api_key`, `XIAOMIMIMO_API_KEY` and the value users
     * pass to model preference filters. Changing it drops every stored API key.
     *
     * @var string
     */
    public const PROVIDER_ID = 'xiaomimimo';

    /**
     * Model pushed to the front of the list and used for the AI plugin's preference filters.
     *
     * A static default: MiMo's model list endpoint reports no capability data, so there is nothing to
     * compute a "best" model from. Override with the `XIAOMIMIMO_DEFAULT_MODEL` environment variable
     * or constant.
     *
     * @var string
     */
    public const DEFAULT_MODEL = 'mimo-v2.6-pro';

    /**
     * The thinking-mode choice that leaves the model's own default alone.
     *
     * @var string
     */
    public const THINKING_DEFAULT = 'default';

    /**
     * The structured-output choice used when nothing is configured.
     *
     * @var string
     */
    public const STRUCTURED_OUTPUT_DEFAULT = 'json_object';

    /**
     * The accepted thinking-mode values.
     *
     * `default` sends no `thinking` parameter at all; the other two map straight onto MiMo's
     * `thinking.type` values.
     *
     * @var list<string>
     */
    public const THINKING_MODES = [self::THINKING_DEFAULT, 'enabled', 'disabled'];

    /**
     * The accepted structured-output values.
     *
     * MiMo only implements OpenAI's JSON mode (`json_object`); `json_schema` is not supported, so it
     * is not offered. `none` sends no `response_format` at all.
     *
     * @var list<string>
     */
    public const STRUCTURED_OUTPUT_MODES = ['json_object', 'none'];

    /**
     * Resolves configuration from an environment variable or a PHP constant.
     *
     * @param string $name The variable/constant name.
     * @return string The value, or an empty string when unset.
     */
    public static function env(string $name): string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (defined($name)) {
            $constant = constant($name);
            if (is_scalar($constant)) {
                return (string) $constant;
            }
        }

        return '';
    }

    /**
     * Reads a stored option, if WordPress and a string value are available.
     *
     * Guarded so this class keeps working outside WordPress (see the file docblock).
     *
     * @param string $name The option name.
     * @return string The stored value, or an empty string when there is none.
     */
    private static function getStoredOption(string $name): string
    {
        if (!function_exists('get_option')) {
            return '';
        }

        $value = get_option($name, '');

        return is_string($value) ? $value : '';
    }

    /**
     * Gets the base URLs the settings page offers.
     *
     * @return list<string> The allowed base URLs.
     */
    public static function getBaseUrlChoices(): array
    {
        return [
            self::BASE_URL_STANDARD,
            self::BASE_URL_TOKEN_PLAN_CN,
            self::BASE_URL_TOKEN_PLAN_SGP,
            self::BASE_URL_TOKEN_PLAN_AMS,
        ];
    }

    /**
     * Whether a URL is one of the offered base URLs.
     *
     * @param string $url The URL to check, without a trailing slash.
     * @return bool Whether the URL may be used.
     */
    public static function isAllowedBaseUrl(string $url): bool
    {
        return in_array($url, self::getBaseUrlChoices(), true);
    }

    /**
     * Gets the API base URL.
     *
     * Resolved as: environment variable/constant > the stored option > the built-in default. The
     * option is validated on read as well as on save, so a value written directly to the database
     * (WP-CLI, a migration, a stray filter) can never point requests at an unlisted host.
     *
     * @return string The base URL, without a trailing slash.
     */
    public static function getBaseUrl(): string
    {
        $url = self::env('XIAOMIMIMO_BASE_URL');
        if ($url !== '') {
            return rtrim($url, '/');
        }

        $stored = rtrim(self::getStoredOption(self::OPTION_BASE_URL), '/');

        return self::isAllowedBaseUrl($stored) ? $stored : self::DEFAULT_BASE_URL;
    }

    /**
     * Gets the model ID to prefer.
     *
     * @return string The model ID, or an empty string to leave the AI plugin's own defaults alone.
     */
    public static function getDefaultModelId(): string
    {
        $configured = self::env('XIAOMIMIMO_DEFAULT_MODEL');

        return $configured === '' ? self::DEFAULT_MODEL : $configured;
    }

    /**
     * Gets how structured output (JSON response) requests are shaped.
     *
     * MiMo implements OpenAI's JSON mode only: `response_format` accepts `{"type":"json_object"}` and
     * rejects a JSON schema. `json_schema` requests from the SDK are therefore downgraded to
     * `json_object` (see the text generation model); `none` sends no `response_format` at all.
     *
     * @return string One of `json_object` or `none`.
     */
    public static function getStructuredOutputMode(): string
    {
        $mode = strtolower(self::env('XIAOMIMIMO_STRUCTURED_OUTPUT'));
        if ($mode === '') {
            $mode = strtolower(self::getStoredOption(self::OPTION_STRUCTURED_OUTPUT));
        }

        return in_array($mode, self::STRUCTURED_OUTPUT_MODES, true)
            ? $mode
            : self::STRUCTURED_OUTPUT_DEFAULT;
    }

    /**
     * Gets which thinking mode requests should ask for.
     *
     * `default` means the plugin sends no `thinking` parameter, leaving the model's own default
     * (enabled) in place. `enabled` and `disabled` are passed through as `thinking.type`; disabling
     * thinking is what a caller needs when it wants `temperature`/`top_p` to actually apply, because
     * MiMo forces those to its defaults while thinking is on.
     *
     * @return string One of `default`, `enabled` or `disabled`.
     */
    public static function getThinkingMode(): string
    {
        $mode = strtolower(self::env('XIAOMIMIMO_THINKING'));
        if ($mode === '') {
            $mode = strtolower(self::getStoredOption(self::OPTION_THINKING));
        }

        return in_array($mode, self::THINKING_MODES, true) ? $mode : self::THINKING_DEFAULT;
    }

    /**
     * Whether this deployment claims its models accept image input.
     *
     * MiMo's model list reports no modalities, so this plugin declares vision from a maintained list
     * in the catalog. Set `XIAOMIMIMO_MODEL_INPUT_MODALITIES` to a comma-separated list containing
     * `image` (e.g. `text,image`) to declare it for every chat model instead — for deployments ahead
     * of the catalog.
     *
     * @return bool Whether image input is declared for all chat models.
     */
    public static function declaresImageInput(): bool
    {
        $modalities = self::env('XIAOMIMIMO_MODEL_INPUT_MODALITIES');
        if ($modalities === '') {
            return false;
        }

        $modalities = array_map('trim', explode(',', strtolower($modalities)));

        return in_array('image', $modalities, true);
    }

    /**
     * Gets the request timeout in seconds.
     *
     * WordPress' HTTP default is 5 seconds, which no LLM request survives.
     *
     * @return float The timeout in seconds.
     */
    public static function getRequestTimeout(): float
    {
        $timeout = self::env('XIAOMIMIMO_REQUEST_TIMEOUT');

        return $timeout === '' ? 120.0 : (float) $timeout;
    }

    /**
     * Gets the connection timeout in seconds.
     *
     * @return float The timeout in seconds.
     */
    public static function getConnectTimeout(): float
    {
        $connectTimeout = self::env('XIAOMIMIMO_CONNECT_TIMEOUT');

        return $connectTimeout === '' ? 10.0 : (float) $connectTimeout;
    }

    /**
     * Whether a MiMo credential is available to the AI Client.
     *
     * Asks the AI Client instead of reading the credential itself: the key was given to WordPress by
     * the user, so the option is not this plugin's to read. The registry carries an authentication
     * instance once the user saved a key in Settings → Connectors (core hands it over on `init`) or
     * set `XIAOMIMIMO_API_KEY`, which the SDK resolves when the provider is registered.
     *
     * Still a purely local check — no network request — so it can be called from filters.
     *
     * @return bool Whether credentials are configured.
     */
    public static function hasCredentials(): bool
    {
        if (!class_exists(AiClient::class)) {
            return false;
        }

        $registry = AiClient::defaultRegistry();
        if (!$registry->hasProvider(self::PROVIDER_ID)) {
            return false;
        }

        return $registry->getProviderRequestAuthentication(self::PROVIDER_ID) !== null;
    }

    /**
     * Creates the request options used for every MiMo request, including the model list.
     *
     * @return RequestOptions The request options.
     */
    public static function createRequestOptions(): RequestOptions
    {
        $options = new RequestOptions();
        $options->setTimeout(self::getRequestTimeout());
        $options->setConnectTimeout(self::getConnectTimeout());

        return $options;
    }

    /**
     * Gets the User-Agent header value.
     *
     * @return string The User-Agent value.
     */
    public static function getUserAgent(): string
    {
        return 'bestony-ai-provider-for-xiaomi-mimo/' . self::VERSION;
    }
}
