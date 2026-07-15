<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Data;

final readonly class MusicPlan
{
    /** @param array<string, mixed> $value */
    public function __construct(public array $value) {}
}
