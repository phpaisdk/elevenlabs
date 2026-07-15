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
        ])
        ->and($events[1])->toBeInstanceOf(TranscriptUpdate::class)
        ->and($events[1]->text)->toBe('Hello wor')
        ->and($events[1]->source)->toBe(TranscriptSource::Input)
        ->and($events[2])->toBeInstanceOf(TranscriptCompleted::class)
        ->and($events[2]->text)->toBe('Hello world')
        ->and($events[2]->source)->toBe(TranscriptSource::Input)
        ->and($events[3])->toBeInstanceOf(ProviderEvent::class)
        ->and($events[3]->providerType)->toBe('committed_transcript_with_timestamps')
        ->and($events[4])->toBeInstanceOf(ProviderEvent::class)
        ->and($events[4]->providerType)->toBe('transport.binary')
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

    expect($transport->endpoint)->toBeInstanceOf(WebSocketEndpoint::class)
        ->and($transport->endpoint?->headers)->not->toHaveKey('xi-api-key')
        ->and($transport->endpoint?->url)->toContain('token=sutkn_browser')
        ->toContain('include_language_detection=true');
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

    expect($events[0])->toBeInstanceOf(LiveError::class)
        ->and($events[0]->code)->toBe('rate_limited')
        ->and($events[0]->message)->toBe('Slow down')
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
