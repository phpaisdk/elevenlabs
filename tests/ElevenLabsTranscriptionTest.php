<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\ElevenLabs;
use AiSdk\ElevenLabs\Tests\Fakes\FakeHttpClient;
use AiSdk\Generate;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    ElevenLabs::reset();
});

it('sends ElevenLabs transcription options in their documented locations', function () {
    $client = new FakeHttpClient(200, json_encode([
        'text' => 'Hello world',
        'language_code' => 'en',
    ], JSON_THROW_ON_ERROR));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);

    $result = Generate::transcription(Content::audio('audio-bytes', 'audio/wav', 'meeting.wav'))
        ->model(ElevenLabs::model('scribe_v2'))
        ->providerOptions('elevenlabs', [
            'language_code' => 'en',
            'diarize' => true,
            'keyterms' => ['PHP', 'AI SDK'],
            'enable_logging' => false,
        ])
        ->run();

    $body = (string) $client->lastRequest?->getBody();
    $filePosition = strpos($body, 'name="file"');
    $keytermPosition = strrpos($body, 'name="keyterms"');

    if ($filePosition === false || $keytermPosition === false) {
        throw new LogicException('Expected file and keyterms multipart fields.');
    }

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/speech-to-text?enable_logging=false')
        ->and($body)->toContain('name="model_id"')
        ->toContain('scribe_v2')
        ->toContain('name="language_code"')
        ->toContain('name="diarize"')
        ->toContain('name="keyterms"')
        ->and($filePosition)->toBeGreaterThan($keytermPosition)
        ->and($result->output->text)->toBe('Hello world')
        ->and($result->output->language)->toBe('en');
    expect(str_contains($body, 'name="enable_logging"'))->toBeFalse();
});

it('uses source_url for remote media and normalizes separate multichannel transcripts', function () {
    $client = new FakeHttpClient(200, json_encode([
        'transcripts' => [
            [
                'channel_index' => 0,
                'language_code' => 'en',
                'text' => 'Hello.',
                'words' => [['text' => 'Hello.', 'start' => 0.0, 'end' => 0.5]],
            ],
            [
                'channel_index' => 1,
                'language_code' => 'en',
                'text' => 'Hi.',
                'words' => [['text' => 'Hi.', 'start' => 0.2, 'end' => 0.6]],
            ],
        ],
    ], JSON_THROW_ON_ERROR));
    $factory = new Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);

    $result = Generate::transcription(Content::audio('https://cdn.example.com/call.mp3'))
        ->model(ElevenLabs::model('scribe_v2'))
        ->providerOptions('elevenlabs', ['use_multi_channel' => true])
        ->run();
    $body = (string) $client->lastRequest?->getBody();

    expect($body)->toContain('name="source_url"')
        ->toContain('https://cdn.example.com/call.mp3')
        ->and($result->output->text)->toBe("Hello.\nHi.")
        ->and($result->output->language)->toBe('en')
        ->and($result->output->segments)->toHaveCount(2)
        ->and($result->output->segments[0]->speaker)->toBe('0')
        ->and($result->output->segments[1]->speaker)->toBe('1');
    expect(str_contains($body, 'name="file"'))->toBeFalse();
});

it('rejects an ElevenLabs transcription answer that has no transcript', function (string $body) {
    $factory = new Psr17Factory();
    Generate::configure(new Sdk(new FakeHttpClient(200, $body), $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);

    expect(fn() => Generate::transcription(Content::audio('audio-bytes', 'audio/wav', 'a.wav'))->model(ElevenLabs::model('scribe_v2'))->run())
        ->toThrow(AiSdk\Exceptions\InvalidResponseException::class);
})->with(['{}', '[1,2]', '{"error":{"message":"quota"}}']);
