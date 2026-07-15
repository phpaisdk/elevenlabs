<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;
use AiSdk\Results\AudioData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class ElevenLabsSpeechModel extends BaseModel implements SpeechModelInterface
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

    public function generate(SpeechRequest $request): SpeechResponse
    {
        $options = $request->providerOptionsFor($this->provider());
        $voiceId = $request->voice ?? ($options['voice_id'] ?? null);
        if (! is_string($voiceId) || $voiceId === '') {
            throw new InvalidArgumentException('ElevenLabs speech requires ->voice("voice-id").');
        }

        unset($options['voice_id']);
        $query = $this->takeQueryOptions($options);
        $format = is_string($query['output_format'] ?? null)
            ? $query['output_format']
            : $this->format($request->format);
        $query['output_format'] = $format;
        $body = array_replace(['text' => $request->input, 'model_id' => $this->modelId], $options);
        $url = Url::joinPath($this->options->baseUrl, '/text-to-speech/' . rawurlencode($voiceId)) . '?' . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
        $response = $this->runner($this->options->sdk)->postRaw($url, $body, array_replace(['Accept' => $this->mime($format)], $this->options->authHeaders()), $this->provider());

        return new SpeechResponse(new AudioData((string) $response->getBody(), $response->getHeaderLine('Content-Type') ?: $this->mime($format)), new Usage(), [], [$this->provider() => ['model' => $this->modelId, 'voice_id' => $voiceId]]);
    }

    private function format(?string $format): string
    {
        return match ($format) {
            'wav' => 'wav_44100', 'pcm' => 'pcm_44100', 'ulaw' => 'ulaw_8000', 'alaw' => 'alaw_8000', default => 'mp3_44100_128',
        };
    }

    private function mime(string $format): string
    {
        return match (true) {
            str_starts_with($format, 'pcm_') => 'audio/pcm',
            str_starts_with($format, 'wav_') => 'audio/wav',
            str_starts_with($format, 'ulaw_'), str_starts_with($format, 'alaw_') => 'audio/basic',
            str_starts_with($format, 'opus_') => 'audio/ogg',
            str_starts_with($format, 'aac_') => 'audio/aac',
            str_starts_with($format, 'flac_') => 'audio/flac',
            default => 'audio/mpeg',
        };
    }

    /**
     * ElevenLabs documents these fields as query parameters, not JSON body
     * members. Remove them by reference so providerOptions cannot duplicate
     * them in both places.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, scalar|null>
     */
    private function takeQueryOptions(array &$options): array
    {
        $query = [];

        foreach (['enable_logging', 'optimize_streaming_latency', 'output_format'] as $name) {
            $value = $options[$name] ?? null;
            unset($options[$name]);

            if (is_scalar($value) || $value === null) {
                $query[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
        }

        return $query;
    }
}
