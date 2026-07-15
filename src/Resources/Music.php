<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\Content;
use AiSdk\ElevenLabs\Data\BinaryData;
use AiSdk\ElevenLabs\Data\DetailedMusicResult;
use AiSdk\ElevenLabs\Data\MusicPlan;
use AiSdk\ElevenLabs\Data\MusicUpload;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;
use AiSdk\ElevenLabs\Support\MultipartMixed;
use AiSdk\Results\AudioData;
use AiSdk\Results\AudioResult;
use AiSdk\Support\Json;

final readonly class Music
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function compose(string $prompt, array $options = []): AudioResult
    {
        $query = $this->outputFormatQuery($options, 'auto');

        return (new MediaRequest($this->options))->jsonAudio('/music', array_replace(['prompt' => $prompt], $options), $query);
    }

    /**
     * @param  MusicPlan|array<string, mixed>  $plan
     * @param  array<string, mixed>  $options
     */
    public function composeFromPlan(MusicPlan|array $plan, array $options = []): AudioResult
    {
        $query = $this->outputFormatQuery($options, 'auto');

        return (new MediaRequest($this->options))->jsonAudio('/music', array_replace([
            'composition_plan' => $plan instanceof MusicPlan ? $plan->value : $plan,
        ], $options), $query);
    }

    /** @param array<string, mixed> $options */
    public function plan(string $prompt, array $options = []): MusicPlan
    {
        return new MusicPlan((new MediaRequest($this->options))->json(
            '/music/plan',
            array_replace(['prompt' => $prompt], $options),
        ));
    }

    /** @param array<string, mixed> $options */
    public function composeDetailed(string $prompt, array $options = []): DetailedMusicResult
    {
        return $this->detailed(array_replace(['prompt' => $prompt], $options));
    }

    /**
     * @param  MusicPlan|array<string, mixed>  $plan
     * @param  array<string, mixed>  $options
     */
    public function composeDetailedFromPlan(MusicPlan|array $plan, array $options = []): DetailedMusicResult
    {
        return $this->detailed(array_replace([
            'composition_plan' => $plan instanceof MusicPlan ? $plan->value : $plan,
        ], $options));
    }

    /**
     * @param  Content|list<Content>  $videos
     * @param  array<string, mixed>  $options
     */
    public function videoToMusic(Content|array $videos, array $options = []): AudioResult
    {
        $query = $this->outputFormatQuery($options, 'mp3_44100_128');
        $request = new MediaRequest($this->options);
        $parts = [];
        foreach ($videos instanceof Content ? [$videos] : $videos as $index => $video) {
            $parts[] = $request->contentPart('videos', $video, 'video-' . $index . '.mp4', 'video/mp4');
        }
        foreach ($options as $name => $value) {
            $parts[] = ['name' => $name, 'value' => $value];
        }
        $response = $request->multipart('/music/video-to-music', $parts, $query);

        return new AudioResult(new AudioData(
            data: (string) $response->getBody(),
            mimeType: $response->getHeaderLine('Content-Type') ?: 'audio/mpeg',
        ));
    }

    /** @param array<string, mixed> $options */
    public function separateStems(Content $audio, array $options = []): BinaryData
    {
        $query = $this->outputFormatQuery($options, 'mp3_44100_128');
        $request = new MediaRequest($this->options);
        $parts = [$request->contentPart('file', $audio, 'music.mp3', 'audio/mpeg')];
        foreach ($options as $name => $value) {
            $parts[] = ['name' => $name, 'value' => $value];
        }

        return $request->binary($request->multipart('/music/stem-separation', $parts, $query));
    }

    /** @param array<string, mixed> $options */
    public function upload(Content $audio, array $options = []): MusicUpload
    {
        $request = new MediaRequest($this->options);
        $parts = [$request->contentPart('file', $audio, 'music.mp3', 'audio/mpeg')];
        foreach ($options as $name => $value) {
            $parts[] = ['name' => $name, 'value' => $value];
        }
        $payload = $request->multipartJson('/music/upload', $parts);
        $wordTimestamps = array_values(array_filter(
            is_array($payload['words_timestamps'] ?? null) ? $payload['words_timestamps'] : [],
            is_array(...),
        ));

        return new MusicUpload(
            songId: is_string($payload['song_id'] ?? null) ? $payload['song_id'] : '',
            compositionPlan: is_array($payload['composition_plan'] ?? null) ? $payload['composition_plan'] : null,
            wordTimestamps: $wordTimestamps,
            rawResponse: $payload,
        );
    }

    /** @param array<string, mixed> $body */
    private function detailed(array $body): DetailedMusicResult
    {
        $query = $this->outputFormatQuery($body, 'auto');
        $response = (new MediaRequest($this->options))->jsonResponse(
            '/music/detailed',
            $body,
            $query,
            ['Accept' => 'multipart/mixed'],
        );
        $metadata = [];
        $audio = '';
        $mimeType = 'audio/mpeg';
        foreach (MultipartMixed::parse($response) as $part) {
            $partType = $part['headers']['content-type'] ?? '';
            if (str_contains($partType, 'application/json')) {
                $metadata = Json::decode($part['body'], ElevenLabsOptions::PROVIDER_NAME);

                continue;
            }
            if (str_starts_with($partType, 'audio/')) {
                $audio = $part['body'];
                $mimeType = explode(';', $partType, 2)[0];
            }
        }

        return new DetailedMusicResult(
            output: new AudioData($audio, $mimeType),
            compositionPlan: is_array($metadata['composition_plan'] ?? null) ? $metadata['composition_plan'] : [],
            songMetadata: is_array($metadata['song_metadata'] ?? null) ? $metadata['song_metadata'] : [],
            songId: $response->getHeaderLine('song-id') ?: null,
            rawMetadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{output_format: scalar}
     */
    private function outputFormatQuery(array &$options, string $default): array
    {
        $format = $options['output_format'] ?? $default;
        unset($options['output_format']);

        return ['output_format' => is_scalar($format) ? $format : $default];
    }
}
