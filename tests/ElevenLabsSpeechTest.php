<?php

declare(strict_types=1);

use AiSdk\ElevenLabs;
use AiSdk\ElevenLabs\Tests\Fakes\FakeHttpClient;
use AiSdk\Generate;
use AiSdk\Support\Sdk;

afterEach(function () {
    Generate::reset();
    ElevenLabs::reset();
});

function configureElevenLabsWith(FakeHttpClient $client): void
{
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
}

it('generates speech with an ElevenLabs model and voice id', function () {
    $client = new FakeHttpClient(200, 'audio-bytes', 'audio/mpeg');
    configureElevenLabsWith($client);
    ElevenLabs::create(['apiKey' => 'xi-test']);

    $result = Generate::speech('Hello there')
        ->model(ElevenLabs::model('eleven_flash_v2_5'))
        ->voice('voice-id')
        ->providerOptions('elevenlabs', [
            'seed' => 42,
            'enable_logging' => false,
            'optimize_streaming_latency' => 2,
            'output_format' => 'wav_44100',
        ])
        ->run();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-speech/voice-id?enable_logging=false&optimize_streaming_latency=2&output_format=wav_44100')
        ->and($client->sentBody())->toBe(['text' => 'Hello there', 'model_id' => 'eleven_flash_v2_5', 'seed' => 42])
        ->and($client->lastRequest?->getHeaderLine('xi-api-key'))->toBe('xi-test')
        ->and($client->lastRequest?->getHeaderLine('Accept'))->toBe('audio/wav')
        ->and($result->output->data)->toBe('audio-bytes');
});

it('maps the portable wav format to an actual wav container', function () {
    $client = new FakeHttpClient(200, 'wave-bytes', 'audio/wav');
    configureElevenLabsWith($client);
    ElevenLabs::create(['apiKey' => 'xi-test']);

    Generate::speech('Hello there')
        ->model(ElevenLabs::model('eleven_flash_v2_5'))
        ->voice('voice-id')
        ->format('wav')
        ->run();

    expect((string) $client->lastRequest?->getUri())->toEndWith('?output_format=wav_44100');
});
