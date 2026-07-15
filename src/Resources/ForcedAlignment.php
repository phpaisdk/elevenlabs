<?php

declare(strict_types=1);

namespace AiSdk\ElevenLabs\Resources;

use AiSdk\Content;
use AiSdk\ElevenLabs\Data\AlignmentItem;
use AiSdk\ElevenLabs\Data\ForcedAlignmentResult;
use AiSdk\ElevenLabs\ElevenLabsOptions;
use AiSdk\ElevenLabs\Support\MediaRequest;

final readonly class ForcedAlignment
{
    public function __construct(private ElevenLabsOptions $options) {}

    public function create(Content $audio, string $text): ForcedAlignmentResult
    {
        $request = new MediaRequest($this->options);
        $payload = $request->multipartJson('/forced-alignment', [
            $request->contentPart('file', $audio, 'audio.wav', 'audio/wav'),
            ['name' => 'text', 'value' => $text],
        ]);

        return new ForcedAlignmentResult(
            characters: $this->items($payload['characters'] ?? null),
            words: $this->items($payload['words'] ?? null),
            loss: is_numeric($payload['loss'] ?? null) ? (float) $payload['loss'] : 0.0,
            rawResponse: $payload,
        );
    }

    /** @return list<AlignmentItem> */
    private function items(mixed $value): array
    {
        $items = [];
        foreach (is_array($value) ? $value : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = new AlignmentItem(
                text: is_string($item['text'] ?? null) ? $item['text'] : '',
                start: is_numeric($item['start'] ?? null) ? (float) $item['start'] : 0.0,
                end: is_numeric($item['end'] ?? null) ? (float) $item['end'] : 0.0,
                loss: is_numeric($item['loss'] ?? null) ? (float) $item['loss'] : null,
            );
        }

        return $items;
    }
}
