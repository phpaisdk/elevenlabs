<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\ElevenLabs;
use AiSdk\ElevenLabs\Resources\Dialogue;
use AiSdk\ElevenLabs\Resources\Dubbing;
use AiSdk\ElevenLabs\Resources\ForcedAlignment;
use AiSdk\ElevenLabs\Resources\Music;
use AiSdk\ElevenLabs\Resources\SoundEffects;
use AiSdk\ElevenLabs\Resources\VoiceChanger;
use AiSdk\ElevenLabs\Resources\VoiceDesign;
use AiSdk\ElevenLabs\Resources\VoiceIsolator;
use AiSdk\ElevenLabs\Tests\Fakes\FakeHttpClient;
use AiSdk\Generate;
use AiSdk\Support\Sdk;

afterEach(function () {
    Generate::reset();
    ElevenLabs::reset();
});

function configureElevenLabsCreativeMediaWith(FakeHttpClient $client): void
{
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk($client, $factory, $factory));
    ElevenLabs::create(['apiKey' => 'xi-test']);
}

it('exposes provider-owned media resources on both the instance and facade', function () {
    configureElevenLabsCreativeMediaWith(new FakeHttpClient(200, '{}'));
    $provider = ElevenLabs::default();

    expect($provider->voiceChanger())->toBeInstanceOf(VoiceChanger::class)
        ->and($provider->voiceIsolator())->toBeInstanceOf(VoiceIsolator::class)
        ->and($provider->voiceDesign())->toBeInstanceOf(VoiceDesign::class)
        ->and($provider->music())->toBeInstanceOf(Music::class)
        ->and($provider->soundEffects())->toBeInstanceOf(SoundEffects::class)
        ->and($provider->dialogue())->toBeInstanceOf(Dialogue::class)
        ->and($provider->dubbing())->toBeInstanceOf(Dubbing::class)
        ->and($provider->forcedAlignment())->toBeInstanceOf(ForcedAlignment::class)
        ->and(ElevenLabs::voiceDesign())->toBeInstanceOf(VoiceDesign::class)
        ->and(ElevenLabs::dubbing())->toBeInstanceOf(Dubbing::class)
        ->and(ElevenLabs::forcedAlignment())->toBeInstanceOf(ForcedAlignment::class);
});

it('designs remixes and creates voices with documented body and query placement', function () {
    $previewPayload = json_encode([
        'previews' => [[
            'audio_base_64' => base64_encode('preview-audio'),
            'generated_voice_id' => 'generated-1',
            'media_type' => 'audio/mpeg',
            'duration_secs' => 1.25,
            'language' => 'en',
        ]],
        'text' => 'Preview text',
    ], JSON_THROW_ON_ERROR);
    $client = new FakeHttpClient(200, $previewPayload);
    configureElevenLabsCreativeMediaWith($client);

    $designed = ElevenLabs::voiceDesign()->design('A warm and expressive documentary narrator.', [
        'model_id' => 'eleven_ttv_v3',
        'auto_generate_text' => true,
        'output_format' => 'mp3_44100_192',
    ]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-voice/design?output_format=mp3_44100_192')
        ->and($client->sentBody())->toBe([
            'voice_description' => 'A warm and expressive documentary narrator.',
            'model_id' => 'eleven_ttv_v3',
            'auto_generate_text' => true,
        ])
        ->and($designed->previews)->toHaveCount(1)
        ->and($designed->previews[0]->generatedVoiceId)->toBe('generated-1')
        ->and($designed->previews[0]->audio->data)->toBe('preview-audio');

    ElevenLabs::voiceDesign()->remix('voice/id', 'Give it a brighter delivery.', [
        'text' => str_repeat('Example text. ', 10),
        'output_format' => 'pcm_44100',
    ]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-voice/voice%2Fid/remix?output_format=pcm_44100')
        ->and($client->sentBody()['voice_description'])->toBe('Give it a brighter delivery.')
        ->and($client->sentBody())->not->toHaveKey('output_format');

    $createdPayload = json_encode([
        'voice_id' => 'voice-created',
        'name' => 'Warm narrator',
        'category' => 'generated',
        'description' => 'A warm narrator',
    ], JSON_THROW_ON_ERROR);
    $createdClient = new FakeHttpClient(200, $createdPayload);
    configureElevenLabsCreativeMediaWith($createdClient);

    $created = ElevenLabs::voiceDesign()->create(
        'generated-1',
        'Warm narrator',
        'A warm and expressive documentary narrator.',
        ['labels' => ['language' => 'en']],
    );

    expect((string) $createdClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-voice')
        ->and($createdClient->sentBody())->toBe([
            'voice_name' => 'Warm narrator',
            'voice_description' => 'A warm and expressive documentary narrator.',
            'generated_voice_id' => 'generated-1',
            'labels' => ['language' => 'en'],
        ])
        ->and($created->id)->toBe('voice-created');
});

it('creates dubbing jobs and retrieves their status output and transcripts', function () {
    $client = new FakeHttpClient(200, json_encode([
        'dubbing_id' => 'dub-1',
        'expected_duration_sec' => 12.5,
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($client);

    $job = ElevenLabs::dubbing()->create(
        Content::video('video-bytes', 'video/mp4', 'clip.mp4'),
        'es',
        ['source_lang' => 'en', 'num_speakers' => 2, 'watermark' => false],
    );
    $body = (string) $client->lastRequest?->getBody();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/dubbing')
        ->and($body)->toContain('name="file"; filename="clip.mp4"')
        ->toContain('video-bytes')
        ->toContain('name="target_lang"')
        ->toContain('name="source_lang"')
        ->toContain('name="num_speakers"')
        ->and($job->id)->toBe('dub-1')
        ->and($job->expectedDuration)->toBe(12.5);

    $statusClient = new FakeHttpClient(200, json_encode([
        'dubbing_id' => 'dub-1',
        'name' => 'Spanish dub',
        'status' => 'dubbed',
        'source_language' => 'en',
        'target_languages' => ['es'],
        'created_at' => '2026-07-14T12:00:00Z',
        'editable' => false,
        'media_metadata' => ['content_type' => 'video/mp4', 'duration' => 12.5],
        'error' => null,
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($statusClient);
    $status = ElevenLabs::dubbing()->status('dub/1');

    expect((string) $statusClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/dubbing/dub%2F1')
        ->and($status->status)->toBe('dubbed')
        ->and($status->mediaType)->toBe('video/mp4')
        ->and($status->targetLanguages)->toBe(['es']);

    $outputClient = new FakeHttpClient(200, 'dubbed-video', 'video/mp4', [
        'Content-Disposition' => 'attachment; filename="dubbed.mp4"',
    ]);
    configureElevenLabsCreativeMediaWith($outputClient);
    $output = ElevenLabs::dubbing()->output('dub-1', 'es');

    expect((string) $outputClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/dubbing/dub-1/audio/es')
        ->and($output->data)->toBe('dubbed-video')
        ->and($output->mimeType)->toBe('video/mp4')
        ->and($output->filename)->toBe('dubbed.mp4');

    $transcriptClient = new FakeHttpClient(200, json_encode([
        'transcript_format' => 'srt',
        'srt' => "1\n00:00:00,000 --> 00:00:01,000\nHola",
        'webvtt' => null,
        'json' => null,
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($transcriptClient);
    $transcript = ElevenLabs::dubbing()->transcript('dub-1', 'es', 'srt');

    expect((string) $transcriptClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/dubbing/dub-1/transcripts/es/format/srt')
        ->and($transcript->format)->toBe('srt')
        ->and($transcript->content)->toContain('Hola');
});

it('returns typed forced alignment timing data', function () {
    $client = new FakeHttpClient(200, json_encode([
        'characters' => [['text' => 'H', 'start' => 0.0, 'end' => 0.1]],
        'words' => [['text' => 'Hello', 'start' => 0.0, 'end' => 0.5, 'loss' => 0.12]],
        'loss' => 0.2,
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($client);

    $result = ElevenLabs::forcedAlignment()->create(
        Content::audio('spoken-audio', 'audio/wav', 'speech.wav'),
        'Hello',
    );
    $body = (string) $client->lastRequest?->getBody();

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/forced-alignment')
        ->and($body)->toContain('name="file"; filename="speech.wav"')
        ->toContain('name="text"')
        ->toContain('Hello')
        ->and($result->characters[0]->text)->toBe('H')
        ->and($result->words[0]->loss)->toBe(0.12)
        ->and($result->loss)->toBe(0.2);
});

it('supports non-streaming music planning detailed output upload and direct transforms', function () {
    $planClient = new FakeHttpClient(200, json_encode([
        'chunks' => [['text' => '[Intro]', 'duration_ms' => 5000, 'positive_styles' => ['cinematic']]],
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($planClient);
    $plan = ElevenLabs::music()->plan('A short cinematic intro', [
        'model_id' => 'music_v2',
        'music_length_ms' => 5000,
    ]);

    expect((string) $planClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music/plan')
        ->and($planClient->sentBody())->toBe([
            'prompt' => 'A short cinematic intro',
            'model_id' => 'music_v2',
            'music_length_ms' => 5000,
        ])
        ->and($plan->value)->toHaveKey('chunks');

    $composeClient = new FakeHttpClient(200, 'planned-music', 'audio/mpeg');
    configureElevenLabsCreativeMediaWith($composeClient);
    ElevenLabs::music()->composeFromPlan($plan, ['model_id' => 'music_v2', 'output_format' => 'mp3_48000_192']);

    expect((string) $composeClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music?output_format=mp3_48000_192')
        ->and($composeClient->sentBody())->toBe([
            'composition_plan' => $plan->value,
            'model_id' => 'music_v2',
        ]);

    $mixed = "--aisdk-test\r\n"
        . "Content-Type: application/json\r\n\r\n"
        . '{"composition_plan":{"chunks":[]},"song_metadata":{"title":"Test"}}' . "\r\n"
        . "--aisdk-test\r\n"
        . "Content-Type: audio/mpeg\r\n"
        . "Content-Disposition: attachment; filename=track.mp3\r\n\r\n"
        . "detailed-audio\r\n"
        . "--aisdk-test--\r\n";
    $detailedClient = new FakeHttpClient(200, $mixed, 'multipart/mixed; boundary=aisdk-test', ['song-id' => 'song-1']);
    configureElevenLabsCreativeMediaWith($detailedClient);
    $detailed = ElevenLabs::music()->composeDetailed('Cinematic music', [
        'model_id' => 'music_v2',
        'with_timestamps' => true,
        'output_format' => 'mp3_48000_192',
    ]);

    expect((string) $detailedClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music/detailed?output_format=mp3_48000_192')
        ->and($detailedClient->sentBody())->toBe([
            'prompt' => 'Cinematic music',
            'model_id' => 'music_v2',
            'with_timestamps' => true,
        ])
        ->and($detailed->output->data)->toBe('detailed-audio')
        ->and($detailed->songId)->toBe('song-1')
        ->and($detailed->songMetadata['title'])->toBe('Test');

    $videoClient = new FakeHttpClient(200, 'video-music', 'audio/mpeg');
    configureElevenLabsCreativeMediaWith($videoClient);
    ElevenLabs::music()->videoToMusic([
        Content::video('video-one', 'video/mp4', 'one.mp4'),
        Content::video('video-two', 'video/mp4', 'two.mp4'),
    ], [
        'description' => 'Tense cinematic score',
        'tags' => ['cinematic', 'tense'],
        'model_id' => 'music_v2',
        'output_format' => 'mp3_44100_192',
    ]);
    $videoBody = (string) $videoClient->lastRequest?->getBody();

    expect((string) $videoClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music/video-to-music?output_format=mp3_44100_192')
        ->and(substr_count($videoBody, 'name="videos"'))->toBe(2)
        ->and($videoBody)->toContain('name="description"')
        ->toContain('name="tags"')
        ->toContain('cinematic')
        ->toContain('tense');

    $stemsClient = new FakeHttpClient(200, 'zip-bytes', 'application/zip', [
        'Content-Disposition' => 'attachment; filename=stems.zip',
    ]);
    configureElevenLabsCreativeMediaWith($stemsClient);
    $stems = ElevenLabs::music()->separateStems(
        Content::audio('song-audio', 'audio/mpeg', 'song.mp3'),
        ['stem_variation_id' => 'six_stems_v1', 'sign_with_c2pa' => true, 'output_format' => 'mp3_44100_192'],
    );

    expect((string) $stemsClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music/stem-separation?output_format=mp3_44100_192')
        ->and((string) $stemsClient->lastRequest?->getBody())->toContain('name="file"; filename="song.mp3"')
        ->toContain('name="stem_variation_id"')
        ->toContain('name="sign_with_c2pa"')
        ->and($stems->data)->toBe('zip-bytes')
        ->and($stems->filename)->toBe('stems.zip');

    $uploadClient = new FakeHttpClient(200, json_encode([
        'song_id' => 'uploaded-song',
        'composition_plan' => ['chunks' => []],
        'words_timestamps' => [['word' => 'hello', 'start' => 0.0, 'end' => 0.5]],
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($uploadClient);
    $upload = ElevenLabs::music()->upload(
        Content::audio('song-audio', 'audio/mpeg', 'song.mp3'),
        ['extract_composition_plan' => 'music_v2', 'with_timestamps' => true],
    );

    expect((string) $uploadClient->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/music/upload')
        ->and((string) $uploadClient->lastRequest?->getBody())->toContain('name="extract_composition_plan"')
        ->toContain('music_v2')
        ->toContain('name="with_timestamps"')
        ->and($upload->songId)->toBe('uploaded-song')
        ->and($upload->wordTimestamps)->toHaveCount(1);
});

it('generates dialogue with typed timestamps and voice segments', function () {
    $client = new FakeHttpClient(200, json_encode([
        'audio_base64' => base64_encode('dialogue-audio'),
        'alignment' => [
            'characters' => ['H', 'i'],
            'character_start_times_seconds' => [0.0, 0.1],
            'character_end_times_seconds' => [0.1, 0.2],
        ],
        'normalized_alignment' => null,
        'voice_segments' => [[
            'voice_id' => 'voice-a',
            'start_time_seconds' => 0.0,
            'end_time_seconds' => 0.2,
            'character_start_index' => 0,
            'character_end_index' => 2,
            'dialogue_input_index' => 0,
        ]],
    ], JSON_THROW_ON_ERROR));
    configureElevenLabsCreativeMediaWith($client);

    $result = ElevenLabs::dialogue()->withTimestamps([
        ['text' => 'Hi', 'voice_id' => 'voice-a'],
    ], [
        'model_id' => 'eleven_v3',
        'output_format' => 'wav_44100',
        'enable_logging' => false,
    ]);

    expect((string) $client->lastRequest?->getUri())->toBe('https://api.elevenlabs.io/v1/text-to-dialogue/with-timestamps?output_format=wav_44100&enable_logging=false')
        ->and($client->sentBody())->toBe([
            'inputs' => [['text' => 'Hi', 'voice_id' => 'voice-a']],
            'model_id' => 'eleven_v3',
        ])
        ->and($result->output->data)->toBe('dialogue-audio')
        ->and($result->output->mimeType)->toBe('audio/wav')
        ->and($result->alignment?->characters)->toBe(['H', 'i'])
        ->and($result->voiceSegments[0]->voiceId)->toBe('voice-a');
});
