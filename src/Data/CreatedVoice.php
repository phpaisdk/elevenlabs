<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class CreatedVoice
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $category = null,
        public ?string $description = null,
        public array $rawResponse = [],
    ) {}
}
