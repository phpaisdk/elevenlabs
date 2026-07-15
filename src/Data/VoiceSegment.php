<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class VoiceSegment
{
    public function __construct(
        public string $voiceId,
        public float $start,
        public float $end,
        public int $characterStart,
        public int $characterEnd,
        public int $dialogueInputIndex,
    ) {}
}
