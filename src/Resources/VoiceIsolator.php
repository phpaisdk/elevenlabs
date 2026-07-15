<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\Content;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\Results\AudioResult;

/** ElevenLabs Audio Isolation; intentionally provider-specific. */
final readonly class VoiceIsolator
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function isolate(Content $audio, array $options = []): AudioResult
    {
        return (new MediaRequest($this->options))->audio('/audio-isolation', $audio, $options);
    }
}
