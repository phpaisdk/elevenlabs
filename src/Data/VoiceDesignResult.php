<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class VoiceDesignResult
{
    /**
     * @param  list<VoicePreview>  $previews
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public array $previews,
        public string $text,
        public array $rawResponse = [],
    ) {}
}
