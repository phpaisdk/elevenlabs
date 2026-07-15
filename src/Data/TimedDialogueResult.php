<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

use AiSdk\Results\AudioData;

final readonly class TimedDialogueResult
{
    /**
     * @param  list<VoiceSegment>  $voiceSegments
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public AudioData $output,
        public ?CharacterAlignment $alignment,
        public ?CharacterAlignment $normalizedAlignment,
        public array $voiceSegments,
        public array $rawResponse = [],
    ) {}
}
