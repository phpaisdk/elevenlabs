<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\Model;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\ElevenLabsProvider;
use AiSdk\ElevenLabs\Resources\Dialogue;
use AiSdk\ElevenLabs\Resources\Dubbing;
use AiSdk\ElevenLabs\Resources\ForcedAlignment;
use AiSdk\ElevenLabs\Resources\Music;
use AiSdk\ElevenLabs\Resources\SoundEffects;
use AiSdk\ElevenLabs\Resources\VoiceChanger;
use AiSdk\ElevenLabs\Resources\VoiceDesign;
use AiSdk\ElevenLabs\Resources\VoiceIsolator;

final class ElevenLabs
{
    private static ?ElevenLabsProvider $default = null;

    /** @param array<string, mixed> $config */
    public static function create(array $config = []): ElevenLabsProvider
    {
        return self::$default = new ElevenLabsProvider(ElevenLabsOptions::fromArray($config));
    }

    public static function default(): ElevenLabsProvider
    {
        return self::$default ??= self::create();
    }

    public static function reset(): void
    {
        self::$default = null;
    }

    public static function model(string $modelId): Model
    {
        return self::default()->model($modelId);
    }

    public static function voiceChanger(): VoiceChanger
    {
        return self::default()->voiceChanger();
    }

    public static function voiceIsolator(): VoiceIsolator
    {
        return self::default()->voiceIsolator();
    }

    public static function voiceDesign(): VoiceDesign
    {
        return self::default()->voiceDesign();
    }

    public static function music(): Music
    {
        return self::default()->music();
    }

    public static function soundEffects(): SoundEffects
    {
        return self::default()->soundEffects();
    }

    public static function dialogue(): Dialogue
    {
        return self::default()->dialogue();
    }

    public static function dubbing(): Dubbing
    {
        return self::default()->dubbing();
    }

    public static function forcedAlignment(): ForcedAlignment
    {
        return self::default()->forcedAlignment();
    }
}
