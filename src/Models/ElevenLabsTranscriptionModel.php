<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Responses\TranscriptionResponse;
use AiSdk\Results\TranscriptData;
use AiSdk\Results\TranscriptSegment;
use AiSdk\Support\Json;
use AiSdk\Utils\Support\MultipartFormData;
use AiSdk\Utils\Support\Url;

final class ElevenLabsTranscriptionModel extends BaseModel implements TranscriptionModelInterface
{
    public function __construct(private readonly string $modelId, private readonly ElevenLabsOptions $options) {}

    public function provider(): string
    {
        return ElevenLabsOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        $audio = $request->audio;
        $options = $request->providerOptionsFor($this->provider());
        $enableLogging = $options['enable_logging'] ?? null;
        unset($options['enable_logging'], $options['source_url'], $options['cloud_storage_url']);
        $parts = [
            ['name' => 'model_id', 'value' => $this->modelId],
        ];
        foreach ($options as $name => $value) {
            $parts[] = ['name' => $name, 'value' => $value];
        }
        if ($audio->url() !== null) {
            $parts[] = ['name' => 'source_url', 'value' => $audio->url()];
        } else {
            $data = $audio->data();
            if ($data === null) {
                throw new InvalidArgumentException('ElevenLabs transcription requires file-backed, inline, or URL content.');
            }

            // Keeping file content last follows the SDK's other multipart STT
            // adapters and avoids buffering surprises in compatible gateways.
            $parts[] = ['name' => 'file', 'value' => $data, 'filename' => $audio->filename() ?: 'audio.wav', 'contentType' => $audio->mimeType() ?: 'audio/wav'];
        }
        $multipart = MultipartFormData::encode($parts);
        $sdk = $this->options->sdk ?? Generate::sdk();
        $url = Url::joinPath($this->options->baseUrl, '/speech-to-text');
        if (is_bool($enableLogging)) {
            $url .= '?' . http_build_query(['enable_logging' => $enableLogging ? 'true' : 'false']);
        }
        $httpRequest = $sdk->requestFactory->createRequest('POST', $url)
            ->withBody($sdk->streamFactory->createStream($multipart['body']))
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $multipart['boundary']);
        foreach ($this->options->authHeaders() as $name => $value) {
            $httpRequest = $httpRequest->withHeader($name, $value);
        }
        $response = $this->runner($sdk)->sendRequest($httpRequest, $this->provider());
        $payload = Json::decode((string) $response->getBody(), $this->provider());
        $transcripts = array_values(array_filter(
            is_array($payload['transcripts'] ?? null) ? $payload['transcripts'] : [],
            is_array(...),
        ));
        $text = is_string($payload['text'] ?? null)
            ? $payload['text']
            : implode("\n", array_values(array_filter(array_map(
                static fn(array $transcript): ?string => is_string($transcript['text'] ?? null) ? $transcript['text'] : null,
                $transcripts,
            ))));
        $firstTranscript = $transcripts[0] ?? [];
        $language = $payload['language_code'] ?? $firstTranscript['language_code'] ?? null;

        return new TranscriptionResponse(
            new TranscriptData(
                text: $text,
                language: is_string($language) ? $language : null,
                segments: $this->segments($payload, $transcripts),
            ),
            rawResponse: $payload,
            providerMetadata: [$this->provider() => ['model' => $this->modelId]],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<mixed>>  $transcripts
     * @return list<TranscriptSegment>
     */
    private function segments(array $payload, array $transcripts): array
    {
        $words = is_array($payload['words'] ?? null) ? $payload['words'] : [];
        if ($words === []) {
            foreach ($transcripts as $transcript) {
                foreach (is_array($transcript['words'] ?? null) ? $transcript['words'] : [] as $word) {
                    if (! is_array($word)) {
                        continue;
                    }

                    $word['channel_index'] ??= $transcript['channel_index'] ?? null;
                    $words[] = $word;
                }
            }
        }

        $segments = [];
        foreach ($words as $word) {
            if (! is_array($word) || ! is_string($word['text'] ?? null)) {
                continue;
            }

            $speaker = $word['speaker_id'] ?? $word['channel_index'] ?? null;
            $segments[] = new TranscriptSegment(
                text: $word['text'],
                start: is_numeric($word['start'] ?? null) ? (float) $word['start'] : 0.0,
                end: is_numeric($word['end'] ?? null) ? (float) $word['end'] : 0.0,
                speaker: is_string($speaker) || is_int($speaker) ? (string) $speaker : null,
            );
        }

        return $segments;
    }
}
