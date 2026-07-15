<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

use AiSdk\Results\AudioData;

final readonly class DetailedMusicResult
{
    /**
     * @param  array<string, mixed>  $compositionPlan
     * @param  array<string, mixed>  $songMetadata
     * @param  array<string, mixed>  $rawMetadata
     */
    public function __construct(
        public AudioData $output,
        public array $compositionPlan,
        public array $songMetadata,
        public ?string $songId = null,
        public array $rawMetadata = [],
    ) {}
}
