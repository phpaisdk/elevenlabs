<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\ElevenLabs\Data\CreatedVoice;
use AiSdk\ElevenLabs\Data\VoiceDesignResult;
use AiSdk\ElevenLabs\Data\VoicePreview;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\Results\AudioData;

final readonly class VoiceDesign
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function design(string $description, array $options = []): VoiceDesignResult
    {
        $query = $this->outputFormatQuery($options);
        $payload = (new MediaRequest($this->options))->json(
            '/text-to-voice/design',
            array_replace(['voice_description' => $description], $options),
            $query,
        );

        return $this->previews($payload);
    }

    /** @param array<string, mixed> $options */
    public function remix(string $voiceId, string $description, array $options = []): VoiceDesignResult
    {
        $query = $this->outputFormatQuery($options);
        $payload = (new MediaRequest($this->options))->json(
            '/text-to-voice/' . rawurlencode($voiceId) . '/remix',
            array_replace(['voice_description' => $description], $options),
            $query,
        );

        return $this->previews($payload);
    }

    /** @param array<string, mixed> $options */
    public function create(string $generatedVoiceId, string $name, string $description, array $options = []): CreatedVoice
    {
        $payload = (new MediaRequest($this->options))->json('/text-to-voice', array_replace([
            'voice_name' => $name,
            'voice_description' => $description,
            'generated_voice_id' => $generatedVoiceId,
        ], $options));

        return new CreatedVoice(
            id: is_string($payload['voice_id'] ?? null) ? $payload['voice_id'] : '',
            name: is_string($payload['name'] ?? null) ? $payload['name'] : $name,
            category: is_string($payload['category'] ?? null) ? $payload['category'] : null,
            description: is_string($payload['description'] ?? null) ? $payload['description'] : null,
            rawResponse: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, scalar|null>
     */
    private function outputFormatQuery(array &$options): array
    {
        $outputFormat = $options['output_format'] ?? null;
        unset($options['output_format']);

        return is_scalar($outputFormat) ? ['output_format' => $outputFormat] : [];
    }

    /** @param array<string, mixed> $payload */
    private function previews(array $payload): VoiceDesignResult
    {
        $previews = [];
        foreach (is_array($payload['previews'] ?? null) ? $payload['previews'] : [] as $preview) {
            if (! is_array($preview)) {
                continue;
            }

            $encoded = is_string($preview['audio_base_64'] ?? null) ? $preview['audio_base_64'] : '';
            $decoded = base64_decode($encoded, true);
            $previews[] = new VoicePreview(
                generatedVoiceId: is_string($preview['generated_voice_id'] ?? null) ? $preview['generated_voice_id'] : '',
                audio: new AudioData(
                    data: is_string($decoded) ? $decoded : '',
                    mimeType: is_string($preview['media_type'] ?? null) ? $preview['media_type'] : 'audio/mpeg',
                    duration: is_numeric($preview['duration_secs'] ?? null) ? (float) $preview['duration_secs'] : null,
                ),
                language: is_string($preview['language'] ?? null) ? $preview['language'] : null,
            );
        }

        return new VoiceDesignResult(
            previews: $previews,
            text: is_string($payload['text'] ?? null) ? $payload['text'] : '',
            rawResponse: $payload,
        );
    }
}
