<?php

/**
 * Model catalog helpers.
 *
 * Pure string logic, no WordPress and no SDK: this is the part of the plugin worth unit testing, and
 * `scripts/selfcheck.php` does exactly that.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Util;

/**
 * Classification rules for Xiaomi MiMo model IDs.
 *
 * MiMo's `/models` endpoint reports only `id`, `object` and `owned_by` — no capability data at all —
 * so every capability this plugin declares is derived from the model ID here.
 */
final class XiaomimimoModelCatalog
{
    /**
     * Model IDs that accept image input.
     *
     * Sources (checked 2026-09-22):
     *  - 图片理解 guide, "支持的模型列表": `mimo-v2.6-flash`, `mimo-v2.6-pro`,
     *    `mimo-v2.6-pro-ultraspeed` and `mimo-v2.5` —
     *    https://mimo.mi.com/docs/zh-CN/quick-start/usage-guide/multimodal-understanding/image-understanding
     *  - `mimo-v2.5-pro` is text-only, so it is deliberately *not* matched by the `^mimo-v2\.5$`
     *    pattern (that one is anchored, so `mimo-v2.5-pro` and `mimo-v2.5-asr` fall through).
     *
     * MiMo only *adds* vision support over time, and a model wrongly declared here 400s rather than
     * silently degrading, so the list is kept as narrow as the evidence. Escape hatch for deployments
     * ahead of this list: `XIAOMIMIMO_MODEL_INPUT_MODALITIES=text,image`.
     *
     * @var list<string>
     */
    private const VISION_MODEL_PATTERNS = [
        '#^mimo-v2\.6-#',
        '#^mimo-v2\.5$#',
        '#-omni$#',
    ];

    /**
     * Model ID patterns that are not text-generation models at all.
     *
     * Checked *before* the catch-all chat rule, so a new speech family cannot be mistaken for a chat
     * model.
     *
     * @var list<string>
     */
    private const NON_TEXT_MODEL_PATTERNS = [
        // Speech recognition (MiMo-V2.5-ASR).
        '#-asr$#',
        // Speech synthesis, including the voice-clone and voice-design variants.
        '#-tts(?:-|$)#',
    ];

    /**
     * Whether a model accepts image input.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model accepts images.
     */
    public static function supportsImageInput(string $modelId): bool
    {
        foreach (self::VISION_MODEL_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a model can generate text at all.
     *
     * Speech models are excluded. Everything else in the `mimo-` namespace is treated as a chat
     * model: MiMo's public surface is entirely `mimo-*`, and falling back to "text" keeps a newly
     * released chat model usable instead of silently absent from every picker.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model generates text.
     */
    public static function isTextModel(string $modelId): bool
    {
        foreach (self::NON_TEXT_MODEL_PATTERNS as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return false;
            }
        }

        return strpos($modelId, 'mimo-') === 0;
    }

    /**
     * Whether this plugin declares no capability for a model.
     *
     * MiMo serves no image-generation models, so anything that is not a chat model is unsupported.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model is unsupported.
     */
    public static function isUnsupported(string $modelId): bool
    {
        return !self::isTextModel($modelId);
    }

    /**
     * Whether a model rejects non-default sampling parameters.
     *
     * Empty on purpose. MiMo documents `temperature` and `top_p` for every chat model, and accepts
     * `frequency_penalty` / `presence_penalty` too; while thinking is enabled the model silently
     * forces its own defaults rather than rejecting the request, so there is no 400 to guard against.
     * Add a pattern here only after an upstream 400 has been observed; the corresponding options must
     * then also be removed in the metadata directory.
     *
     * @param string $modelId The model ID.
     * @return bool Whether sampling parameters must not be sent.
     */
    public static function rejectsSamplingParameters(string $modelId): bool
    {
        return false;
    }

    /**
     * Whether a model is a preview or experimental variant.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model is a preview.
     */
    public static function isPreview(string $modelId): bool
    {
        return stripos($modelId, 'preview') !== false
            || stripos($modelId, '-exp') !== false
            || stripos($modelId, 'beta') !== false;
    }

    /**
     * Orders model IDs so the models a user is most likely to want appear first.
     *
     * Not a judgement about which model is best: the goal is that the first entry in the picker is a
     * current flagship rather than something alphabetically lucky.
     *
     * @param string $modelIdA The first model ID.
     * @param string $modelIdB The second model ID.
     * @return int Negative if the first model should sort first, positive otherwise.
     */
    public static function compareModelIds(string $modelIdA, string $modelIdB): int
    {
        // Preview and experimental variants are rate-limited and short-lived.
        $previewDelta = (int) self::isPreview($modelIdA) <=> (int) self::isPreview($modelIdB);
        if ($previewDelta !== 0) {
            return $previewDelta;
        }

        /*
         * Models that can actually do something come before ones the plugin declares no capability
         * for (the ASR and TTS families).
         */
        $unsupportedDelta = (int) self::isUnsupported($modelIdA) <=> (int) self::isUnsupported($modelIdB);
        if ($unsupportedDelta !== 0) {
            return $unsupportedDelta;
        }

        /*
         * Newer generation first. MiMo names generations with a version after the `v` (`mimo-v2.6-pro`,
         * `mimo-v2.5`), so compare those numerically — and put versioned IDs ahead of a hypothetical
         * unversioned one, which a plain string sort gets backwards because digits sort before letters.
         */
        $versionA = self::generationVersion($modelIdA);
        $versionB = self::generationVersion($modelIdB);
        if ($versionA === null || $versionB === null) {
            $versionedDelta = (int) ($versionA === null) <=> (int) ($versionB === null);
            if ($versionedDelta !== 0) {
                return $versionedDelta;
            }
        } elseif (version_compare($versionA, $versionB, '!=')) {
            return version_compare($versionA, $versionB, '>') ? -1 : 1;
        }

        // Same generation: a plainer name first, then reverse-natural for a stable order.
        return -strnatcasecmp($modelIdA, $modelIdB);
    }

    /**
     * Extracts the generation number from a model ID.
     *
     * @param string $modelId The model ID.
     * @return string|null The version, or null when the ID carries none.
     */
    private static function generationVersion(string $modelId): ?string
    {
        if (preg_match('/^mimo-v(\d+(?:\.\d+)*)/', $modelId, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
