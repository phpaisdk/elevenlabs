<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class DubbingTranscript
{
    /**
     * @param  array<string, mixed>|null  $structured
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $format,
        public ?string $content = null,
        public ?array $structured = null,
        public array $rawResponse = [],
    ) {}
}
