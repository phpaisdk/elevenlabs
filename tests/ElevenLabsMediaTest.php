<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Contracts\LiveProviderInterface;
use AiSdk\ElevenLabs;
use AiSdk\ElevenLabs\Tests\Fakes\FakeHttpClient;
use AiSdk\Generate;
use AiSdk\Support\Sdk;

afterEach(function () {
    Generate::reset();
    ElevenLabs::reset();
});

function configureElevenLabsMediaWith(FakeHttpClient $client): void
{
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);
}

it('sends music prompts to the documented music endpoint', function () {
    $client = new FakeHttpClient(200, 'music-bytes', 'audio/mpeg');
    configureElevenLabsMediaWith($client);

    $result = ElevenLabs::music()->compose('Slow piano', ['music_length_ms' => 30_000]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music?output_format=auto')
        ->and($client->sentBody())->toBe(['prompt' => 'Slow piano', 'music_length_ms' => 30_000])
        ->and($result->output->data)->toBe('music-bytes');
});

it('keeps voice changer query options out of the multipart form', function () {
    $client = new FakeHttpClient(200, 'changed-voice', 'audio/mpeg');
    configureElevenLabsMediaWith($client);

    ElevenLabs::voiceChanger()->convert(
        'voice/id',
        Content::audio('raw-audio', 'audio/wav', 'source.wav'),
        [
            'model_id' => 'eleven_multilingual_sts_v2',
            'voice_settings' => ['stability' => 0.6],
            'remove_background_noise' => true,
            'enable_logging' => false,
            'optimize_streaming_latency' => 3,
            'output_format' => 'wav_44100',
        ],
    );

    $body = (string) $client->lastRequest?->getBody();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/speech-to-speech/voice%2Fid?enable_logging=false&optimize_streaming_latency=3&output_format=wav_44100')
        ->and($body)->toContain('name="audio"; filename="source.wav"')
        ->toContain('raw-audio')
        ->toContain('name="model_id"')
        ->toContain('eleven_multilingual_sts_v2')
        ->toContain('{"stability":0.6}')
        ->toContain('name="remove_background_noise"')
        ->not->toContain('name="enable_logging"')
        ->not->toContain('name="optimize_streaming_latency"')
        ->not->toContain('name="output_format"');
});

it('sends voice isolation fields as multipart form values', function () {
    $client = new FakeHttpClient(200, 'isolated-voice', 'audio/mpeg');
    configureElevenLabsMediaWith($client);

    ElevenLabs::voiceIsolator()->isolate(
        Content::audio('raw-audio', 'audio/wav', 'source.wav'),
        ['file_format' => 'pcm_s16le_16', 'preview_b64' => 'preview'],
    );

    $body = (string) $client->lastRequest?->getBody();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/audio-isolation')
        ->and($body)->toContain('name="audio"; filename="source.wav"')
        ->toContain('name="file_format"')
        ->toContain('pcm_s16le_16')
        ->toContain('name="preview_b64"');
});

it('sends sound effects and dialogue with documented JSON and query fields', function () {
    $client = new FakeHttpClient(200, 'generated-audio', 'audio/mpeg');
    configureElevenLabsMediaWith($client);

    ElevenLabs::soundEffects()->generate('A wooden door creaking', [
        'loop' => true,
        'duration_seconds' => 4.5,
        'output_format' => 'wav_48000',
    ]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/sound-generation?output_format=wav_48000')
        ->and($client->sentBody())->toBe([
            'text' => 'A wooden door creaking',
            'loop' => true,
            'duration_seconds' => 4.5,
        ]);

    ElevenLabs::dialogue()->create([
        ['text' => 'Hello', 'voice_id' => 'voice-a'],
        ['text' => 'Hi', 'voice_id' => 'voice-b'],
    ], [
        'model_id' => 'eleven_v3',
        'enable_logging' => false,
        'output_format' => 'wav_44100',
    ]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-dialogue?output_format=wav_44100&enable_logging=false')
        ->and($client->sentBody())->toBe([
            'inputs' => [
                ['text' => 'Hello', 'voice_id' => 'voice-a'],
                ['text' => 'Hi', 'voice_id' => 'voice-b'],
            ],
            'model_id' => 'eleven_v3',
        ]);
});

it('exposes only the unified model selector on provider instances', function () {
    $client = new FakeHttpClient(200, '{}');
    configureElevenLabsMediaWith($client);
    $provider = ElevenLabs::default();

    expect($provider)->toBeInstanceOf(LiveProviderInterface::class)
        ->and($provider->model('scribe_v2')->modelId())->toBe('scribe_v2')
        ->and(is_callable([$provider, 'speechModel']))->toBeFalse()
        ->and(is_callable([$provider, 'transcriptionModel']))->toBeFalse()
        ->and(is_callable([$provider, 'liveModel']))->toBeFalse();
});
