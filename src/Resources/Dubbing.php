<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\Content;
use AiSdk\ElevenLabs\Data\BinaryData;
use AiSdk\ElevenLabs\Data\DubbingJob;
use AiSdk\ElevenLabs\Data\DubbingStatus;
use AiSdk\ElevenLabs\Data\DubbingTranscript;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;

final readonly class Dubbing
{
    public function __construct(private ElevenLabsOptions $options) {}

    /** @param array<string, mixed> $options */
    public function create(Content $media, string $targetLanguage, array $options = []): DubbingJob
    {
        $request = new MediaRequest($this->options);
        $parts = [];

        if ($media->url() !== null) {
            $parts[] = ['name' => 'source_url', 'value' => $media->url()];
        } else {
            $parts[] = $request->contentPart('file', $media, 'source.bin', 'application/octet-stream');
        }

        $parts[] = ['name' => 'target_lang', 'value' => $targetLanguage];
        foreach ($options as $name => $value) {
            if ($value instanceof Content) {
                $parts[] = $request->contentPart($name, $value, $name, 'application/octet-stream');

                continue;
            }

            $parts[] = ['name' => $name, 'value' => $value];
        }

        $payload = $request->multipartJson('/dubbing', $parts);

        return new DubbingJob(
            id: is_string($payload['dubbing_id'] ?? null) ? $payload['dubbing_id'] : '',
            expectedDuration: is_numeric($payload['expected_duration_sec'] ?? null) ? (float) $payload['expected_duration_sec'] : 0.0,
            rawResponse: $payload,
        );
    }

    public function status(string $dubbingId): DubbingStatus
    {
        $payload = (new MediaRequest($this->options))->getJson('/dubbing/' . rawurlencode($dubbingId));
        $media = is_array($payload['media_metadata'] ?? null) ? $payload['media_metadata'] : [];
        $targetLanguages = array_values(array_filter(
            is_array($payload['target_languages'] ?? null) ? $payload['target_languages'] : [],
            is_string(...),
        ));

        return new DubbingStatus(
            id: is_string($payload['dubbing_id'] ?? null) ? $payload['dubbing_id'] : $dubbingId,
            name: is_string($payload['name'] ?? null) ? $payload['name'] : '',
            status: is_string($payload['status'] ?? null) ? $payload['status'] : '',
            sourceLanguage: is_string($payload['source_language'] ?? null) ? $payload['source_language'] : null,
            targetLanguages: $targetLanguages,
            createdAt: is_string($payload['created_at'] ?? null) ? $payload['created_at'] : '',
            editable: ($payload['editable'] ?? false) === true,
            mediaType: is_string($media['content_type'] ?? null) ? $media['content_type'] : null,
            mediaDuration: is_numeric($media['duration'] ?? null) ? (float) $media['duration'] : null,
            error: is_string($payload['error'] ?? null) ? $payload['error'] : null,
            rawResponse: $payload,
        );
    }

    public function output(string $dubbingId, string $languageCode): BinaryData
    {
        $request = new MediaRequest($this->options);

        return $request->binary($request->getRaw(
            '/dubbing/' . rawurlencode($dubbingId) . '/audio/' . rawurlencode($languageCode),
        ));
    }

    public function transcript(string $dubbingId, string $languageCode, string $format = 'srt'): DubbingTranscript
    {
        $payload = (new MediaRequest($this->options))->getJson(
            '/dubbing/' . rawurlencode($dubbingId)
            . '/transcripts/' . rawurlencode($languageCode)
            . '/format/' . rawurlencode($format),
        );
        $actualFormat = is_string($payload['transcript_format'] ?? null) ? $payload['transcript_format'] : $format;
        $content = $payload[$actualFormat] ?? null;

        return new DubbingTranscript(
            format: $actualFormat,
            content: is_string($content) ? $content : null,
            structured: is_array($payload['json'] ?? null) ? $payload['json'] : null,
            rawResponse: $payload,
        );
    }
}
