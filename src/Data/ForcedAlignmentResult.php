<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class ForcedAlignmentResult
{
    /**
     * @param  list<AlignmentItem>  $characters
     * @param  list<AlignmentItem>  $words
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public array $characters,
        public array $words,
        public float $loss,
        public array $rawResponse = [],
    ) {}
}
