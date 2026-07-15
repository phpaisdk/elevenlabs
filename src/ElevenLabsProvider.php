<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\LiveProviderInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\SpeechProviderInterface;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Contracts\TranscriptionProviderInterface;
use AiSdk\ElevenLabs\Models\ElevenLabsLiveModel;
use AiSdk\ElevenLabs\Models\ElevenLabsSpeechModel;
use AiSdk\ElevenLabs\Models\ElevenLabsTranscriptionModel;
use AiSdk\ElevenLabs\Resources\Dialogue;
use AiSdk\ElevenLabs\Resources\Dubbing;
use AiSdk\ElevenLabs\Resources\ForcedAlignment;
use AiSdk\ElevenLabs\Resources\Music;
use AiSdk\ElevenLabs\Resources\SoundEffects;
use AiSdk\ElevenLabs\Resources\VoiceChanger;
use AiSdk\ElevenLabs\Resources\VoiceDesign;
use AiSdk\ElevenLabs\Resources\VoiceIsolator;
use AiSdk\Live\Contracts\LiveModelInterface;

final class ElevenLabsProvider extends BaseProvider implements LiveProviderInterface, SpeechProviderInterface, TranscriptionProviderInterface
{
    public function __construct(public readonly ElevenLabsOptions $options) {}

    public function name(): string
    {
        return ElevenLabsOptions::PROVIDER_NAME;
    }

    public function voiceChanger(): VoiceChanger
    {
        return new VoiceChanger($this->options);
    }

    public function voiceIsolator(): VoiceIsolator
    {
        return new VoiceIsolator($this->options);
    }

    public function voiceDesign(): VoiceDesign
    {
        return new VoiceDesign($this->options);
    }

    public function music(): Music
    {
        return new Music($this->options);
    }

    public function soundEffects(): SoundEffects
    {
        return new SoundEffects($this->options);
    }

    public function dialogue(): Dialogue
    {
        return new Dialogue($this->options);
    }

    public function dubbing(): Dubbing
    {
        return new Dubbing($this->options);
    }

    public function forcedAlignment(): ForcedAlignment
    {
        return new ForcedAlignment($this->options);
    }

    protected function speechModel(string $modelId): SpeechModelInterface
    {
        return new ElevenLabsSpeechModel($modelId, $this->options);
    }

    protected function transcriptionModel(string $modelId): TranscriptionModelInterface
    {
        return new ElevenLabsTranscriptionModel($modelId, $this->options);
    }

    protected function liveModel(string $modelId): LiveModelInterface
    {
        return new ElevenLabsLiveModel($modelId, $this->options);
    }
}
