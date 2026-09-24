<?php

/**
 * Plugin settings page class file.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Admin;

use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;

/**
 * The Settings → Bestony AI Provider for Xiaomi MiMo page and the Plugins-screen shortcut to it.
 *
 * Everything here goes through the WordPress Settings API: `register_setting()` owns the nonce,
 * capability check and persistence, so no option is written by hand.
 *
 * Three options live here — the API host, the thinking mode and the structured-output mode. Each one
 * only ever offers the values the plugin actually understands, and each is overridden by the matching
 * environment variable (`XIAOMIMIMO_BASE_URL`, `XIAOMIMIMO_THINKING`, `XIAOMIMIMO_STRUCTURED_OUTPUT`)
 * when one is set.
 */
final class XiaomimimoSettings
{
    /**
     * The Settings API option group, used for the nonce fields and the form target.
     *
     * @var string
     */
    public const OPTION_GROUP = 'xiaomimimo_settings';

    /**
     * The admin page slug, also used as the Settings API page id.
     *
     * @var string
     */
    public const PAGE_SLUG = 'xiaomimimo-settings';

    /**
     * The section id for the API endpoint settings.
     *
     * @var string
     */
    private const SECTION_ENDPOINT = 'xiaomimimo_endpoint_section';

    /**
     * The section id for the generation behaviour settings.
     *
     * @var string
     */
    private const SECTION_GENERATION = 'xiaomimimo_generation_section';

    /**
     * Registers the admin hooks.
     *
     * @param string $pluginFile Absolute path to the main plugin file.
     * @return void
     */
    public static function register(string $pluginFile): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_menu', [self::class, 'addPage']);
        add_filter(
            'plugin_action_links_' . plugin_basename($pluginFile),
            [self::class, 'addActionLink']
        );
    }

    /**
     * Registers the settings, their sections and their fields.
     *
     * @return void
     */
    public static function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            XiaomimimoConfig::OPTION_BASE_URL,
            [
                'type' => 'string',
                'default' => XiaomimimoConfig::DEFAULT_BASE_URL,
                'sanitize_callback' => [self::class, 'sanitizeBaseUrl'],
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            XiaomimimoConfig::OPTION_THINKING,
            [
                'type' => 'string',
                'default' => XiaomimimoConfig::THINKING_DEFAULT,
                'sanitize_callback' => [self::class, 'sanitizeThinking'],
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            XiaomimimoConfig::OPTION_STRUCTURED_OUTPUT,
            [
                'type' => 'string',
                'default' => XiaomimimoConfig::STRUCTURED_OUTPUT_DEFAULT,
                'sanitize_callback' => [self::class, 'sanitizeStructuredOutput'],
            ]
        );

        add_settings_section(
            self::SECTION_ENDPOINT,
            __('Xiaomi MiMo API endpoint', 'bestony-ai-provider-for-xiaomi-mimo'),
            [self::class, 'renderEndpointSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            XiaomimimoConfig::OPTION_BASE_URL,
            __('API host', 'bestony-ai-provider-for-xiaomi-mimo'),
            [self::class, 'renderBaseUrlField'],
            self::PAGE_SLUG,
            self::SECTION_ENDPOINT
        );

        add_settings_section(
            self::SECTION_GENERATION,
            __('Generation behaviour', 'bestony-ai-provider-for-xiaomi-mimo'),
            [self::class, 'renderGenerationSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            XiaomimimoConfig::OPTION_THINKING,
            __('Thinking mode', 'bestony-ai-provider-for-xiaomi-mimo'),
            [self::class, 'renderThinkingField'],
            self::PAGE_SLUG,
            self::SECTION_GENERATION
        );

        add_settings_field(
            XiaomimimoConfig::OPTION_STRUCTURED_OUTPUT,
            __('Structured output', 'bestony-ai-provider-for-xiaomi-mimo'),
            [self::class, 'renderStructuredOutputField'],
            self::PAGE_SLUG,
            self::SECTION_GENERATION
        );
    }

    /**
     * Adds the page under the Settings menu.
     *
     * @return void
     */
    public static function addPage(): void
    {
        add_options_page(
            __('Bestony AI Provider for Xiaomi MiMo', 'bestony-ai-provider-for-xiaomi-mimo'),
            __('Bestony AI Provider for Xiaomi MiMo', 'bestony-ai-provider-for-xiaomi-mimo'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    /**
     * Adds a Setup Token Plan shortcut to the plugin's row on the Plugins screen.
     *
     * @param array<string, string> $links The existing action links.
     * @return array<string, string> The action links, with the shortcut appended.
     */
    public static function addActionLink(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        $links[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Setup Token Plan', 'bestony-ai-provider-for-xiaomi-mimo')
        );

        return $links;
    }

    /**
     * Validates a submitted base URL against the allowed list.
     *
     * @param mixed $value The submitted value.
     * @return string An allowed base URL, or the default when the value is not one.
     */
    public static function sanitizeBaseUrl($value): string
    {
        $value = is_string($value) ? rtrim(trim($value), '/') : '';

        return XiaomimimoConfig::isAllowedBaseUrl($value) ? $value : XiaomimimoConfig::DEFAULT_BASE_URL;
    }

    /**
     * Validates a submitted thinking mode.
     *
     * @param mixed $value The submitted value.
     * @return string An allowed thinking mode, or the default when the value is not one.
     */
    public static function sanitizeThinking($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, XiaomimimoConfig::THINKING_MODES, true)
            ? $value
            : XiaomimimoConfig::THINKING_DEFAULT;
    }

    /**
     * Validates a submitted structured-output mode.
     *
     * @param mixed $value The submitted value.
     * @return string An allowed mode, or the default when the value is not one.
     */
    public static function sanitizeStructuredOutput($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, XiaomimimoConfig::STRUCTURED_OUTPUT_MODES, true)
            ? $value
            : XiaomimimoConfig::STRUCTURED_OUTPUT_DEFAULT;
    }

    /**
     * Renders the settings page.
     *
     * @return void
     */
    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the API endpoint section description.
     *
     * @return void
     */
    public static function renderEndpointSection(): void
    {
        echo '<p>' . esc_html__(
            'Choose which Xiaomi MiMo host the provider talks to. Pick the standard API for pay-as-you-go keys, or the Token Plan cluster your key was provisioned for.',
            'bestony-ai-provider-for-xiaomi-mimo'
        ) . '</p>';
    }

    /**
     * Renders the generation behaviour section description.
     *
     * @return void
     */
    public static function renderGenerationSection(): void
    {
        echo '<p>' . esc_html__(
            'These control how requests are shaped. They apply to every model and every generation.',
            'bestony-ai-provider-for-xiaomi-mimo'
        ) . '</p>';
    }

    /**
     * Renders the base URL dropdown.
     *
     * @return void
     */
    public static function renderBaseUrlField(): void
    {
        $name = XiaomimimoConfig::OPTION_BASE_URL;
        $current = self::getStoredChoice(
            $name,
            XiaomimimoConfig::DEFAULT_BASE_URL,
            XiaomimimoConfig::getBaseUrlChoices(),
            false
        );

        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach (self::getBaseUrlLabels() as $url => $label) {
            echo '<option value="' . esc_attr($url) . '"' . selected($url, $current, false) . '>'
                . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__(
            'The model list and every generation request use this base URL. The XIAOMIMIMO_BASE_URL environment variable or PHP constant, when set, overrides it.',
            'bestony-ai-provider-for-xiaomi-mimo'
        ) . '</p>';
    }

    /**
     * Renders the thinking-mode dropdown.
     *
     * @return void
     */
    public static function renderThinkingField(): void
    {
        $name = XiaomimimoConfig::OPTION_THINKING;
        $current = self::getStoredChoice(
            $name,
            XiaomimimoConfig::THINKING_DEFAULT,
            XiaomimimoConfig::THINKING_MODES
        );

        $choices = [
            XiaomimimoConfig::THINKING_DEFAULT => __('Use the model default', 'bestony-ai-provider-for-xiaomi-mimo'),
            'enabled' => __('Always enable thinking', 'bestony-ai-provider-for-xiaomi-mimo'),
            'disabled' => __('Disable thinking', 'bestony-ai-provider-for-xiaomi-mimo'),
        ];

        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($choices as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($value, $current, false) . '>'
                . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__(
            'MiMo enables thinking by default, which is slower and ignores temperature and top_p. Disable it for cheaper, faster calls that honour those parameters. XIAOMIMIMO_THINKING overrides this setting.',
            'bestony-ai-provider-for-xiaomi-mimo'
        ) . '</p>';
    }

    /**
     * Renders the structured-output dropdown.
     *
     * @return void
     */
    public static function renderStructuredOutputField(): void
    {
        $name = XiaomimimoConfig::OPTION_STRUCTURED_OUTPUT;
        $current = self::getStoredChoice(
            $name,
            XiaomimimoConfig::STRUCTURED_OUTPUT_DEFAULT,
            XiaomimimoConfig::STRUCTURED_OUTPUT_MODES
        );

        $choices = [
            'json_object' => __('JSON mode (json_object)', 'bestony-ai-provider-for-xiaomi-mimo'),
            'none' => __('Send no response_format', 'bestony-ai-provider-for-xiaomi-mimo'),
        ];

        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($choices as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($value, $current, false) . '>'
                . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__(
            'MiMo implements OpenAI JSON mode only, not schema-constrained output; features that ask for a JSON schema are downgraded to json_object. XIAOMIMIMO_STRUCTURED_OUTPUT overrides this setting.',
            'bestony-ai-provider-for-xiaomi-mimo'
        ) . '</p>';
    }

    /**
     * Gets the labels for the offered base URLs, keyed by URL.
     *
     * The URL list itself is owned by {@see XiaomimimoConfig}; a URL without a label falls back to the
     * URL, so adding one there can never render a blank option.
     *
     * @return array<string, string> Base URL to translated label.
     */
    private static function getBaseUrlLabels(): array
    {
        $labels = [
            XiaomimimoConfig::BASE_URL_STANDARD => __('Xiaomi MiMo (standard API)', 'bestony-ai-provider-for-xiaomi-mimo'),
            XiaomimimoConfig::BASE_URL_TOKEN_PLAN_CN => __('Token Plan — China', 'bestony-ai-provider-for-xiaomi-mimo'),
            XiaomimimoConfig::BASE_URL_TOKEN_PLAN_SGP => __('Token Plan — Singapore', 'bestony-ai-provider-for-xiaomi-mimo'),
            XiaomimimoConfig::BASE_URL_TOKEN_PLAN_AMS => __('Token Plan — Europe', 'bestony-ai-provider-for-xiaomi-mimo'),
        ];

        $choices = [];
        foreach (XiaomimimoConfig::getBaseUrlChoices() as $url) {
            $choices[$url] = $labels[$url] ?? $url;
        }

        return $choices;
    }

    /**
     * Gets the value to preselect in a dropdown.
     *
     * The stored option is shown rather than the effective value, so the control always has a valid
     * selection even when an environment variable overrides the setting.
     *
     * @param string $name The option name.
     * @param string $default The fallback when the stored value is unusable.
     * @param list<string> $allowed The values the control offers.
     * @param bool $caseInsensitive Whether to lowercase the stored value before comparing.
     * @return string One of the allowed values.
     */
    private static function getStoredChoice(string $name, string $default, array $allowed, bool $caseInsensitive = true): string
    {
        $value = get_option($name, $default);
        $value = is_string($value) ? trim($value) : '';
        if ($caseInsensitive) {
            $value = strtolower($value);
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
