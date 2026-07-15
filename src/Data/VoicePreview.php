<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

use AiSdk\Results\AudioData;

final readonly class VoicePreview
{
    public function __construct(
        public string $generatedVoiceId,
        public AudioData $audio,
        public ?string $language = null,
    ) {}
}
