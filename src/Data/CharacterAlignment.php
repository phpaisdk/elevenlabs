<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class CharacterAlignment
{
    /**
     * @param  list<string>  $characters
     * @param  list<float>  $startTimes
     * @param  list<float>  $endTimes
     */
    public function __construct(
        public array $characters,
        public array $startTimes,
        public array $endTimes,
    ) {}
}
