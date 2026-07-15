<?php

declare(strict_types=1);

use AiSdk\ElevenLabs;
use AiSdk\ElevenLabs\Tests\Fakes\FakeHttpClient;
use AiSdk\ElevenLabs\Tests\Fakes\FakeTransport;
use AiSdk\Generate;
use AiSdk\Live;
use AiSdk\Live\LiveClosed;
use AiSdk\Live\LiveError;
use AiSdk\Live\ProviderEvent;
use AiSdk\Live\TranscriptCompleted;
use AiSdk\Live\TranscriptSource;
use AiSdk\Live\TranscriptUpdate;
use AiSdk\Live\TransportFrame;
use AiSdk\Live\WebSocketEndpoint;
use AiSdk\Support\Sdk;

afterEach(function () {
    Generate::reset();
    ElevenLabs::reset();
});

it('streams audio to Scribe and normalizes transcript events', function () {
    ElevenLabs::create(['apiKey' => 'xi-test']);

    $transport = new FakeTransport();
    $transport->connection->enqueue(
        TransportFrame::text('{"message_type":"session_started","session_id":"session-1"}'),
        TransportFrame::text('{"message_type":"partial_transcript","text":"Hello wor"}'),
        TransportFrame::text('{"message_type":"committed_transcript","text":"Hello world"}'),
        TransportFrame::text('{"message_type":"committed_transcript_with_timestamps","text":"Hello world","words":[]}'),
        TransportFrame::binary('provider-binary'),
    );

    $session = Live::transcribe()
        ->model(ElevenLabs::model('scribe_v2_realtime'))
        ->language('en')
        ->audioFormat('pcm16')
        ->providerOptions('elevenlabs', [
            'commit_strategy' => 'manual',
            'include_timestamps' => true,
            'keyterms' => ['PHP', 'AI SDK'],
        ])
        ->connect($transport);

    $session->sendAudio("\x00\x01");
    $session->commitAudio();
    $events = iterator_to_array($session->events());

    expect($transport->endpoint)->toBeInstanceOf(WebSocketEndpoint::class)
        ->and($transport->endpoint?->headers['xi-api-key'] ?? null)->toBe('xi-test')
        ->and($transport->endpoint?->url)->toContain('/v1/speech-to-text/realtime?')
        ->toContain('model_id=scribe_v2_realtime')
        ->toContain('language_code=en')
        ->toContain('audio_format=pcm_16000')
        ->toContain('keyterms=PHP')
        ->toContain('keyterms=AI%20SDK')
        ->and(json_decode($transport->connection->sent[0]->payload, true))->toBe([
            'message_type' => 'input_audio_chunk',
            'audio_base_64' => 'AAE=',
        ])
        ->and(json_decode($transport->connection->sent[1]->payload, true))->toBe([
            'message_type' => 'input_audio_chunk',
            'audio_base_64' => '',
            'commit' => true,
        ]);

    $partial = $events[1] ?? null;
    $completed = $events[2] ?? null;
    $committed = $events[3] ?? null;
    $binary = $events[4] ?? null;

    if (! $partial instanceof TranscriptUpdate || ! $completed instanceof TranscriptCompleted || ! $committed instanceof ProviderEvent || ! $binary instanceof ProviderEvent) {
        throw new LogicException('Expected normalized Scribe transcript events.');
    }

    expect($partial)->toBeInstanceOf(TranscriptUpdate::class)
        ->and($partial->text)->toBe('Hello wor')
        ->and($partial->source)->toBe(TranscriptSource::Input)
        ->and($completed)->toBeInstanceOf(TranscriptCompleted::class)
        ->and($completed->text)->toBe('Hello world')
        ->and($completed->source)->toBe(TranscriptSource::Input)
        ->and($committed)->toBeInstanceOf(ProviderEvent::class)
        ->and($committed->providerType)->toBe('committed_transcript_with_timestamps')
        ->and($binary)->toBeInstanceOf(ProviderEvent::class)
        ->and($binary->providerType)->toBe('transport.binary')
        ->and($events[5])->toBeInstanceOf(LiveClosed::class);
});

it('authenticates realtime transcription with a single-use token when supplied', function () {
    ElevenLabs::create(['apiKey' => 'xi-must-not-leak']);
    $transport = new FakeTransport();

    Live::transcribe()
        ->model(ElevenLabs::model('scribe_v2_realtime'))
        ->providerOptions('elevenlabs', [
            'query' => [
                'token' => 'sutkn_browser',
                'include_language_detection' => true,
            ],
        ])
        ->connect($transport);

    $endpoint = $transport->endpoint;
    if (! $endpoint instanceof WebSocketEndpoint) {
        throw new LogicException('Expected a realtime WebSocket endpoint.');
    }

    expect($endpoint)->toBeInstanceOf(WebSocketEndpoint::class)
        ->and($endpoint->url)->toContain('token=sutkn_browser')
        ->toContain('include_language_detection=true');
    expect(array_key_exists('xi-api-key', $endpoint->headers))->toBeFalse();
});

it('normalizes documented Scribe errors', function () {
    ElevenLabs::create(['apiKey' => 'xi-test']);
    $transport = new FakeTransport();
    $transport->connection->enqueue(
        TransportFrame::text('{"message_type":"rate_limited","message":"Slow down"}'),
    );

    $session = Live::transcribe()
        ->model(ElevenLabs::model('scribe_v2_realtime'))
        ->connect($transport);
    $events = iterator_to_array($session->events());

    $error = $events[0] ?? null;
    if (! $error instanceof LiveError) {
        throw new LogicException('Expected a normalized Scribe error.');
    }

    expect($error)->toBeInstanceOf(LiveError::class)
        ->and($error->code)->toBe('rate_limited')
        ->and($error->message)->toBe('Slow down')
        ->and($events[1])->toBeInstanceOf(LiveClosed::class);
});

it('creates a single-use Scribe token for browser transcription', function () {
    $client = new FakeHttpClient(200, '{"token":"sutkn_test"}');
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);

    $before = time();
    $secret = Live::transcribe()
        ->model(ElevenLabs::model('scribe_v2_realtime'))
        ->clientSecret();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/single-use-token/realtime_scribe')
        ->and($client->lastRequest?->getMethod())->toBe('POST')
        ->and($secret->value)->toBe('sutkn_test')
        ->and($secret->expiresAt)->toBeGreaterThanOrEqual($before + 900);
});
