<?php

/**
 * Shared request creation for Xiaomi MiMo models.
 *
 * @package XiaomiMiMo\AiProvider
 */

declare(strict_types=1);

namespace XiaomiMiMo\AiProvider\Models;

use XiaomiMiMo\AiProvider\Provider\XiaomimimoProvider;
use XiaomiMiMo\AiProvider\Util\XiaomimimoConfig;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Builds requests against the Xiaomi MiMo API.
 *
 * MiMo documents a custom `api-key` request header, but also accepts `Authorization: Bearer <key>`,
 * which the SDK's default API key authentication already applies — so no custom authentication class
 * is needed.
 */
trait XiaomimimoRequestTrait
{
    /**
     * Creates a request object for the MiMo API.
     *
     * Satisfies the abstract `createRequest()` of the OpenAI-compatible base class.
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URL.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $headers['User-Agent'] = XiaomimimoConfig::getUserAgent();

        return new Request(
            $method,
            XiaomimimoProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
