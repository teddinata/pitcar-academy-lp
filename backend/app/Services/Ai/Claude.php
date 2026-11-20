<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\FileParam;
use App\Models\LessonAttachment;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The one place that talks to the Claude API. Everything else gets text and
 * token counts back, so tests can swap this class for a fake.
 *
 * Requests go through the beta endpoint for one reason: server-side
 * fallbacks, so a request a safety classifier declines is retried on a
 * model the API picks instead of failing the student's question.
 */
class Claude
{
    private const BETAS = ['server-side-fallback-2026-07-01'];

    private ?Client $client = null;

    public function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Streams one answer. $onText receives each piece of text as it arrives.
     *
     * @param  list<array<string, mixed>>  $system
     * @param  list<array<string, mixed>>  $messages
     * @param  callable(string): void  $onText
     */
    public function stream(array $system, array $messages, callable $onText, string $effort = 'medium'): Reply
    {
        $stream = $this->client()->beta->messages->createStream(
            model: $this->model(),
            maxTokens: 16000,
            system: $system,
            messages: $messages,
            outputConfig: ['effort' => $effort],
            fallbacks: 'default',
            betas: self::BETAS,
        );

        $text = '';
        $reply = new Reply;

        foreach ($stream as $event) {
            if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                $text .= $event->delta->text;
                $onText($event->delta->text);
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $reply->stopReason = $event->delta->stopReason;
                $reply->inputTokens = (int) $event->usage->inputTokens;
                $reply->outputTokens = $event->usage->outputTokens;
                $reply->cacheReadTokens = (int) $event->usage->cacheReadInputTokens;
                $reply->cacheWriteTokens = (int) $event->usage->cacheCreationInputTokens;
            }
        }

        $reply->text = $text;

        return $reply;
    }

    /**
     * Answers with tools: runs each tool Claude asks for through $runTool and
     * loops until it writes its answer. Within the loop the assistant turns
     * go back exactly as they came, thinking blocks included.
     *
     * @param  list<array<string, mixed>>  $system
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  callable(string, array<string, mixed>): string  $runTool
     */
    public function runTools(array $system, array $messages, array $tools, callable $runTool, int $maxRounds = 8): Reply
    {
        $reply = new Reply;

        for ($round = 0; $round < $maxRounds; $round++) {
            $message = $this->client()->beta->messages->create(
                model: $this->model(),
                maxTokens: 16000,
                system: $system,
                messages: $messages,
                tools: $tools,
                outputConfig: ['effort' => 'medium'],
                fallbacks: 'default',
                betas: self::BETAS,
            );

            $reply->stopReason = $message->stopReason;
            $reply->inputTokens += (int) $message->usage->inputTokens;
            $reply->outputTokens += $message->usage->outputTokens;
            $reply->cacheReadTokens += (int) $message->usage->cacheReadInputTokens;
            $reply->cacheWriteTokens += (int) $message->usage->cacheCreationInputTokens;

            if ($message->stopReason !== 'tool_use') {
                foreach ($message->content as $block) {
                    if ($block->type === 'text') {
                        $reply->text .= $block->text;
                    }
                }

                return $reply;
            }

            $results = [];
            foreach ($message->content as $block) {
                if ($block instanceof BetaToolUseBlock) {
                    try {
                        $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => $runTool($block->name, (array) $block->input)];
                    } catch (\Throwable $e) {
                        report($e);
                        $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => 'Error: '.$e->getMessage(), 'isError' => true];
                    }
                }
            }

            $messages[] = ['role' => 'assistant', 'content' => array_map($this->replay(...), $message->content)];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        throw new RuntimeException('AI tidak selesai menyusun jawaban setelah '.$maxRounds.' langkah.');
    }

    /**
     * An assistant block as it goes back in the tool loop, as a plain array
     * with the same content. Response objects mixed into a request message
     * stop the SDK from recognising it, and an empty tool input then goes
     * out as [] where the API needs {}.
     */
    private function replay(mixed $block): array
    {
        return json_decode(json_encode($block), true);
    }

    /** Uploads a class PDF once and remembers its Files API id. */
    public function fileId(LessonAttachment $attachment): ?string
    {
        if ($attachment->ai_file_id) {
            return $attachment->ai_file_id;
        }

        if ($attachment->mime !== 'application/pdf' || ! Storage::exists($attachment->path)) {
            return null;
        }

        $file = $this->client()->files->upload(
            file: FileParam::fromString((string) Storage::get($attachment->path), $attachment->name, 'application/pdf'),
        );
        $attachment->forceFill(['ai_file_id' => $file->id])->save();

        return $file->id;
    }

    private function model(): string
    {
        return (string) config('services.anthropic.model');
    }

    private function client(): Client
    {
        if (! $this->configured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY belum diisi.');
        }

        return $this->client ??= new Client(
            apiKey: (string) config('services.anthropic.key'),
            baseUrl: config('services.anthropic.base_url') ?: null,
            requestOptions: ['timeout' => 120],
        );
    }
}
