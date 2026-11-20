<?php

namespace App\Services\Ai;

/** An answer from Claude and what it cost, in tokens. */
class Reply
{
    public string $text = '';

    public ?string $stopReason = null;

    public int $inputTokens = 0;

    public int $outputTokens = 0;

    public int $cacheReadTokens = 0;

    public int $cacheWriteTokens = 0;

    public function refused(): bool
    {
        return $this->stopReason === 'refusal';
    }

    /** @return array<string, int> the token columns of ai_messages */
    public function usage(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
        ];
    }
}
