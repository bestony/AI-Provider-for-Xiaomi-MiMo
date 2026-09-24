=== Bestony AI Provider for Xiaomi MiMo ===
Contributors:      bestony
Tags:              ai, connector, xiaomi, mimo, vision
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.0.1
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Xiaomi MiMo (小米 MiMo) provider for the PHP AI Client: text and multimodal understanding with MiMo models.

== Description ==

Adds [Xiaomi MiMo](https://platform.xiaomimimo.com/) (小米 MiMo) as a provider for the WordPress AI Client.

* The model list is fetched live from `GET /v1/models`, so newly released models appear on their own.
* Text generation and chat history with the MiMo chat models, including tool calling, structured
  output (JSON mode) and stop sequences.
* Vision (multimodal understanding): the `mimo-v2.6-*` family and `mimo-v2.5` accept images, so
  features like Alt Text Generation work.
* Reasoning output is surfaced as thought parts rather than mixed into the answer text.
* A **Settings → Bestony AI Provider for Xiaomi MiMo** page with three options: the API host (standard API or
  one of the Token Plan clusters), the thinking mode, and the structured-output mode.

== Screenshots ==

1. The Xiaomi MiMo connector card on Settings → Connectors, with the API key field.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/bestony-ai-provider-for-xiaomi-mimo/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings → Connectors, open the Xiaomi MiMo card and paste your API key — or define it
   outside the database (see Configuration).

A Xiaomi MiMo account with API access is required. Create a key at
[https://platform.xiaomimimo.com/console/api-key](https://platform.xiaomimimo.com/console/api-key).

== Settings ==

**Settings → Bestony AI Provider for Xiaomi MiMo** has three options. Each is overridden by its matching
environment variable when one is set.

**API host** — which Xiaomi MiMo host the provider talks to:

* Xiaomi MiMo (standard API) — `https://api.xiaomimimo.com/v1` (default)
* Token Plan — China — `https://token-plan-cn.xiaomimimo.com/v1`
* Token Plan — Singapore — `https://token-plan-sgp.xiaomimimo.com/v1`
* Token Plan — Europe — `https://token-plan-ams.xiaomimimo.com/v1`

Pick the standard API for a pay-as-you-go key, or the Token Plan cluster your subscription key was
provisioned for. The chosen host is used for the model list and for every generation request. It is
stored per site, so each site on a multisite network configures its own. Only these four hosts can be
selected: a value that is not one of them (written to the database directly, for example) falls back
to the default.

**Thinking mode** — `Use the model default` (default), `Always enable thinking`, or `Disable thinking`.
MiMo enables thinking by default; while it is on, the model forces its own `temperature`/`top_p` and
the answer arrives as `reasoning_content` plus content. Disable it for cheaper, faster calls that
honour those parameters.

**Structured output** — `JSON mode (json_object)` (default) or `Send no response_format`. MiMo
implements OpenAI JSON mode only, not schema-constrained output; a feature that asks for a JSON
schema is downgraded to `json_object`.

There is also a **Setup Token Plan** link in the plugin's row on the Plugins screen that jumps
straight to this page.

The admin screens are translatable; a translation template (`languages/bestony-ai-provider-for-xiaomi-mimo.pot`) ships with the plugin.

== Configuration ==

The API key is read, in order of precedence, from:

1. The `XIAOMIMIMO_API_KEY` environment variable
2. The `XIAOMIMIMO_API_KEY` PHP constant
3. The `connectors_ai_xiaomimimo_api_key` option (Settings → Connectors)

Each settings-page option is overridden by its environment variable, and the remaining behaviour is
controlled by environment variables or PHP constants too:

* `XIAOMIMIMO_DEFAULT_MODEL` — chat model to prefer in pickers and feature filters.
  Default: `mimo-v2.6-pro`.
* `XIAOMIMIMO_BASE_URL` — API base URL. Overrides the host chosen on Settings → AI Provider for
  Xiaomi MiMo.
* `XIAOMIMIMO_THINKING` — `default`, `enabled` or `disabled`. Overrides the Thinking mode setting.
* `XIAOMIMIMO_STRUCTURED_OUTPUT` — `json_object` or `none`. Overrides the Structured output setting.
* `XIAOMIMIMO_MODEL_INPUT_MODALITIES` — comma-separated list of modalities your models accept. Include
  `image` (e.g. `text,image`) to declare vision for **every** chat model, for deployments ahead of
  the built-in list.
* `XIAOMIMIMO_REQUEST_TIMEOUT` — request timeout in seconds. Default: `120`.
* `XIAOMIMIMO_CONNECT_TIMEOUT` — connection timeout in seconds. Default: `10`.

Because the model list is cached by the AI Client, changing a setting or an environment variable does
not invalidate that cache. If a new value does not take effect immediately, clear the cache — in
WP-CLI:

    wp cache flush
    wp transient delete --all

To influence which models the AI plugin picks, use the standard filters in your own plugin or theme —
this plugin already puts its preferred model first:

    add_filter( 'wpai_preferred_text_models', function ( $models ) {
        array_unshift( $models, array( 'xiaomimimo', 'mimo-v2.6-flash' ) );
        return $models;
    } );

== Frequently Asked Questions ==

= Does this plugin work without the PHP AI Client? =

No. It requires the PHP AI Client SDK, which is provided by WordPress 7.0 or by the AI plugin. The
provider stays silent when the SDK is missing.

= Do I need to configure anything in the database? =

The API key (on Settings → Connectors) and, if you change them from the defaults, the settings on
Settings → Bestony AI Provider for Xiaomi MiMo. Both can instead be set with an environment variable or
constant.

= Why does the plugin require WordPress 7.0? =

Because the Connectors screen and the `connectors_ai_xiaomimimo_api_key` option are WordPress 7.0
features. The SDK's own credential lookup reads only environment variables and constants, so on 6.9
you could paste a key into a UI that does not exist yet. WordPress 7.0 wires the stored key to the
provider for you.

= Which API host should I choose? =

The standard API (`https://api.xiaomimimo.com/v1`) for a pay-as-you-go key, which is billed against
your account balance. If you bought a Token Plan subscription, use the cluster that matches where the
plan was issued — China, Singapore or Europe — because Token Plan keys and their quota are separate
from pay-as-you-go keys.

= Which models can be used with vision features? =

`mimo-v2.6-pro`, `mimo-v2.6-flash`, `mimo-v2.6-pro-ultraspeed` and `mimo-v2.5`. The model list
endpoint reports no capabilities, so this list lives in the plugin source. If your model supports
images but is not on the list, set `XIAOMIMIMO_MODEL_INPUT_MODALITIES=text,image`.

= A JSON feature fails or returns nothing =

MiMo implements OpenAI JSON mode only (`response_format: {"type":"json_object"}`) and rejects a JSON
schema, so this plugin sends `json_object` and drops the schema. MiMo guarantees valid JSON but not
the exact fields, so the model relies on the prompt for the shape. Try setting Structured output to
**Send no response_format** if a feature is unhappy, or point that feature at another provider.

= Why does temperature have no effect? =

MiMo forces its own `temperature`/`top_p` while thinking is enabled, which is the default. Set
Thinking mode to `Disable thinking` (or `XIAOMIMIMO_THINKING=disabled`) to make those parameters
apply.

= Why is there no image generation? =

MiMo's open platform serves text/multimodal-understanding and speech models, not text-to-image, so the
plugin declares no image-generation capability.

= Why is there no speech (ASR/TTS) support? =

The PHP AI Client exposes text, image, speech, video and embedding entry points, and the MiMo speech
endpoints do not fit the shape the SDK sends. The ASR/TTS models still appear in the model list, but
with no capability they cannot be selected.

= Where is my API key stored? =

In the WordPress options table on your own site, under the AI Client's connector option
(`connectors_ai_xiaomimimo_api_key`), or in an environment variable or PHP constant if you set one. It
is never transmitted anywhere except to the Xiaomi MiMo host configured above.

= What data leaves my site? =

Only what you send to the AI: your prompts, and whatever the calling plugin or theme adds to them
(system instructions, conversation history, tool definitions, attached files). See External services
below for the exact endpoints.

= Is there a settings page? =

Yes: **Settings → Bestony AI Provider for Xiaomi MiMo**, which chooses the API host, the thinking mode and the
structured-output mode. The API key still lives on Settings → Connectors.

== External services ==

This plugin connects to the Xiaomi MiMo API, an external service operated by Xiaomi (小米). It is
required so the WordPress AI Client can send requests to MiMo models from your site. MiMo is a paid
service: requests are billed to your Xiaomi MiMo account, and an account with API access is required.

The plugin contacts the following endpoints under the selected base URL — `https://api.xiaomimimo.com/v1`
by default, or whichever host is chosen on Settings → Bestony AI Provider for Xiaomi MiMo:

* `GET /models` — called when the AI Client refreshes its list of available models, and when it checks
  whether your credentials work. No user content is sent; only your API key, so MiMo can return the
  models available to your account.
* `POST /chat/completions` — called whenever any plugin or theme on your site uses the WordPress AI
  Client to generate text or to analyze an image. The request carries your API key and the prompt:
  messages, system instruction, tool definitions, and any other parameters the calling code supplied
  (conversation history, a JSON schema for structured output, or images attached to the prompt).

No request is made until something on your site asks the AI Client for a generation, or the AI Client
refreshes its model list. Your API key is stored on your own site and is only ever sent to the host
configured above.

This service is provided by Xiaomi:

* Platform and documentation: [https://platform.xiaomimimo.com/](https://platform.xiaomimimo.com/)
* Documentation hub: [https://mimo.mi.com/docs/](https://mimo.mi.com/docs/)
* Terms of Service: [https://mimo.mi.com/docs/quick-start/terms/user-agreement](https://mimo.mi.com/docs/quick-start/terms/user-agreement)
* Privacy Policy: [https://mimo.mi.com/docs/quick-start/terms/privacy-policy](https://mimo.mi.com/docs/quick-start/terms/privacy-policy)

== Changelog ==

= 1.0.1 =
* The release zip no longer includes the `.wordpress-org` directory, which is only used for the
  WordPress.org plugin directory listing.
* Renamed to Bestony AI Provider for Xiaomi MiMo: the plugin slug, main file and text domain are now
  `bestony-ai-provider-for-xiaomi-mimo`. Settings, the API key and environment variables are unchanged.

= 1.0.0 =
* Initial release: text generation, chat history, tool calling, structured output (JSON mode) and
  vision input with Xiaomi MiMo (小米 MiMo) models.
* A Settings → Bestony AI Provider for Xiaomi MiMo page to choose the API host (standard API or the China,
  Singapore and Europe Token Plan clusters), the thinking mode and the structured-output mode.

== Upgrade Notice ==

= 1.0.1 =
Renames the plugin to Bestony AI Provider for Xiaomi MiMo (slug `bestony-ai-provider-for-xiaomi-mimo`) and stops shipping the `.wordpress-org` directory in the release zip. Existing settings and keys keep working.

= 1.0.0 =
Initial release.
