<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class DubbingStatus
{
    /**
     * @param  list<string>  $targetLanguages
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public ?string $sourceLanguage,
        public array $targetLanguages,
        public string $createdAt,
        public bool $editable = false,
        public ?string $mediaType = null,
        public ?float $mediaDuration = null,
        public ?string $error = null,
        public array $rawResponse = [],
    ) {}
}
