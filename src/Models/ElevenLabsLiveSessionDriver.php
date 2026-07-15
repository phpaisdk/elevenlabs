<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Models;

use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\Exceptions\UnsupportedLiveActionException;
use AiSdk\Live\Contracts\LiveSessionDriverInterface;
use AiSdk\Live\Contracts\TransportConnectionInterface;
use AiSdk\Live\LiveClosed;
use AiSdk\Live\LiveError;
use AiSdk\Live\LiveEvent;
use AiSdk\Live\LiveOperation;
use AiSdk\Live\ProviderEvent;
use AiSdk\Live\TranscriptCompleted;
use AiSdk\Live\TranscriptSource;
use AiSdk\Live\TranscriptUpdate;
use AiSdk\Live\TransportFrame;
use AiSdk\Live\TransportFrameType;
use AiSdk\Support\Json;

final class ElevenLabsLiveSessionDriver implements LiveSessionDriverInterface
{
    public function __construct(
        private readonly TransportConnectionInterface $connection,
        private readonly LiveOperation $operation,
    ) {}

    public function sendAudio(string $bytes): void
    {
        $this->send([
            'message_type' => 'input_audio_chunk',
            'audio_base_64' => base64_encode($bytes),
        ]);
    }

    public function sendText(string $text): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function commitAudio(): void
    {
        $this->send([
            'message_type' => 'input_audio_chunk',
            'audio_base_64' => '',
            'commit' => true,
        ]);
    }

    public function clearAudio(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function requestResponse(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function cancelResponse(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function sendToolResult(string $callId, mixed $result): void
    {
        $this->unsupported(__FUNCTION__);
    }

    /** @return iterable<LiveEvent> */
    public function events(): iterable
    {
        while (($frame = $this->connection->receive()) !== null) {
            if ($frame->type !== TransportFrameType::Text) {
                yield new ProviderEvent('transport.binary', ['bytes' => base64_encode($frame->payload)]);

                continue;
            }

            $payload = Json::decode($frame->payload, $this->provider() . ' Live event');
            $type = is_string($payload['message_type'] ?? null) ? $payload['message_type'] : 'unknown';

            yield match ($type) {
                'partial_transcript' => new TranscriptUpdate(
                    (string) ($payload['text'] ?? ''),
                    source: TranscriptSource::Input,
                ),
                'committed_transcript' => new TranscriptCompleted(
                    (string) ($payload['text'] ?? ''),
                    source: TranscriptSource::Input,
                ),
                'auth_error', 'quota_exceeded', 'transcriber_error', 'input_error', 'error',
                'commit_throttled', 'unaccepted_terms', 'rate_limited', 'queue_overflow',
                'resource_exhausted', 'session_time_limit_exceeded', 'chunk_size_exceeded',
                'insufficient_audio_activity' => new LiveError(
                    (string) ($payload['message'] ?? $payload['error'] ?? $type),
                    $type,
                    $payload,
                ),
                default => new ProviderEvent($type, $payload),
            };
        }

        yield new LiveClosed();
    }

    public function close(): void
    {
        if (! $this->connection->isClosed()) {
            $this->connection->close();
        }
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload): void
    {
        $this->connection->send(TransportFrame::text(Json::encode($payload)));
    }

    private function unsupported(string $action): never
    {
        throw UnsupportedLiveActionException::for($this->provider(), $this->operation, $action);
    }

    private function provider(): string
    {
        return ElevenLabsOptions::PROVIDER_NAME;
    }
}
