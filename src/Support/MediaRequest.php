<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Support;

use AiSdk\Content;
use AiSdk\ElevenLabs\Data\BinaryData;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Results\AudioData;
use AiSdk\Results\AudioResult;
use AiSdk\Support\Json;
use AiSdk\Support\Usage;
use AiSdk\Utils\Http\HttpRunner;
use AiSdk\Utils\Support\MultipartFormData;
use AiSdk\Utils\Support\Url;
use Psr\Http\Message\ResponseInterface;

/** Shared HTTP sender for ElevenLabs provider-owned media resources. */
final readonly class MediaRequest
{
    public function __construct(private ElevenLabsOptions $options) {}

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, scalar|null>  $query
     */
    public function audio(string $path, Content $audio, array $fields = [], array $query = []): AudioResult
    {
        $parts = [$this->contentPart('audio', $audio, 'audio.wav', 'audio/wav')];
        foreach ($fields as $name => $value) {
            $parts[] = ['name' => $name, 'value' => $value];
        }
        $response = $this->multipart($path, $parts, $query);

        return new AudioResult(new AudioData((string) $response->getBody(), $response->getHeaderLine('Content-Type') ?: 'audio/mpeg'), new Usage(), [], [ElevenLabsOptions::PROVIDER_NAME => []]);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, scalar> $query
     */
    public function jsonAudio(string $path, array $body, array $query = []): AudioResult
    {
        $url = $this->url($path, $query);
        $response = HttpRunner::fromSdk($this->options->sdk ?? Generate::sdk())
            ->postRaw($url, $body, $this->options->authHeaders(), ElevenLabsOptions::PROVIDER_NAME);

        return new AudioResult(new AudioData((string) $response->getBody(), $response->getHeaderLine('Content-Type') ?: 'audio/mpeg'), new Usage(), [], [ElevenLabsOptions::PROVIDER_NAME => []]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function json(string $path, array $body, array $query = []): array
    {
        return Json::decode((string) $this->jsonResponse($path, $body, $query)->getBody(), ElevenLabsOptions::PROVIDER_NAME);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar|null>  $query
     * @param  array<string, string>  $headers
     */
    public function jsonResponse(string $path, array $body, array $query = [], array $headers = []): ResponseInterface
    {
        return $this->runner()->postRaw(
            $this->url($path, $query),
            $body,
            array_replace($this->options->authHeaders(), $headers),
            ElevenLabsOptions::PROVIDER_NAME,
        );
    }

    /**
     * @param  array<string, scalar|list<scalar>|null>  $query
     * @return array<string, mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        return $this->runner()->getJson(
            $this->url($path, $query),
            $this->options->authHeaders(),
            ElevenLabsOptions::PROVIDER_NAME,
        );
    }

    /**
     * @param  array<int, array{name: string, value: mixed, filename?: string, contentType?: string}>  $parts
     * @param  array<string, scalar|null>  $query
     */
    public function multipart(string $path, array $parts, array $query = []): ResponseInterface
    {
        $multipart = MultipartFormData::encode($parts);
        $sdk = $this->options->sdk ?? Generate::sdk();
        $request = $sdk->requestFactory->createRequest('POST', $this->url($path, $query))
            ->withBody($sdk->streamFactory->createStream($multipart['body']))
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $multipart['boundary']);
        foreach ($this->options->authHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return HttpRunner::fromSdk($sdk)->sendRequest($request, ElevenLabsOptions::PROVIDER_NAME);
    }

    /**
     * @param  array<int, array{name: string, value: mixed, filename?: string, contentType?: string}>  $parts
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function multipartJson(string $path, array $parts, array $query = []): array
    {
        return Json::decode((string) $this->multipart($path, $parts, $query)->getBody(), ElevenLabsOptions::PROVIDER_NAME);
    }

    /** @param array<string, scalar|list<scalar>|null> $query */
    public function getRaw(string $path, array $query = []): ResponseInterface
    {
        $sdk = $this->options->sdk ?? Generate::sdk();
        $request = $sdk->requestFactory->createRequest('GET', $this->url($path, $query));
        foreach ($this->options->authHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return HttpRunner::fromSdk($sdk)->sendRequest($request, ElevenLabsOptions::PROVIDER_NAME);
    }

    public function binary(ResponseInterface $response): BinaryData
    {
        $disposition = $response->getHeaderLine('Content-Disposition');
        $filename = preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) === 1
            ? $matches[1]
            : null;

        return new BinaryData(
            data: (string) $response->getBody(),
            mimeType: $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
            filename: $filename,
        );
    }

    /** @return array{name: string, value: mixed, filename: string, contentType: string} */
    public function contentPart(string $name, Content $content, string $fallbackFilename, string $fallbackContentType): array
    {
        $data = $content->data();
        if ($data === null) {
            throw new InvalidArgumentException("Multipart field [{$name}] requires inline or file-backed content.");
        }

        return [
            'name' => $name,
            'value' => $data,
            'filename' => $content->filename() ?: $fallbackFilename,
            'contentType' => $content->mimeType() ?: $fallbackContentType,
        ];
    }

    private function runner(): HttpRunner
    {
        return HttpRunner::fromSdk($this->options->sdk ?? Generate::sdk());
    }

    /** @param array<string, scalar|list<scalar>|null> $query */
    private function url(string $path, array $query = []): string
    {
        $url = Url::joinPath($this->options->baseUrl, $path);

        return $query === []
            ? $url
            : $url . '?' . http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
    }
}
