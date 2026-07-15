<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\UnsupportedTransportException;
use AiSdk\Generate;
use AiSdk\Live\ClientSecret;
use AiSdk\Live\Contracts\LiveClientSecretModelInterface;
use AiSdk\Live\Contracts\LiveSessionDriverInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Live\LiveOperation;
use AiSdk\Live\LiveRequest;
use AiSdk\Live\WebSocketEndpoint;
use AiSdk\Support\Json;
use AiSdk\Utils\Support\Url;

final class ElevenLabsLiveModel extends BaseModel implements LiveClientSecretModelInterface
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

    public function createLiveSession(LiveRequest $request, TransportInterface $transport): LiveSessionDriverInterface
    {
        $this->ensureTranscription($request);

        $providerOptions = $request->providerOptions[$this->provider()] ?? [];
        $providerQuery = is_array($providerOptions['query'] ?? null) ? $providerOptions['query'] : [];
        $rawQuery = is_array($providerOptions['raw'] ?? null) ? $providerOptions['raw'] : [];
        $directQuery = array_diff_key($providerOptions, array_flip(['headers', 'query', 'raw']));
        $query = array_replace($directQuery, $providerQuery, $rawQuery, ['model_id' => $this->modelId]);

        if (isset($request->options['language'])) {
            $query['language_code'] = $request->options['language'];
        }

        if (isset($request->options['input_audio_format'])) {
            $query['audio_format'] = $this->audioFormat($request->options['input_audio_format']);
        }

        $query = array_intersect_key($query, array_flip([
            'model_id',
            'token',
            'include_timestamps',
            'include_language_detection',
            'audio_format',
            'language_code',
            'commit_strategy',
            'keyterms',
            'no_verbatim',
            'vad_silence_threshold_secs',
            'vad_threshold',
            'min_speech_duration_ms',
            'min_silence_duration_ms',
            'filter_background_audio',
            'enable_logging',
        ]));

        $headers = $this->options->authHeaders();
        if (is_string($query['token'] ?? null) && $query['token'] !== '') {
            unset($headers['xi-api-key']);
        }
        if (is_array($providerOptions['headers'] ?? null)) {
            foreach ($providerOptions['headers'] as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }

        $baseUrl = preg_replace('/^http(s?):\/\//', 'ws$1://', $this->options->baseUrl) ?? $this->options->baseUrl;
        $endpoint = new WebSocketEndpoint(
            Url::joinPath($baseUrl, '/speech-to-text/realtime') . '?' . $this->queryString($query),
            $headers,
        );

        if (! $transport->supports($endpoint)) {
            throw UnsupportedTransportException::for($endpoint);
        }

        return new ElevenLabsLiveSessionDriver($transport->connect($endpoint), $request->operation);
    }

    public function clientSecret(LiveRequest $request): ClientSecret
    {
        $this->ensureTranscription($request);

        $sdk = $this->options->sdk ?? Generate::sdk();
        $httpRequest = $sdk->requestFactory
            ->createRequest('POST', Url::joinPath($this->options->baseUrl, '/single-use-token/realtime_scribe'))
            ->withHeader('Accept', 'application/json');

        foreach ($this->options->authHeaders() as $name => $value) {
            $httpRequest = $httpRequest->withHeader($name, $value);
        }

        $response = $this->runner($sdk)->sendRequest($httpRequest, $this->provider());
        $payload = Json::decode((string) $response->getBody(), $this->provider() . ' client secret');
        $token = $payload['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new InvalidArgumentException('ElevenLabs did not return a realtime transcription token.');
        }

        return new ClientSecret($token, time() + 900, raw: $payload);
    }

    private function ensureTranscription(LiveRequest $request): void
    {
        if ($request->operation !== LiveOperation::Transcribe) {
            throw new InvalidArgumentException('ElevenLabs Live supports realtime transcription through Live::transcribe().');
        }
    }

    private function audioFormat(mixed $format): mixed
    {
        if (! is_string($format)) {
            return $format;
        }

        return match (strtolower($format)) {
            'pcm', 'pcm16', 'audio/pcm' => 'pcm_16000',
            'pcmu', 'mulaw', 'g711_ulaw', 'audio/pcmu' => 'ulaw_8000',
            default => $format,
        };
    }

    /** @param array<string, mixed> $query */
    private function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! is_scalar($item)) {
                    continue;
                }

                $encoded = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode($encoded);
            }
        }

        return implode('&', $pairs);
    }
}
