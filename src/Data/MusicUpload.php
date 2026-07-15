<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class MusicUpload
{
    /**
     * @param  array<string, mixed>|null  $compositionPlan
     * @param  list<array<string, mixed>>  $wordTimestamps
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $songId,
        public ?array $compositionPlan = null,
        public array $wordTimestamps = [],
        public array $rawResponse = [],
    ) {}
}
