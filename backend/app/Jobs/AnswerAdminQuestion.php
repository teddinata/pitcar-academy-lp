<?php

namespace App\Jobs;

use App\Models\AiMessage;
use App\Services\Ai\AdminAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Runs on the worker: a question with several tool calls can outlast a web request. */
class AnswerAdminQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $messageId) {}

    public function handle(AdminAssistant $assistant): void
    {
        $message = AiMessage::find($this->messageId);

        if ($message?->status === AiMessage::PENDING) {
            $assistant->answer($message);
        }
    }

    public function failed(?\Throwable $e): void
    {
        AiMessage::whereKey($this->messageId)->where('status', AiMessage::PENDING)
            ->update(['status' => AiMessage::FAILED, 'error' => 'Waktu habis saat menyusun jawaban.']);
    }
}
