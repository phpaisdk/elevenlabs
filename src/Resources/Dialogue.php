<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\ElevenLabs\Data\CharacterAlignment;
use AiSdk\ElevenLabs\Data\TimedDialogueResult;
use AiSdk\ElevenLabs\Data\VoiceSegment;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\Results\AudioData;
use AiSdk\Results\AudioResult;

final readonly class Dialogue
{
    public function __construct(private ElevenLabsOptions $options) {}

    /**
     * @param list<array{text: string, voice_id: string}> $inputs
     * @param array<string, mixed> $options
     */
    public function create(array $inputs, array $options = []): AudioResult
    {
        $query = $this->query($options);

        return (new MediaRequest($this->options))->jsonAudio('/text-to-dialogue', array_replace(['inputs' => $inputs], $options), $query);
    }

    /**
     * @param  list<array{text: string, voice_id: string}>  $inputs
     * @param  array<string, mixed>  $options
     */
    public function withTimestamps(array $inputs, array $options = []): TimedDialogueResult
    {
        $query = $this->query($options);
        $payload = (new MediaRequest($this->options))->json(
            '/text-to-dialogue/with-timestamps',
            array_replace(['inputs' => $inputs], $options),
            $query,
        );
        $encoded = is_string($payload['audio_base64'] ?? null) ? $payload['audio_base64'] : '';
        $decoded = base64_decode($encoded, true);
        $segments = [];
        foreach (is_array($payload['voice_segments'] ?? null) ? $payload['voice_segments'] : [] as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $segments[] = new VoiceSegment(
                voiceId: is_string($segment['voice_id'] ?? null) ? $segment['voice_id'] : '',
                start: is_numeric($segment['start_time_seconds'] ?? null) ? (float) $segment['start_time_seconds'] : 0.0,
                end: is_numeric($segment['end_time_seconds'] ?? null) ? (float) $segment['end_time_seconds'] : 0.0,
                characterStart: is_numeric($segment['character_start_index'] ?? null) ? (int) $segment['character_start_index'] : 0,
                characterEnd: is_numeric($segment['character_end_index'] ?? null) ? (int) $segment['character_end_index'] : 0,
                dialogueInputIndex: is_numeric($segment['dialogue_input_index'] ?? null) ? (int) $segment['dialogue_input_index'] : 0,
            );
        }

        return new TimedDialogueResult(
            output: new AudioData(
                data: is_string($decoded) ? $decoded : '',
                mimeType: $this->mimeType(is_string($query['output_format'] ?? null) ? $query['output_format'] : ''),
            ),
            alignment: $this->alignment($payload['alignment'] ?? null),
            normalizedAlignment: $this->alignment($payload['normalized_alignment'] ?? null),
            voiceSegments: $segments,
            rawResponse: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, scalar>
     */
    private function query(array &$options): array
    {
        $format = $options['output_format'] ?? 'mp3_44100_128';
        $enableLogging = $options['enable_logging'] ?? null;
        unset($options['output_format'], $options['enable_logging']);
        $query = ['output_format' => is_scalar($format) ? $format : 'mp3_44100_128'];
        if (is_bool($enableLogging)) {
            $query['enable_logging'] = $enableLogging ? 'true' : 'false';
        }

        return $query;
    }

    private function alignment(mixed $value): ?CharacterAlignment
    {
        if (! is_array($value)) {
            return null;
        }

        return new CharacterAlignment(
            characters: array_values(array_filter(is_array($value['characters'] ?? null) ? $value['characters'] : [], is_string(...))),
            startTimes: array_values(array_map(static fn(mixed $time): float => is_numeric($time) ? (float) $time : 0.0, is_array($value['character_start_times_seconds'] ?? null) ? $value['character_start_times_seconds'] : [])),
            endTimes: array_values(array_map(static fn(mixed $time): float => is_numeric($time) ? (float) $time : 0.0, is_array($value['character_end_times_seconds'] ?? null) ? $value['character_end_times_seconds'] : [])),
        );
    }

    private function mimeType(string $format): string
    {
        return match (true) {
            str_starts_with($format, 'wav_') => 'audio/wav',
            str_starts_with($format, 'pcm_') => 'audio/pcm',
            str_starts_with($format, 'opus_') => 'audio/opus',
            str_starts_with($format, 'ulaw_') => 'audio/basic',
            str_starts_with($format, 'alaw_') => 'audio/a-law',
            default => 'audio/mpeg',
        };
    }
}
