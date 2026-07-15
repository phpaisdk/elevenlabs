<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs;

use AiSdk\Support\Sdk;
use AiSdk\Utils\Support\Env;
use AiSdk\Utils\Support\Url;

final class ElevenLabsOptions
{
    public const string PROVIDER_NAME = 'elevenlabs';

    public const string DEFAULT_BASE_URL = 'https://api.elevenlabs.io/v1';

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly array $headers = [],
        public readonly ?Sdk $sdk = null,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config = []): self
    {
        $headers = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : [];

        return new self(
            apiKey: Env::loadApiKey($config['apiKey'] ?? null, 'ELEVENLABS_API_KEY', self::PROVIDER_NAME),
            baseUrl: Url::withoutTrailingSlash(Env::loadOptionalSetting($config['baseUrl'] ?? null, 'ELEVENLABS_BASE_URL') ?? self::DEFAULT_BASE_URL),
            headers: $headers,
            sdk: ($config['sdk'] ?? null) instanceof Sdk ? $config['sdk'] : null,
        );
    }

    /** @return array<string, string> */
    public function authHeaders(): array
    {
        return array_replace(['xi-api-key' => $this->apiKey], $this->headers);
    }
}
