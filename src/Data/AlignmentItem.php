<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class AlignmentItem
{
    public function __construct(
        public string $text,
        public float $start,
        public float $end,
        public ?float $loss = null,
    ) {}
}
