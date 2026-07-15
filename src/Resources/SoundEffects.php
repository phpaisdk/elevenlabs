<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\Results\AudioResult;

final readonly class SoundEffects
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function generate(string $prompt, array $options = []): AudioResult
    {
        $format = $options['output_format'] ?? 'mp3_44100_128';
        unset($options['output_format']);

        return (new MediaRequest($this->options))->jsonAudio('/sound-generation', array_replace(['text' => $prompt], $options), ['output_format' => $format]);
    }
}
