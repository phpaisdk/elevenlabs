<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\Content;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\Results\AudioResult;

/** ElevenLabs Speech-to-Speech / Voice Changer; intentionally provider-specific. */
final readonly class VoiceChanger
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function convert(string $voiceId, Content $audio, array $options = []): AudioResult
    {
        $query = [];
        foreach (['enable_logging', 'optimize_streaming_latency', 'output_format'] as $name) {
            $value = $options[$name] ?? null;
            unset($options[$name]);

            if (is_scalar($value) || $value === null) {
                $query[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
        }

        return (new MediaRequest($this->options))->audio(
            '/speech-to-speech/' . rawurlencode($voiceId),
            $audio,
            $options,
            $query,
        );
    }
}
