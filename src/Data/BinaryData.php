<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

use AiSdk\Exceptions\AiSdkException;

final readonly class BinaryData
{
    public function __construct(
        public string $data,
        public string $mimeType = 'application/octet-stream',
        public ?string $filename = null,
    ) {}

    public function save(string $path): string
    {
        if (file_put_contents($path, $this->data) === false) {
            throw new AiSdkException("Unable to write binary data to [{$path}].");
        }

        return $path;
    }
}
