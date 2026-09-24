<?php

/**
 * Plugin Name:       Bestony AI Provider for Xiaomi MiMo
 * Plugin URI:        https://github.com/bestony/AI-Provider-for-Xiaomi-MiMo
 * Description:       Xiaomi MiMo (小米 MiMo) provider for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.0.1
 * Author:            Bestony
 * Author URI:        https://github.com/bestony
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       bestony-ai-provider-for-xiaomi-mimo
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider;

use XiaomiMiMo\AiProvider\Admin\XiaomimimoSettings;
use XiaomiMiMo\AiProvider\Provider\XiaomimimoProvider;
use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use WordPress\AiClient\AiClient;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Loads the plugin's translations.
 *
 * @return void
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        'bestony-ai-provider-for-xiaomi-mimo',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

add_action('init', __NAMESPACE__ . '\\load_textdomain');

/*
 * The admin page and the Plugins-screen shortcut are admin-only, and `plugin_action_links_*` is
 * itself only applied in the admin, so registering unconditionally costs nothing on the front end.
 */
XiaomimimoSettings::register(__FILE__);

/**
 * Registers the provider with the AI Client.
 *
 * Runs on `init` priority 5: WordPress core builds the Connectors entries at priority 10 from
 * whatever providers are in the registry, so a later priority means no Connectors card (and no
 * place for the user to paste an API key).
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(XiaomimimoProvider::class)) {
        return;
    }

    $registry->registerProvider(XiaomimimoProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Puts the configured MiMo model first in a preference list.
 *
 * The AI plugin's defaults are Anthropic/Google/OpenAI, none of which the user may have configured.
 * Existing entries from other providers are preserved. Only an entry for the exact same provider and
 * model is removed, so a user who added a different MiMo model elsewhere keeps it.
 *
 * @param mixed $preferredModels List of `[provider_id, model_id]` tuples.
 * @param string $defaultModel The model ID to put first.
 * @return array<int, array{string, string}>
 */
function prefer_xiaomimimo_model($preferredModels, string $defaultModel): array
{
    $preferredList = is_array($preferredModels) ? array_values($preferredModels) : [];

    // Without a credential the provider never yields candidates, so there is nothing to prioritise.
    if (!XiaomimimoConfig::hasCredentials() || $defaultModel === '') {
        return $preferredList;
    }

    $preferred = [[XiaomimimoConfig::PROVIDER_ID, $defaultModel]];
    foreach ($preferredList as $entry) {
        if (!is_array($entry) || count($entry) < 2) {
            continue;
        }
        $entry = array_values($entry);
        if (XiaomimimoConfig::PROVIDER_ID === $entry[0] && $defaultModel === $entry[1]) {
            continue;
        }
        $preferred[] = [$entry[0], $entry[1]];
    }

    return $preferred;
}

/**
 * Prefers the configured chat model for text and vision features.
 *
 * The same model serves both: MiMo's `mimo-v2.6-*` family and `mimo-v2.5` accept images alongside
 * text, so there is no separate vision model to name.
 *
 * @param mixed $preferredModels List of `[provider_id, model_id]` tuples.
 * @return array<int, array{string, string}>
 */
function prefer_xiaomimimo_text_models($preferredModels): array
{
    return prefer_xiaomimimo_model($preferredModels, XiaomimimoConfig::getDefaultModelId());
}

add_filter('wpai_preferred_text_models', __NAMESPACE__ . '\\prefer_xiaomimimo_text_models');
add_filter('wpai_preferred_vision_models', __NAMESPACE__ . '\\prefer_xiaomimimo_text_models');
