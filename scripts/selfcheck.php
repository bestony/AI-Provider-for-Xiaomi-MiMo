<?php

// phpcs:ignoreFile -- dev-only CLI harness; .gitattributes export-ignores it from releases.

/**
 * Runnable self-check for the Xiaomi MiMo provider plugin.
 *
 * Covers the plugin's own logic — model classification, sort order, capability declarations,
 * configuration resolution and request building — without needing WordPress, composer or a live API
 * key. One file, no test framework, because this is the smallest thing that fails when the logic
 * breaks.
 *
 * Usage:
 *   php scripts/selfcheck.php
 *   php scripts/selfcheck.php --sdk=/path/to/php-ai-client              # bundled layout
 *   php scripts/selfcheck.php --sdk=/path/to/php-ai-client/src          # source-checkout layout
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/*
 * The plugin's autoloader refuses to run outside WordPress (Plugin Check requires a direct-access
 * guard on it), so this harness defines ABSPATH the way a WordPress test bootstrap does.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

/*
 * WordPress keys `plugin_action_links_*` and resolves `load_plugin_textdomain()`'s path against the
 * plugins directory, so the harness has to know where that is: the plugin root's parent.
 */
$GLOBALS['xiaomimimo_plugins_dir'] = dirname($root);
$GLOBALS['xiaomimimo_plugin_dir'] = $root;

require $root . '/src/autoload.php';

use XiaomiMiMo\AiProvider\Admin\XiaomimimoSettings;
use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use XiaomiMiMo\AiProvider\Util\XiaomimimoModelCatalog;

$failures = 0;
$checks = 0;

/**
 * Asserts a condition and records the outcome.
 *
 * @param bool $condition The condition to check.
 * @param string $description What is being checked.
 * @return void
 */
function check(bool $condition, string $description): void
{
    global $failures, $checks;
    $checks++;

    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL  {$description}\n");
        return;
    }

    fwrite(STDOUT, "ok    {$description}\n");
}

// --- Vision classification. -------------------------------------------------------------------
foreach (['mimo-v2.6-pro', 'mimo-v2.6-flash', 'mimo-v2.6-pro-ultraspeed', 'mimo-v2.5'] as $modelId) {
    check(XiaomimimoModelCatalog::supportsImageInput($modelId), "{$modelId} accepts image input");
}
foreach (['mimo-v2.5-pro', 'mimo-v2.5-asr', 'mimo-v2.5-tts', 'gpt-4o'] as $modelId) {
    check(!XiaomimimoModelCatalog::supportsImageInput($modelId), "{$modelId} is not declared vision-capable");
}

// --- Text classification: speech models must never look like chat models. ---------------------
foreach (['mimo-v2.6-pro', 'mimo-v2.6-flash', 'mimo-v2.6-pro-ultraspeed', 'mimo-v2.5', 'mimo-v2.5-pro'] as $modelId) {
    check(XiaomimimoModelCatalog::isTextModel($modelId), "{$modelId} generates text");
}
foreach (['mimo-v2.5-asr', 'mimo-v2.5-tts', 'mimo-v2.5-tts-voiceclone', 'mimo-v2.5-tts-voicedesign', 'gpt-4o'] as $modelId) {
    check(!XiaomimimoModelCatalog::isTextModel($modelId), "{$modelId} is not a chat model");
}
check(
    XiaomimimoModelCatalog::isUnsupported('mimo-v2.5-asr'),
    'ASR models are declared with no capability at all'
);
check(
    XiaomimimoModelCatalog::isUnsupported('mimo-v2.5-tts-voiceclone'),
    'TTS models are declared with no capability at all'
);

// --- Sampling parameters: documented as supported, so never stripped. -------------------------
check(
    !XiaomimimoModelCatalog::rejectsSamplingParameters('mimo-v2.6-pro'),
    'MiMo accepts temperature, so nothing is stripped'
);

// --- Sort order: usable before unusable, newer generation first, preview last. ----------------
check(
    XiaomimimoModelCatalog::compareModelIds('mimo-v2.6-pro', 'mimo-v2.5-asr') < 0,
    'a usable model sorts before a capability-less one'
);
check(
    XiaomimimoModelCatalog::compareModelIds('mimo-v2.6-pro', 'mimo-v2.5-pro') < 0,
    'a newer generation sorts before an older one'
);
check(
    XiaomimimoModelCatalog::compareModelIds('mimo-v2.10-pro', 'mimo-v2.6-pro') < 0,
    'versions compare numerically, not lexically'
);
check(
    XiaomimimoModelCatalog::compareModelIds('mimo-v2.6-pro', 'mimo-v2.6-preview') < 0,
    'a stable model sorts before its preview variant'
);
check(
    XiaomimimoModelCatalog::compareModelIds('mimo-v2.6-pro', 'mimo-v2.6-pro') === 0,
    'comparing a model with itself is neutral'
);

// --- Configuration defaults. ------------------------------------------------------------------
check(
    XiaomimimoConfig::getBaseUrl() === 'https://api.xiaomimimo.com/v1',
    'default base URL is the standard API'
);
check(XiaomimimoConfig::getRequestTimeout() >= 60.0, 'request timeout is long enough for an LLM call');
check(
    XiaomimimoConfig::getUserAgent() === 'ai-provider-for-xiaomimimo/' . XiaomimimoConfig::VERSION,
    'user agent identifies the plugin and its version'
);
check(XiaomimimoConfig::getStructuredOutputMode() === 'json_object', 'structured output defaults to json_object');
check(XiaomimimoConfig::getThinkingMode() === 'default', 'thinking mode defaults to no intervention');
check(XiaomimimoConfig::getDefaultModelId() === 'mimo-v2.6-pro', 'default chat model');
check(!XiaomimimoConfig::declaresImageInput(), 'image input is not force-declared by default');
check(!XiaomimimoConfig::hasCredentials(), 'no credentials unless one is configured');

// Environment variables override everything, even before WordPress options exist.
putenv('XIAOMIMIMO_BASE_URL=https://token-plan-cn.xiaomimimo.com/v1/');
check(
    XiaomimimoConfig::getBaseUrl() === 'https://token-plan-cn.xiaomimimo.com/v1',
    'XIAOMIMIMO_BASE_URL overrides the base URL'
);
putenv('XIAOMIMIMO_THINKING=disabled');
check(XiaomimimoConfig::getThinkingMode() === 'disabled', 'XIAOMIMIMO_THINKING overrides thinking mode');
putenv('XIAOMIMIMO_STRUCTURED_OUTPUT=none');
check(
    XiaomimimoConfig::getStructuredOutputMode() === 'none',
    'XIAOMIMIMO_STRUCTURED_OUTPUT overrides structured output'
);
putenv('XIAOMIMIMO_STRUCTURED_OUTPUT=bogus');
check(
    XiaomimimoConfig::getStructuredOutputMode() === 'json_object',
    'an unrecognised structured-output value falls back to json_object'
);
putenv('XIAOMIMIMO_BASE_URL');
putenv('XIAOMIMIMO_THINKING');
putenv('XIAOMIMIMO_STRUCTURED_OUTPUT');

// --- The offered base URLs. -------------------------------------------------------------------
$choices = XiaomimimoConfig::getBaseUrlChoices();
check(count($choices) === 4, 'exactly four MiMo base URLs are offered');
check($choices[0] === XiaomimimoConfig::DEFAULT_BASE_URL, 'the default base URL is offered first');
foreach (
    [
    'https://api.xiaomimimo.com/v1',
    'https://token-plan-cn.xiaomimimo.com/v1',
    'https://token-plan-sgp.xiaomimimo.com/v1',
    'https://token-plan-ams.xiaomimimo.com/v1',
    ] as $url
) {
    check(XiaomimimoConfig::isAllowedBaseUrl($url), "{$url} is an allowed base URL");
}
check(
    !XiaomimimoConfig::isAllowedBaseUrl('https://evil.example.com/v1'),
    'an unlisted host is not an allowed base URL'
);

// --- Request building, against the real SDK when one is available. ----------------------------
$sdkPath = null;
foreach ($argv as $argument) {
    if (strpos($argument, '--sdk=') === 0) {
        $sdkPath = rtrim(substr($argument, 6), '/');
    }
}

use_xiaomimimo_load_sdk($sdkPath);

/**
 * Loads the AI Client SDK from either layout, then runs the SDK-dependent checks.
 *
 * Two layouts are common: the bundled WordPress 7.0 package (an `autoload.php` next to `src/` and
 * `third-party/`) and a source checkout whose `src/` is the passed directory. The argument may point
 * at the package root or straight at `src`.
 *
 * @param string|null $sdkPath The value of the `--sdk=` argument, or null when absent.
 * @return void
 */
function use_xiaomimimo_load_sdk(?string $sdkPath): void
{
    if ($sdkPath === null || $sdkPath === '') {
        check(!XiaomimimoConfig::hasCredentials(), 'without the AI Client there are no credentials to report');
        fwrite(STDOUT, "skip  SDK-dependent checks (pass --sdk=<path to php-ai-client> to run them)\n");
        return;
    }

    // Accept either the package root or the src directory itself.
    if (is_file($sdkPath . '/src/AiClient.php')) {
        $sdkRoot = $sdkPath;
    } elseif (is_file($sdkPath . '/AiClient.php')) {
        $sdkRoot = dirname($sdkPath);
    } else {
        fwrite(STDERR, "FAIL  --sdk points at no AiClient.php: {$sdkPath}\n");
        exit(1);
    }

    if (is_file($sdkRoot . '/autoload.php')) {
        // Bundled layout: the generated autoloader covers src/ and third-party/.
        require $sdkRoot . '/autoload.php';
    } else {
        $src = $sdkRoot . '/src';
        if (is_file($src . '/polyfills.php')) {
            require $src . '/polyfills.php';
        }
        spl_autoload_register(static function (string $class) use ($src): void {
            $prefix = 'WordPress\\AiClient\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $file = $src . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    use_xiaomimimo_sdk_checks();
}

/**
 * Checks that need the real SDK classes: declared options, the built request, the plugin wiring.
 *
 * @return void
 */
function use_xiaomimimo_sdk_checks(): void
{
    $response = new \WordPress\AiClient\Providers\Http\DTO\Response(
        200,
        [],
        json_encode([
            'object' => 'list',
            'data' => [
                ['id' => 'mimo-v2.6-pro', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.6-flash', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.6-pro-ultraspeed', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.5', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.5-pro', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.5-asr', 'object' => 'model', 'owned_by' => 'xiaomi'],
                ['id' => 'mimo-v2.5-tts', 'object' => 'model', 'owned_by' => 'xiaomi'],
            ],
        ])
    );

    // parseResponseToModelMetadataList() is protected: reach it through a subclass.
    $parser = new class extends \XiaomiMiMo\AiProvider\Metadata\XiaomimimoModelMetadataDirectory {
        /**
         * Exposes the protected parser.
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The model list response.
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> The parsed models.
         */
        public function parse(\WordPress\AiClient\Providers\Http\DTO\Response $response): array
        {
            return $this->parseResponseToModelMetadataList($response);
        }
    };

    $models = $parser->parse($response);
    check(count($models) === 7, 'every model in the list response is parsed');

    $byId = [];
    $orderedIds = [];
    foreach ($models as $model) {
        $byId[$model->getId()] = $model;
        $orderedIds[] = $model->getId();
    }

    check($orderedIds[0] === 'mimo-v2.6-pro', 'the configured model is sorted first');
    check(
        array_search('mimo-v2.6-flash', $orderedIds, true) < array_search('mimo-v2.5-asr', $orderedIds, true),
        'chat models sort before the capability-less speech models'
    );
    check(
        array_search('mimo-v2.6-flash', $orderedIds, true) < array_search('mimo-v2.5-tts', $orderedIds, true),
        'the TTS model sorts after a usable chat model'
    );

    $optionNames = static function (\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model): array {
        return array_map(
            static fn($option): string => $option->getName()->value,
            $model->getSupportedOptions()
        );
    };
    $optionValues = static function (
        \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model,
        string $optionName
    ): ?array {
        foreach ($model->getSupportedOptions() as $option) {
            if ($option->getName()->value === $optionName) {
                return $option->getSupportedValues();
            }
        }
        return null;
    };
    $capabilityValues = static function (\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model): array {
        return array_map(static fn($capability): string => $capability->value, $model->getSupportedCapabilities());
    };

    // Capabilities.
    check($capabilityValues($byId['mimo-v2.5-asr']) === [], 'the ASR model declares no capability');
    check($capabilityValues($byId['mimo-v2.5-tts']) === [], 'the TTS model declares no capability');
    check(
        in_array('text_generation', $capabilityValues($byId['mimo-v2.6-pro']), true),
        'a chat model declares text generation'
    );

    // Input modalities: multimodal models offer two combinations, text-only models one.
    check(
        count((array) $optionValues($byId['mimo-v2.6-pro'], 'inputModalities')) === 2,
        'a multimodal model declares two input modality combinations'
    );
    check(
        count((array) $optionValues($byId['mimo-v2.5'], 'inputModalities')) === 2,
        'mimo-v2.5 is recognised as multimodal'
    );
    check(
        count((array) $optionValues($byId['mimo-v2.5-pro'], 'inputModalities')) === 1,
        'mimo-v2.5-pro is recognised as text-only'
    );

    // Undocumented chat parameters must not be declared.
    $chatOptions = $optionNames($byId['mimo-v2.6-pro']);
    check(in_array('temperature', $chatOptions, true), 'chat models declare temperature');
    check(in_array('topP', $chatOptions, true), 'chat models declare top_p');
    check(in_array('frequencyPenalty', $chatOptions, true), 'chat models declare frequency penalty');
    check(in_array('presencePenalty', $chatOptions, true), 'chat models declare presence penalty');
    check(in_array('outputSchema', $chatOptions, true), 'chat models declare output schema support');
    check(!in_array('candidateCount', $chatOptions, true), 'undocumented candidate count is not declared');
    check(!in_array('logprobs', $chatOptions, true), 'undocumented logprobs is not declared');
    check(!in_array('webSearch', $chatOptions, true), 'undeclared web search is not offered');
    check(!in_array('outputMediaOrientation', $chatOptions, true), 'chat models declare no media options');

    // --- Requests, end to end through a fake HTTP transporter. ---------------------------------
    $providerMetadata = \XiaomiMiMo\AiProvider\Provider\XiaomimimoProvider::metadata();

    /**
     * Records the request it is handed and replays a canned response.
     */
    $transporter = new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
        /** @var \WordPress\AiClient\Providers\Http\DTO\Request|null */
        public $request = null;

        /** @var array<string, mixed> */
        public $body = [];

        /** @var array<string, mixed> */
        public $queue = [];

        /**
         * Sends a request and records it.
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request.
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null $options Transport options.
         * @return \WordPress\AiClient\Providers\Http\DTO\Response The canned response.
         */
        public function send(
            \WordPress\AiClient\Providers\Http\DTO\Request $request,
            ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null
        ): \WordPress\AiClient\Providers\Http\DTO\Response {
            $this->request = $request;
            $this->body = (array) $request->getData();

            return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], json_encode($this->queue));
        }
    };

    /**
     * Wires a model to the fake transporter with a test credential.
     *
     * @param object $model The model instance.
     * @param object $transporter The fake transporter.
     * @return void
     */
    $bind = static function ($model, $transporter): void {
        $model->setHttpTransporter($transporter);
        $model->setRequestAuthentication(
            new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key')
        );
        $model->setRequestOptions(XiaomimimoConfig::createRequestOptions());
    };

    $userMessage = static fn(string $text) => new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [new \WordPress\AiClient\Messages\DTO\MessagePart($text)]
    );

    // Chat: request shape, token field, structured output downgrade.
    $chatModel = new \XiaomiMiMo\AiProvider\Models\XiaomimimoTextGenerationModel(
        $byId['mimo-v2.6-pro'],
        $providerMetadata
    );
    $bind($chatModel, $transporter);
    $chatModel->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'systemInstruction' => 'Be terse.',
        'maxTokens' => 256,
        'temperature' => 0.2,
        'outputMimeType' => 'application/json',
        'outputSchema' => [
            'type' => 'object',
            'properties' => ['suggestions' => ['type' => 'array']],
        ],
    ]));

    $transporter->queue = [
        'id' => 'chatcmpl-1',
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'Hi there'],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3, 'total_tokens' => 10],
    ];
    $chatResult = $chatModel->generateTextResult([$userMessage('hi')]);

    check(
        $transporter->request->getUri() === 'https://api.xiaomimimo.com/v1/chat/completions',
        'the chat request targets /v1/chat/completions'
    );
    check($transporter->body['model'] === 'mimo-v2.6-pro', 'the chat request carries the model ID');
    check(
        $transporter->body['max_completion_tokens'] === 256,
        'max tokens are sent as max_completion_tokens'
    );
    check(!isset($transporter->body['max_tokens']), 'the legacy max_tokens key is not sent');
    check($transporter->body['temperature'] === 0.2, 'temperature is forwarded');
    check($transporter->body['messages'][0]['role'] === 'system', 'the system instruction is prepended');
    check(
        $transporter->request->getHeaders()['User-Agent'][0] === XiaomimimoConfig::getUserAgent(),
        'the chat request identifies the plugin'
    );
    check(
        $transporter->request->getHeaders()['Authorization'][0] === 'Bearer test-key',
        'the chat request carries the Bearer token'
    );

    // Regression: MiMo rejects json_schema, so a schema request must be downgraded to json_object.
    $responseFormat = $transporter->body['response_format'] ?? null;
    check(
        is_array($responseFormat) && ($responseFormat['type'] ?? null) === 'json_object',
        'a JSON schema request is downgraded to json_object'
    );
    check(!isset($responseFormat['json_schema']), 'no json_schema payload is sent');
    check(
        $chatResult->getCandidates()[0]->getMessage()->getParts()[0]->getText() === 'Hi there',
        'the chat response text is parsed'
    );
    check($chatResult->getTokenUsage()->getPromptTokens() === 7, 'prompt tokens are counted');

    // Thinking mode switch: default sends nothing, the other values are injected.
    check(!isset($transporter->body['thinking']), 'the default thinking mode sends no thinking parameter');
    putenv('XIAOMIMIMO_THINKING=enabled');
    $chatModel->generateTextResult([$userMessage('hi')]);
    check(
        ($transporter->body['thinking'] ?? null) === ['type' => 'enabled'],
        'thinking mode enabled is passed through'
    );
    putenv('XIAOMIMIMO_THINKING=disabled');
    $chatModel->generateTextResult([$userMessage('hi')]);
    check(
        ($transporter->body['thinking'] ?? null) === ['type' => 'disabled'],
        'thinking mode disabled is passed through'
    );
    putenv('XIAOMIMIMO_THINKING');

    // Structured output switched off entirely.
    putenv('XIAOMIMIMO_STRUCTURED_OUTPUT=none');
    $chatModel->generateTextResult([$userMessage('hi')]);
    check(
        !isset($transporter->body['response_format']),
        'structured output "none" sends no response_format at all'
    );
    putenv('XIAOMIMIMO_STRUCTURED_OUTPUT');

    // Vision input: an inline image must become an image_url content part.
    $transporter->queue = [
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'A cat'],
            'finish_reason' => 'stop',
        ]],
    ];
    $visionModel = new \XiaomiMiMo\AiProvider\Models\XiaomimimoTextGenerationModel(
        $byId['mimo-v2.6-pro'],
        $providerMetadata
    );
    $bind($visionModel, $transporter);
    $visionModel->generateTextResult([new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [
            new \WordPress\AiClient\Messages\DTO\MessagePart('what is this'),
            new \WordPress\AiClient\Messages\DTO\MessagePart(
                new \WordPress\AiClient\Files\DTO\File('data:image/png;base64,iVBORw0KGgo=', 'image/png')
            ),
        ]
    )]);
    $contentTypes = array_column($transporter->body['messages'][0]['content'], 'type');
    check(in_array('image_url', $contentTypes, true), 'an inline image becomes an image_url content part');

    // Reasoning content is parsed into a thought part by the base class.
    $transporter->queue = [
        'choices' => [[
            'message' => ['role' => 'assistant', 'reasoning_content' => 'pondering', 'content' => 'Answer'],
            'finish_reason' => 'stop',
        ]],
    ];
    $reasoned = $chatModel->generateTextResult([$userMessage('hi')]);
    $channels = [];
    foreach ($reasoned->getCandidates()[0]->getMessage()->getParts() as $part) {
        if ($part->getType()->isText()) {
            $channels[$part->getChannel()->value] = $part->getText();
        }
    }
    check(($channels['thought'] ?? null) === 'pondering', 'reasoning_content becomes a thought part');
    check(($channels['content'] ?? null) === 'Answer', 'the visible answer is a separate content part');

    use_xiaomimimo_requirements_checks($byId, $userMessage);
    use_xiaomimimo_plugin_checks();
}

/**
 * Checks that the declared metadata satisfies the requirements the AI plugin actually sends.
 *
 * This is the failure mode that matters most: metadata is the single source of truth, so a missing
 * capability or option makes a feature report "no available model" with no obvious cause.
 *
 * @param array<string, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata> $byId Models by ID.
 * @param callable $userMessage Builds a user message from text.
 * @return void
 */
function use_xiaomimimo_requirements_checks(array $byId, callable $userMessage): void
{
    // Plain text generation (Title Generation, Summarization, Excerpt Generation, …).
    $plainConfig = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'systemInstruction' => 'Be terse.',
    ]);
    check(
        \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$userMessage('generate a title')],
            $plainConfig
        )->areMetBy($byId['mimo-v2.6-pro']),
        'plain text generation is supported by the configured chat model'
    );

    // Chat history (two or more messages) must map onto the chatHistory capability.
    check(
        \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$userMessage('hi'), $userMessage('and again')],
            $plainConfig
        )->areMetBy($byId['mimo-v2.6-pro']),
        'a multi-message prompt is supported (chat history is declared)'
    );

    // Structured output, exactly as the AI plugin's as_json_response() call sites build it.
    $jsonConfig = \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'outputMimeType' => 'application/json',
        'outputSchema' => [
            'type' => 'object',
            'properties' => ['suggestions' => ['type' => 'array']],
        ],
    ]);
    check(
        \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$userMessage('suggest notes')],
            $jsonConfig
        )->areMetBy($byId['mimo-v2.6-pro']),
        'a JSON schema request is supported (Editorial Notes and friends)'
    );

    // Vision: Alt Text Generation sends text plus an image file.
    $visionMessage = new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [
            new \WordPress\AiClient\Messages\DTO\MessagePart('describe this'),
            new \WordPress\AiClient\Messages\DTO\MessagePart(
                new \WordPress\AiClient\Files\DTO\File('data:image/png;base64,iVBORw0KGgo=', 'image/png')
            ),
        ]
    );
    check(
        \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$visionMessage],
            $plainConfig
        )->areMetBy($byId['mimo-v2.6-pro']),
        'alt text generation is supported by the multimodal model'
    );
    check(
        !\WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$visionMessage],
            $plainConfig
        )->areMetBy($byId['mimo-v2.5-pro']),
        'the text-only model is correctly rejected for an image prompt'
    );

    // Tool calling: the requirement is inferred from the prompt, not configured by the caller.
    $toolMessage = new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [new \WordPress\AiClient\Messages\DTO\MessagePart(new \WordPress\AiClient\Tools\DTO\FunctionResponse(
            'call_1',
            'get_weather',
            ['temperature' => 21]
        ))]
    );
    check(
        \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$toolMessage],
            $plainConfig
        )->areMetBy($byId['mimo-v2.6-pro']),
        'tool calling is supported (function declarations are declared)'
    );

    // A speech model has no capability, so it must never be selected for anything.
    check(
        !\WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
            [$userMessage('hi')],
            $plainConfig
        )->areMetBy($byId['mimo-v2.5-asr']),
        'the ASR model is never selected for text generation'
    );
}

/**
 * Checks the plugin's entry file: registration guards and the model preference filters.
 *
 * The plugin file is not part of the autoloader, so it is loaded here with the few WordPress
 * functions it touches stubbed out. The preference filters are the reason this exists: they rewrite a
 * list owned by other plugins, and an off-by-one there silently drops another provider's model.
 *
 * @return void
 */
function use_xiaomimimo_plugin_checks(): void
{
    // WordPress stubs, defined only when WordPress is not actually present.
    if (!function_exists('add_action')) {
        /**
         * Records an action registration.
         *
         * @param string $hook The hook name.
         * @param callable $callback The callback.
         * @param int $priority The priority.
         * @return void
         */
        function add_action(string $hook, $callback, int $priority = 10): void
        {
            $GLOBALS['xiaomimimo_actions'][$hook][$priority][] = $callback;
        }

        /**
         * Records a filter registration.
         *
         * @param string $hook The hook name.
         * @param callable $callback The callback.
         * @param int $priority The priority.
         * @return void
         */
        function add_filter(string $hook, $callback, int $priority = 10): void
        {
            $GLOBALS['xiaomimimo_filters'][$hook][$priority][] = $callback;
        }

        /**
         * Returns the string unchanged; translation is a WordPress concern.
         *
         * @param string $text The text.
         * @param string|null $domain The text domain.
         * @return string The text.
         */
        function __(string $text, ?string $domain = null): string
        {
            return $text;
        }

        /**
         * Returns the escaped string unchanged.
         *
         * @param string $text The text.
         * @return string The text.
         */
        function esc_html(string $text): string
        {
            return $text;
        }

        /**
         * Returns the escaped string unchanged.
         *
         * @param string $text The text.
         * @param string|null $domain The text domain.
         * @return string The text.
         */
        function esc_html__(string $text, ?string $domain = null): string
        {
            return $text;
        }

        /**
         * Returns the URL-escaped string unchanged.
         *
         * @param string $url The URL.
         * @return string The URL.
         */
        function esc_url(string $url): string
        {
            return $url;
        }

        /**
         * Returns the attribute-escaped string unchanged.
         *
         * @param string $text The text.
         * @return string The text.
         */
        function esc_attr(string $text): string
        {
            return $text;
        }

        /**
         * Builds an admin URL the way WordPress does, without needing WordPress.
         *
         * @param string $path The path, relative to wp-admin.
         * @return string The absolute admin URL.
         */
        function admin_url(string $path = ''): string
        {
            return 'https://example.test/wp-admin/' . $path;
        }

        /**
         * Returns the plugin file's path relative to the plugins directory, as WordPress does.
         *
         * @param string $file The plugin file path.
         * @return string The path relative to the plugins directory.
         */
        function plugin_basename(string $file): string
        {
            $dir = $GLOBALS['xiaomimimo_plugins_dir'] ?? null;
            if (is_string($dir) && strpos($file, $dir) === 0) {
                return ltrim(substr($file, strlen($dir)), '/');
            }

            return basename($file);
        }

        /**
         * Reads from an in-memory option table, so option resolution can be exercised.
         *
         * @param string $name The option name.
         * @param mixed $default The default when the option is not set.
         * @return mixed The option value.
         */
        function get_option(string $name, $default = false)
        {
            return $GLOBALS['xiaomimimo_options'][$name] ?? $default;
        }

        /**
         * Records that translations were loaded; the harness has no .mo files to load.
         *
         * @param string $domain The text domain.
         * @param bool $deprecated Unused.
         * @param string|null $path The languages directory.
         * @return bool Always true.
         */
        function load_plugin_textdomain(string $domain, bool $deprecated = false, ?string $path = null): bool
        {
            $GLOBALS['xiaomimimo_textdomain'] = [$domain, $path];

            return true;
        }
    }

    require dirname(__DIR__) . '/ai-provider-for-xiaomimimo.php';

    /*
     * The SDK resolves a PSR-18 client through HTTPlug discovery when a provider is registered. That
     * works in WordPress (the AI plugin ships the discovery package) but not against a bare SDK
     * checkout, so inject a no-op transporter first and the registration path never reaches discovery.
     */
    \WordPress\AiClient\AiClient::defaultRegistry()->setHttpTransporter(
        new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
            /**
             * Never called: this harness only exercises registration and filters.
             *
             * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request.
             * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null $options Transport options.
             * @return \WordPress\AiClient\Providers\Http\DTO\Response The response.
             */
            public function send(
                \WordPress\AiClient\Providers\Http\DTO\Request $request,
                ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null
            ): \WordPress\AiClient\Providers\Http\DTO\Response {
                return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], '{}');
            }
        }
    );

    // Credential detection: asks the AI Client, never reads the Connectors option.
    check(!XiaomimimoConfig::hasCredentials(), 'no credentials before the AI Client is given one');

    // Registration must be on init priority 5, or the Connectors card never appears.
    $callbacks = $GLOBALS['xiaomimimo_actions']['init'][5] ?? [];
    check($callbacks !== [], 'the provider registers on init priority 5');
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(
        \WordPress\AiClient\AiClient::defaultRegistry()->hasProvider(XiaomimimoConfig::PROVIDER_ID),
        'the registered provider is present in the SDK registry'
    );
    // Registering again must be a no-op rather than an error.
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(true, 'registering the provider twice does not throw');
    check(!XiaomimimoConfig::hasCredentials(), 'a registered provider is not yet a credentialed one');

    // The filters go through WordPress' filter machinery in production; apply them by hand here.
    $apply = static function (string $hook, array $value): array {
        $priorities = $GLOBALS['xiaomimimo_filters'][$hook] ?? [];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value);
            }
        }
        return $value;
    };

    // Without a credential the provider yields no candidates, so the lists must be untouched.
    $untouched = [['anthropic', 'claude-sonnet-5']];
    check(
        $apply('wpai_preferred_text_models', $untouched) === $untouched,
        'the text preference filter changes nothing without credentials'
    );
    check(
        $apply('wpai_preferred_vision_models', $untouched) === $untouched,
        'the vision preference filter changes nothing without credentials'
    );

    // No image-generation model exists, so no image preference filter may be registered.
    check(
        !isset($GLOBALS['xiaomimimo_filters']['wpai_preferred_image_models']),
        'no image preference filter is registered (MiMo has no image generation)'
    );

    \WordPress\AiClient\AiClient::defaultRegistry()->setProviderRequestAuthentication(
        XiaomimimoConfig::PROVIDER_ID,
        new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key')
    );
    check(XiaomimimoConfig::hasCredentials(), 'a key handed to the AI Client counts as credentials');

    // Preferred first, other providers preserved, and a different MiMo model kept.
    check(
        $apply('wpai_preferred_text_models', [
            ['anthropic', 'claude-sonnet-5'],
            ['xiaomimimo', 'mimo-v2.6-flash'],
            ['xiaomimimo', 'mimo-v2.6-pro'],
        ]) === [
            ['xiaomimimo', 'mimo-v2.6-pro'],
            ['anthropic', 'claude-sonnet-5'],
            ['xiaomimimo', 'mimo-v2.6-flash'],
        ],
        'the text filter prefers the default model, keeps other providers and other MiMo models'
    );

    // Vision features need a model that accepts images.
    check(
        $apply('wpai_preferred_vision_models', []) === [['xiaomimimo', 'mimo-v2.6-pro']],
        'the vision filter prefers the multimodal chat model'
    );

    // Entries from other plugins can be any shape; they must be skipped, not propagated.
    $malformed = $apply('wpai_preferred_text_models', ['nonsense', ['only-one'], ['openai', 'gpt-5.6-luna']]);
    check(
        $malformed === [['xiaomimimo', 'mimo-v2.6-pro'], ['openai', 'gpt-5.6-luna']],
        'malformed preference entries are skipped rather than propagated'
    );

    // The filter must reflect the configured model, not a hardcoded one.
    putenv('XIAOMIMIMO_DEFAULT_MODEL=mimo-v2.6-flash');
    check(
        $apply('wpai_preferred_text_models', []) === [['xiaomimimo', 'mimo-v2.6-flash']],
        'XIAOMIMIMO_DEFAULT_MODEL is honoured by the preference filter'
    );
    putenv('XIAOMIMIMO_DEFAULT_MODEL');

    use_xiaomimimo_settings_checks($apply);
}

/**
 * Checks the settings page wiring: the options, their validation, and the Plugins shortcut.
 *
 * @param callable $apply Applies a recorded filter by hook name.
 * @return void
 */
function use_xiaomimimo_settings_checks(callable $apply): void
{
    // --- Resolution: env > stored option > default. ---------------------------------------------
    $GLOBALS['xiaomimimo_options'] = [];
    check(
        XiaomimimoConfig::getBaseUrl() === XiaomimimoConfig::DEFAULT_BASE_URL,
        'no option and no constant yields the default base URL'
    );
    check(
        XiaomimimoConfig::getThinkingMode() === XiaomimimoConfig::THINKING_DEFAULT,
        'no stored thinking option yields the default'
    );
    check(
        XiaomimimoConfig::getStructuredOutputMode() === XiaomimimoConfig::STRUCTURED_OUTPUT_DEFAULT,
        'no stored structured-output option yields the default'
    );

    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_BASE_URL] = 'https://token-plan-sgp.xiaomimimo.com/v1';
    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_THINKING] = 'disabled';
    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_STRUCTURED_OUTPUT] = 'none';
    check(
        XiaomimimoConfig::getBaseUrl() === 'https://token-plan-sgp.xiaomimimo.com/v1',
        'the stored base-URL option becomes the base URL'
    );
    check(XiaomimimoConfig::getThinkingMode() === 'disabled', 'the stored thinking option is honoured');
    check(
        XiaomimimoConfig::getStructuredOutputMode() === 'none',
        'the stored structured-output option is honoured'
    );

    // A value written straight to the database must not reach the network.
    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_BASE_URL] = 'https://evil.example.com/v1';
    check(
        XiaomimimoConfig::getBaseUrl() === XiaomimimoConfig::DEFAULT_BASE_URL,
        'an unlisted stored option falls back to the default base URL'
    );

    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_BASE_URL] = 'https://token-plan-ams.xiaomimimo.com/v1/';
    check(
        XiaomimimoConfig::getBaseUrl() === 'https://token-plan-ams.xiaomimimo.com/v1',
        'a stored trailing slash is trimmed before use'
    );

    // The environment variable still wins, so a deployment is not silently repointed by the UI.
    putenv('XIAOMIMIMO_BASE_URL=https://token-plan-cn.xiaomimimo.com/v1');
    check(
        XiaomimimoConfig::getBaseUrl() === 'https://token-plan-cn.xiaomimimo.com/v1',
        'XIAOMIMIMO_BASE_URL overrides the stored option'
    );
    putenv('XIAOMIMIMO_BASE_URL');
    $GLOBALS['xiaomimimo_options'] = [];

    // --- Every choice must actually drive request URL building. ---------------------------------
    $provider = \XiaomiMiMo\AiProvider\Provider\XiaomimimoProvider::class;
    foreach (XiaomimimoConfig::getBaseUrlChoices() as $choice) {
        $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_BASE_URL] = $choice;
        check(
            $provider::url('chat/completions') === $choice . '/chat/completions',
            "a request for {$choice} joins to {$choice}/chat/completions"
        );
    }

    // The SDK joins with `baseUrl() . '/' . ltrim($path, '/')`, so a leading slash must not double up.
    $GLOBALS['xiaomimimo_options'][XiaomimimoConfig::OPTION_BASE_URL] = 'https://api.xiaomimimo.com/v1';
    check(
        $provider::url('/models') === 'https://api.xiaomimimo.com/v1/models',
        'a leading slash on the path does not produce a double slash'
    );
    check(
        $provider::url() === 'https://api.xiaomimimo.com/v1',
        'an empty path yields the base URL itself'
    );
    $GLOBALS['xiaomimimo_options'] = [];

    // --- Sanitisation of submitted values. ------------------------------------------------------
    check(
        XiaomimimoSettings::sanitizeBaseUrl('https://token-plan-cn.xiaomimimo.com/v1')
            === 'https://token-plan-cn.xiaomimimo.com/v1',
        'an allowed base URL survives sanitisation'
    );
    check(
        XiaomimimoSettings::sanitizeBaseUrl('https://token-plan-cn.xiaomimimo.com/v1/')
            === 'https://token-plan-cn.xiaomimimo.com/v1',
        'a submitted trailing slash is trimmed'
    );
    check(
        XiaomimimoSettings::sanitizeBaseUrl('https://evil.example.com/v1') === XiaomimimoConfig::DEFAULT_BASE_URL,
        'an unlisted submitted base URL is replaced by the default'
    );
    check(
        XiaomimimoSettings::sanitizeBaseUrl(['https://api.xiaomimimo.com/v1']) === XiaomimimoConfig::DEFAULT_BASE_URL,
        'an array submission is replaced by the default'
    );
    check(
        XiaomimimoSettings::sanitizeThinking('DISABLED') === 'disabled',
        'a thinking mode is lowercased and accepted'
    );
    check(
        XiaomimimoSettings::sanitizeThinking('sometimes') === XiaomimimoConfig::THINKING_DEFAULT,
        'an unknown thinking mode falls back to the default'
    );
    check(
        XiaomimimoSettings::sanitizeThinking(null) === XiaomimimoConfig::THINKING_DEFAULT,
        'a null thinking mode falls back to the default'
    );
    check(
        XiaomimimoSettings::sanitizeStructuredOutput('none') === 'none',
        'the structured-output "none" choice survives sanitisation'
    );
    check(
        XiaomimimoSettings::sanitizeStructuredOutput('json_schema') === XiaomimimoConfig::STRUCTURED_OUTPUT_DEFAULT,
        'the unsupported json_schema choice falls back to json_object'
    );

    // --- The Plugins-screen shortcut. -----------------------------------------------------------
    $hook = 'plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/ai-provider-for-xiaomimimo.php');
    $links = $apply($hook, ['deactivate' => '<a href="#">Deactivate</a>']);
    check(count($links) === 2, 'the shortcut is appended to the existing plugin action links');
    $shortcut = (string) end($links);
    check(
        strpos($shortcut, 'Setup Token Plan') !== false,
        'the plugin row offers a Setup Token Plan link'
    );
    check(
        strpos($shortcut, 'options-general.php?page=' . XiaomimimoSettings::PAGE_SLUG) !== false,
        'the Setup Token Plan link points at the settings page'
    );
    check(
        ($links['deactivate'] ?? null) === '<a href="#">Deactivate</a>',
        'existing plugin action links are preserved untouched'
    );

    // --- Translations. --------------------------------------------------------------------------
    $callbacks = $GLOBALS['xiaomimimo_actions']['init'][10] ?? [];
    check($callbacks !== [], 'the text domain is loaded on init');
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(
        ($GLOBALS['xiaomimimo_textdomain'][0] ?? null) === 'ai-provider-for-xiaomimimo',
        'the text domain matches the plugin header'
    );
    check(
        ($GLOBALS['xiaomimimo_textdomain'][1] ?? null) === basename($GLOBALS['xiaomimimo_plugin_dir']) . '/languages',
        'translations are loaded from the plugin languages directory'
    );
}

// --- Result. -----------------------------------------------------------------------------------
fwrite(STDOUT, sprintf("\n%d checks, %d failure(s)\n", $checks, $failures));

exit($failures === 0 ? 0 : 1);
