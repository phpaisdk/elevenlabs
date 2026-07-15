# aisdk/elevenlabs

<a href="https://github.com/phpaisdk/elevenlabs/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/phpaisdk/elevenlabs/tests.yml?branch=main&label=Tests"></a>
<a href="https://packagist.org/packages/aisdk/elevenlabs"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/aisdk/elevenlabs"></a>
<a href="https://packagist.org/packages/aisdk/elevenlabs"><img alt="Latest Version" src="https://img.shields.io/packagist/v/aisdk/elevenlabs"></a>
<a href="https://packagist.org/packages/aisdk/elevenlabs"><img alt="License" src="https://img.shields.io/packagist/l/aisdk/elevenlabs"></a>
<a href="https://whyphp.dev"><img src="https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat-square&labelColor=18181b" alt="Why PHP in 2026"></a>

------

Official ElevenLabs generative-media provider for the PHP AI SDK.

## Installation

```bash
composer require aisdk/elevenlabs
```

## Configuration

Set `ELEVENLABS_API_KEY`, or configure the provider directly:

```php
use AiSdk\ElevenLabs;

ElevenLabs::create([
    'apiKey' => 'xi-...',
    'baseUrl' => 'https://api.elevenlabs.io/v1',
    'headers' => ['X-Custom-Header' => 'value'],
]);
```

`ELEVENLABS_BASE_URL` overrides the default base URL when no `baseUrl` option is supplied.

## Supported SDK Capabilities

| Capability | Support |
|---|---|
| Speech generation | Native |
| Transcription | Native |
| Realtime transcription | Native through `Live::transcribe()` |
| Text, image, embeddings, video | Not provided by this package |

Model IDs are opaque. Use the ElevenLabs model identifier appropriate for the operation, such as `eleven_flash_v2_5`, `eleven_v3`, or `scribe_v2`.

Provider-owned extensions cover the direct ElevenLabs creative-media APIs that do not belong in core:

| Extension | Surface |
|---|---|
| Voice changer and isolation | Audio-to-audio transforms |
| Voice design | Design previews, remix a voice, and save a selected generated preview |
| Music | Compose, composition plans, detailed output, video-to-music, upload, and stem separation |
| Sound effects and dialogue | Text-to-audio generation, including dialogue timestamps |
| Dubbing | Create a dub, inspect its status, and retrieve dubbed media or transcripts |
| Forced alignment | Align supplied audio and transcript text |

These extensions are available from either the configured provider instance or the static facade:

```php
$elevenLabs = ElevenLabs::create(['apiKey' => 'xi-...']);

$music = $elevenLabs->music()->compose('A warm acoustic intro.');
$sameResource = ElevenLabs::music();
```

## Speech Generation

Voice IDs are an ElevenLabs requirement, so provide one using the portable `voice()` method.

```php
use AiSdk\ElevenLabs;
use AiSdk\Generate;

$result = Generate::speech('Welcome to the show.')
    ->model(ElevenLabs::model('eleven_flash_v2_5'))
    ->voice('JBFqnCBsd6RMkjVDRZzb')
    ->format('mp3')
    ->providerOptions('elevenlabs', [
        'voice_settings' => [
            'stability' => 0.5,
            'similarity_boost' => 0.75,
        ],
    ])
    ->run();

$result->output->save(__DIR__.'/speech.mp3');
```

Use `providerOptions('elevenlabs', ...)` for documented ElevenLabs request fields such as `apply_text_normalization`, `language_code`, `seed`, and pronunciation dictionaries. Set `output_format` there when an ElevenLabs-specific output format is needed.

The adapter sends `output_format`, `enable_logging`, and the deprecated
`optimize_streaming_latency` field as query parameters, while keeping voice
settings and generation controls in the JSON request body, matching the
ElevenLabs API contract.

## Transcription

```php
use AiSdk\Content;
use AiSdk\ElevenLabs;
use AiSdk\Generate;

$result = Generate::transcription(Content::audio(__DIR__.'/meeting.mp3'))
    ->model(ElevenLabs::model('scribe_v2'))
    ->providerOptions('elevenlabs', [
        'language_code' => 'en',
        'diarize' => true,
    ])
    ->run();

echo $result->output->text;
```

## Realtime Transcription

Scribe v2 Realtime uses the core Live API. Install `aisdk/transport` for the ready-made WebSocket transport:

```bash
composer require aisdk/transport
```

```php
use AiSdk\ElevenLabs;
use AiSdk\Live;
use AiSdk\Live\TranscriptCompleted;
use AiSdk\Live\TranscriptUpdate;
use AiSdk\Transport;

$session = Live::transcribe()
    ->model(ElevenLabs::model('scribe_v2_realtime'))
    ->language('en')
    ->audioFormat('pcm_16000')
    ->providerOptions('elevenlabs', [
        'commit_strategy' => 'manual',
        'include_timestamps' => true,
    ])
    ->connect(Transport::auto());

$session->sendAudio($pcmBytes);
$session->commitAudio();

foreach ($session->events() as $event) {
    if ($event instanceof TranscriptUpdate) {
        echo "\r{$event->text}"; // Revisable partial transcript.
    }

    if ($event instanceof TranscriptCompleted) {
        echo "\n{$event->text}\n";
    }
}
```

`aisdk/transport` is optional. Without it, pass any application transport implementing `AiSdk\Live\Contracts\TransportInterface`; provider event encoding and normalization still come from this package.

For a browser connection, create a short-lived single-use token on your server and return only its value to your authenticated client:

```php
$secret = Live::transcribe()
    ->model(ElevenLabs::model('scribe_v2_realtime'))
    ->clientSecret();

return ['token' => $secret->value, 'expires_at' => $secret->expiresAt];
```

The browser connects natively to ElevenLabs using that token. Never expose the workspace API key to client-side code.

```js
const { token } = await fetch('/api/elevenlabs/scribe-token').then(response => response.json());
const query = new URLSearchParams({
    model_id: 'scribe_v2_realtime',
    audio_format: 'pcm_16000',
    commit_strategy: 'vad',
    token,
});
const socket = new WebSocket(`wss://api.elevenlabs.io/v1/speech-to-text/realtime?${query}`);

socket.addEventListener('message', ({ data }) => {
    const event = JSON.parse(data);

    if (event.message_type === 'partial_transcript') {
        console.log('Partial:', event.text);
    }

    if (event.message_type === 'committed_transcript') {
        console.log('Final:', event.text);
    }
});
```

When `include_timestamps=true`, the ordinary committed transcript is
normalized as `TranscriptCompleted`; the following
`committed_transcript_with_timestamps` message remains available as a raw
`ProviderEvent`, preserving ElevenLabs word metadata without duplicating the
portable completion event.

## ElevenLabs Media Services

These capabilities are intentionally provider-owned instead of being added to `aisdk/core`.

### Voice Changer

```php
$result = ElevenLabs::voiceChanger()->convert(
    voiceId: 'JBFqnCBsd6RMkjVDRZzb',
    audio: Content::audio(__DIR__.'/performance.wav'),
    options: [
        'model_id' => 'eleven_multilingual_sts_v2',
        'remove_background_noise' => true,
        'output_format' => 'wav_44100',
    ],
);

$result->output->save(__DIR__.'/changed.wav');
```

### Voice Isolator

```php
$result = ElevenLabs::voiceIsolator()->isolate(
    Content::audio(__DIR__.'/noisy-interview.mp3'),
);

$result->output->save(__DIR__.'/clean.mp3');
```

### Music and Sound Effects

```php
$music = ElevenLabs::music()->compose('Warm acoustic guitar intro with gentle rain.', [
    'model_id' => 'music_v1',
    'music_length_ms' => 30_000,
]);

$effect = ElevenLabs::soundEffects()->generate('A wooden door slowly creaking open.');
```

Music also supports composition plans and detailed metadata without opting into an HTTP streaming variant:

```php
$plan = ElevenLabs::music()->plan('A tense cinematic cue with a quiet ending.', [
    'model_id' => 'music_v2',
    'music_length_ms' => 30_000,
]);

$track = ElevenLabs::music()->composeFromPlan($plan, [
    'model_id' => 'music_v2',
    'output_format' => 'mp3_48000_192',
]);

$detailed = ElevenLabs::music()->composeDetailed('A bright synth-pop theme.', [
    'model_id' => 'music_v2',
    'with_timestamps' => true,
]);

echo $detailed->songId;
$detailed->output->save(__DIR__.'/theme.mp3');
```

Direct music transforms use typed `Content` inputs:

```php
$soundtrack = ElevenLabs::music()->videoToMusic([
    Content::video(__DIR__.'/opening.mp4'),
    Content::video(__DIR__.'/closing.mp4'),
], [
    'description' => 'Cinematic, restrained, then uplifting.',
    'tags' => ['cinematic', 'orchestral'],
    'model_id' => 'music_v2',
]);

$stems = ElevenLabs::music()->separateStems(
    Content::audio(__DIR__.'/song.mp3'),
    ['stem_variation_id' => 'six_stems_v1'],
);

$stems->save(__DIR__.'/stems.zip');
```

`music()->upload()` uploads source audio for ElevenLabs composition-plan and inpainting workflows and returns its typed song ID, optional plan, and optional word timestamps.

### Text to Dialogue

```php
$dialogue = ElevenLabs::dialogue()->create([
    ['text' => '[giggling] Knock knock.', 'voice_id' => 'JBFqnCBsd6RMkjVDRZzb'],
    ['text' => '[curious] Who is there?', 'voice_id' => 'Aw4FAjKCGjjNkVhN1Xmq'],
], ['model_id' => 'eleven_v3']);
```

Use `withTimestamps()` when character alignment and per-voice segments are needed:

```php
$dialogue = ElevenLabs::dialogue()->withTimestamps([
    ['text' => 'Ready?', 'voice_id' => 'JBFqnCBsd6RMkjVDRZzb'],
    ['text' => 'Ready.', 'voice_id' => 'Aw4FAjKCGjjNkVhN1Xmq'],
]);

$dialogue->output->save(__DIR__.'/dialogue.mp3');
foreach ($dialogue->voiceSegments as $segment) {
    echo "{$segment->voiceId}: {$segment->start} - {$segment->end}\n";
}
```

### Voice Design and Remixing

Voice design is a two-step generation flow: generate previews, then save the selected preview as a usable voice.

```php
$design = ElevenLabs::voiceDesign()->design(
    'A warm, expressive documentary narrator with measured pacing.',
    [
        'model_id' => 'eleven_ttv_v3',
        'auto_generate_text' => true,
    ],
);

$preview = $design->previews[0];
$preview->audio->save(__DIR__.'/voice-preview.mp3');

$voice = ElevenLabs::voiceDesign()->create(
    generatedVoiceId: $preview->generatedVoiceId,
    name: 'Documentary narrator',
    description: 'A warm, expressive documentary narrator with measured pacing.',
);

echo $voice->id;
```

To transform an eligible existing voice instead, call `voiceDesign()->remix($voiceId, $description, $options)` and save one of its generated previews in the same way.

### Dubbing

Create a dub from local audio/video or from a URL-backed `Content` value, inspect the asynchronous job, then retrieve the output:

```php
$job = ElevenLabs::dubbing()->create(
    Content::video(__DIR__.'/interview.mp4'),
    targetLanguage: 'es',
    options: ['source_lang' => 'en'],
);

$status = ElevenLabs::dubbing()->status($job->id);

if ($status->status === 'dubbed') {
    ElevenLabs::dubbing()
        ->output($job->id, 'es')
        ->save(__DIR__.'/interview-es.mp4');

    $subtitles = ElevenLabs::dubbing()->transcript($job->id, 'es', 'srt');
    file_put_contents(__DIR__.'/interview-es.srt', $subtitles->content ?? '');
}
```

### Forced Alignment

```php
$alignment = ElevenLabs::forcedAlignment()->create(
    Content::audio(__DIR__.'/narration.wav'),
    'The exact transcript spoken in the recording.',
);

foreach ($alignment->words as $word) {
    echo "{$word->text}: {$word->start} - {$word->end}\n";
}
```

## Scope

This package deliberately stays on the direct generative/media surface.

| Included | Deliberately excluded |
|---|---|
| TTS, batch STT, and realtime Scribe | ElevenAgents and its telephony/runtime management APIs |
| Voice changer, isolation, design, and remixing | Voice-library browsing, cloning/training, and general voice CRUD |
| Music generation and direct music transforms | Music finetune, asset/history, and marketplace management |
| Sound effects and text-to-dialogue | Studio, Audio Native, Flows, and project/editor management |
| Dubbing creation and result retrieval | Dubbing Studio resource editing, listing, and deletion |
| Forced alignment | Account, workspace, API-key, billing, analytics, and administrative APIs |

Saving a generated voice preview is included because it is the required second step of the Voice Design and Remix APIs. Pronunciation-dictionary management, AI-audio detection, human production services, and HTTP streaming variants of otherwise one-shot media endpoints are outside this package surface. Core realtime transcription remains fully supported through `Live::transcribe()`.

## Testing

```bash
composer test:all
```

The default suite is fixture- and conformance-based. Credentialed Live network
verification is separate and is not run by `composer test`.

## Links

- [ElevenLabs API reference](https://elevenlabs.io/docs/api-reference)
- [Realtime Speech to Text](https://elevenlabs.io/docs/api-reference/speech-to-text/v-1-speech-to-text-realtime)
- [Single-use tokens](https://elevenlabs.io/docs/api-reference/tokens/create)
- [Voice Changer](https://elevenlabs.io/docs/api-reference/speech-to-speech/convert)
- [Voice Isolator](https://elevenlabs.io/docs/api-reference/audio-isolation/convert)
- [Voice Design](https://elevenlabs.io/docs/api-reference/text-to-voice/design)
- [Voice Remix](https://elevenlabs.io/docs/api-reference/text-to-voice/remix)
- [Dubbing](https://elevenlabs.io/docs/api-reference/dubbing/create)
- [Music](https://elevenlabs.io/docs/api-reference/music/compose)
- [Video to Music](https://elevenlabs.io/docs/api-reference/music/video-to-music)
- [Stem Separation](https://elevenlabs.io/docs/api-reference/music/separate-stems)
- [Forced Alignment](https://elevenlabs.io/docs/api-reference/forced-alignment/create)
- [Text to Dialogue with Timestamps](https://elevenlabs.io/docs/api-reference/text-to-dialogue/convert-with-timestamps)
- [Project Documentation](https://github.com/phpaisdk)
