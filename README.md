# Bestony AI Provider for Xiaomi MiMo

[Xiaomi MiMo](https://platform.xiaomimimo.com/) (小米 MiMo) as a provider for the WordPress AI
Client: text generation and multimodal understanding with MiMo models.

## What it does

* Fetches the model list live from `GET /v1/models`, so newly released models show up on their own.
* Text generation and chat history, including tool calling, structured output (JSON mode) and stop
  sequences.
* Vision input with the `mimo-v2.6-*` family and `mimo-v2.5`, so Alt Text Generation works.
* Reasoning output (`reasoning_content`) surfaces as thought parts instead of being mixed into the
  answer text.
* A **Settings → Bestony AI Provider for Xiaomi MiMo** page with three options: the API host (standard API or
  one of the Token Plan clusters), the thinking mode, and the structured-output mode.

## Requirements

* WordPress 7.0 or newer, with the PHP AI Client SDK (bundled in WordPress 7.0, or provided by the AI plugin)
* PHP 7.4 or newer
* A Xiaomi MiMo account with API access

## Install

Download the zip from [Releases](../../releases) and upload it through **Plugins → Add New → Upload
Plugin**, or copy the plugin folder to `wp-content/plugins/bestony-ai-provider/`. Activate it,
then open **Settings → Connectors**, open the Xiaomi MiMo card and paste your API key.

## Settings

**Settings → Bestony AI Provider for Xiaomi MiMo** has three options. Each is overridden by its matching
environment variable when one is set.

| Option | Values | Default | Environment variable |
| --- | --- | --- | --- |
| API host | Standard API, Token Plan China, Token Plan Singapore, Token Plan Europe | Standard API | `XIAOMIMIMO_BASE_URL` |
| Thinking mode | Model default, Enable, Disable | Model default | `XIAOMIMIMO_THINKING` |
| Structured output | JSON mode (`json_object`), None | JSON mode | `XIAOMIMIMO_STRUCTURED_OUTPUT` |

| API host | Base URL |
| --- | --- |
| Standard API (default) | `https://api.xiaomimimo.com/v1` |
| Token Plan — China | `https://token-plan-cn.xiaomimimo.com/v1` |
| Token Plan — Singapore | `https://token-plan-sgp.xiaomimimo.com/v1` |
| Token Plan — Europe | `https://token-plan-ams.xiaomimimo.com/v1` |

The choice is stored per site and is used for the model list and every generation request. A value
outside this list falls back to the default. The plugin's row on the Plugins screen also has a
**Setup Token Plan** shortcut to the page.

## Configuration

The API key is read in order of precedence from:

1. the `XIAOMIMIMO_API_KEY` environment variable
2. the `XIAOMIMIMO_API_KEY` PHP constant
3. the `connectors_ai_xiaomimimo_api_key` option (Settings → Connectors)

The remaining settings are environment variables or PHP constants:

| Setting | Default | Purpose |
| --- | --- | --- |
| `XIAOMIMIMO_DEFAULT_MODEL` | `mimo-v2.6-pro` | Chat model to prefer in pickers and feature filters |
| `XIAOMIMIMO_BASE_URL` | the host chosen in Settings | API base URL; overrides the settings page |
| `XIAOMIMIMO_THINKING` | the mode chosen in Settings | `default`, `enabled` or `disabled` |
| `XIAOMIMIMO_STRUCTURED_OUTPUT` | the mode chosen in Settings | `json_object` or `none` |
| `XIAOMIMIMO_MODEL_INPUT_MODALITIES` | from the built-in list | Comma-separated input modalities, e.g. `text,image`, to declare vision for every chat model |
| `XIAOMIMIMO_REQUEST_TIMEOUT` | `120` | Request timeout, seconds |
| `XIAOMIMIMO_CONNECT_TIMEOUT` | `10` | Connection timeout, seconds |

Changing a setting does not invalidate the AI Client's cached model list; run `wp cache flush` if a
new value does not take effect.

To change which model the AI plugin picks for its features, use the standard filter — this plugin
already puts its preferred model first:

```php
add_filter( 'wpai_preferred_text_models', function ( $models ) {
    array_unshift( $models, array( 'xiaomimimo', 'mimo-v2.6-flash' ) );
    return $models;
} );
```

## Notes and limitations

* **Authentication** uses `Authorization: Bearer <key>`. MiMo also documents a custom `api-key`
  header; both work, and the SDK's default API-key authentication sends the Bearer header.
* **Structured output.** MiMo implements OpenAI JSON mode (`response_format: {"type":"json_object"}`)
  but not schema-constrained output. Features that ask for a JSON schema are downgraded to
  `json_object`; if a feature returns nothing, try setting Structured output to **None** or point that
  feature at another provider.
* **Thinking** is on by default on MiMo's side and forces `temperature`/`top_p` to the model's own
  defaults. Set Thinking to **Disable** (or `XIAOMIMIMO_THINKING=disabled`) when you need those
  parameters to apply.
* **Not implemented:** image generation (MiMo serves none) and the ASR/TTS speech families. The
  speech models still appear in the model list, but with no capability they can never be selected.

## Data and privacy

Prompts you send to the AI, plus whatever the calling plugin or theme adds to them (system
instructions, conversation history, tool definitions, attached files), are sent to Xiaomi MiMo. Your
API key is stored on your own site and is only ever sent to the host configured above. Nothing is sent
until your site actually asks the AI Client for a generation.

* Platform and docs: <https://platform.xiaomimimo.com/> · <https://mimo.mi.com/docs/>
* Terms of Service: <https://mimo.mi.com/docs/quick-start/terms/user-agreement>
* Privacy Policy: <https://mimo.mi.com/docs/quick-start/terms/privacy-policy>

## Development

```
php scripts/selfcheck.php                                                     # logic checks, no WordPress needed
php scripts/selfcheck.php --sdk=/path/to/php-ai-client                        # plus live-SDK checks
```

The self-check asserts model classification, sort order, the declared capabilities and options, the
settings resolution, and the request shapes for chat and vision. It needs neither WordPress nor an
API key.

Release: push a tag matching the plugin version (`git tag v<version> && git push origin v<version>`).
The [release workflow](.github/workflows/release.yml) verifies the tag against `Version:`/`Stable tag:`,
verifies a matching changelog entry exists, builds the plugin zip and publishes it.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

The `assets/images/xiaomimimo.svg` mark comes from [Lobe Icons](https://github.com/lobehub/lobe-icons)
(MIT) and is a trademark of Xiaomi, used only to identify the service this plugin connects to.

The full WordPress plugin readme, including the changelog, lives in [readme.txt](readme.txt).
