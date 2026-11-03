# PHP AI SDK — ElevenLabs

Speech generation with ElevenLabs for the framework-agnostic [PHP AI SDK](https://phpaisdk.com). Also supports transcription and live sessions.

## Installation

```bash
composer require aisdk/elevenlabs
```

Set `ELEVENLABS_API_KEY` in your environment and replace `your-voice-id` with a voice from your ElevenLabs account.

## Usage

```php
use AiSdk\ElevenLabs;
use AiSdk\Generate;

$result = Generate::speech('Welcome to the PHP AI SDK.')
    ->model(ElevenLabs::model('eleven_v4'))
    ->voice('your-voice-id')
    ->run();

$result->output->save(__DIR__.'/speech.mp3');
```

## Documentation

[Read the documentation](https://phpaisdk.com/docs/0.x/providers/elevenlabs) for configuration, supported features, and more examples.

[Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md) · [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
