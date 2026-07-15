<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Support;

use AiSdk\Exceptions\AiSdkException;
use Psr\Http\Message\ResponseInterface;

final class MultipartMixed
{
    /**
     * @return list<array{headers: array<string, string>, body: string}>
     */
    public static function parse(ResponseInterface $response): array
    {
        $contentType = $response->getHeaderLine('Content-Type');
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $matches) !== 1) {
            throw new AiSdkException('ElevenLabs detailed music response did not include a multipart boundary.');
        }

        $boundary = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');
        $parts = [];
        foreach (explode('--' . $boundary, (string) $response->getBody()) as $rawPart) {
            $rawPart = preg_replace('/^\r?\n|\r?\n$/', '', $rawPart) ?? $rawPart;
            if ($rawPart === '' || $rawPart === '--') {
                continue;
            }
            if (str_ends_with($rawPart, '--')) {
                $rawPart = substr($rawPart, 0, -2);
            }

            $split = preg_split("/\r?\n\r?\n/", $rawPart, 2);
            if (! is_array($split) || count($split) !== 2) {
                continue;
            }

            $headers = [];
            foreach (preg_split('/\r?\n/', $split[0]) ?: [] as $header) {
                if (! str_contains($header, ':')) {
                    continue;
                }
                [$name, $value] = explode(':', $header, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            $parts[] = [
                'headers' => $headers,
                'body' => preg_replace('/\r?\n$/', '', $split[1]) ?? $split[1],
            ];
        }

        return $parts;
    }
}
