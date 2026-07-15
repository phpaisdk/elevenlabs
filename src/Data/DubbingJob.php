<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class DubbingJob
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public string $id,
        public float $expectedDuration,
        public array $rawResponse = [],
    ) {}
}
