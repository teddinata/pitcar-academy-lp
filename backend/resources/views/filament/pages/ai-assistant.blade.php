<x-filament-panels::page>
    @php($messages = $this->messages())
    @php($pending = $messages->contains('status', \App\Models\AiMessage::PENDING))

    <style>
        .pa-ai { display: flex; flex-direction: column; gap: 1.25rem; max-width: 52rem; }
        .pa-ai-log { display: flex; flex-direction: column; gap: 1rem; }
        .pa-ai-turn { display: flex; flex-direction: column; gap: .375rem; }
        .pa-ai-who { font-size: .75rem; font-weight: 600; letter-spacing: .02em; color: var(--gray-500); }
        .pa-ai-q { align-self: flex-end; max-width: 85%; padding: .625rem .875rem; border-radius: .5rem; background: var(--gray-100); white-space: pre-wrap; }
        .dark .pa-ai-q { background: var(--gray-800); }
        .pa-ai-a { line-height: 1.65; }
        .pa-ai-a p, .pa-ai-a ul, .pa-ai-a ol, .pa-ai-a table { margin: 0 0 .75rem; }
        .pa-ai-a ul { list-style: disc; padding-left: 1.25rem; }
        .pa-ai-a ol { list-style: decimal; padding-left: 1.25rem; }
        .pa-ai-a table { border-collapse: collapse; font-size: .875rem; display: block; overflow-x: auto; }
        .pa-ai-a th, .pa-ai-a td { border-bottom: 1px solid var(--gray-200); padding: .375rem .75rem .375rem 0; text-align: left; }
        .dark .pa-ai-a th, .dark .pa-ai-a td { border-color: var(--gray-700); }
        .pa-ai-muted { color: var(--gray-500); font-size: .875rem; }
        .pa-ai-error { color: var(--danger-600); font-size: .875rem; }
        .dark .pa-ai-error { color: var(--danger-400); }
        .pa-ai-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
        .pa-ai-form { display: flex; flex-direction: column; gap: .75rem; border-top: 1px solid var(--gray-200); padding-top: 1rem; }
        .dark .pa-ai-form { border-color: var(--gray-700); }
        .pa-ai-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    </style>

    <div class="pa-ai" @if ($pending) wire:poll.2s @endif>
        @unless ($this->enabled())
            <x-filament::callout color="warning" heading="AI belum aktif">
                <x-slot name="description">Isi ANTHROPIC_API_KEY di .env server, lalu jalankan php artisan config:cache.</x-slot>
            </x-filament::callout>
        @endunless

        @if ($messages->isEmpty())
            <div class="pa-ai-turn">
                <p class="pa-ai-muted">Contoh pertanyaan:</p>
                <div class="pa-ai-chips">
                    @foreach ($this->suggestions() as $suggestion)
                        <x-filament::button color="gray" size="sm" wire:click="suggest({{ $loop->index }})" :disabled="! $this->enabled()">{{ $suggestion }}</x-filament::button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="pa-ai-log" aria-live="polite">
            @foreach ($messages as $message)
                @if ($message->role === 'user')
                    <div class="pa-ai-turn">
                        <span class="pa-ai-who" style="align-self: flex-end">Kamu</span>
                        <div class="pa-ai-q">{{ $message->content }}</div>
                    </div>
                @else
                    <div class="pa-ai-turn">
                        <span class="pa-ai-who">AI Pitcar Academy</span>
                        @if ($message->status === \App\Models\AiMessage::PENDING)
                            <div class="pa-ai-muted" style="display: flex; align-items: center; gap: .5rem">
                                <x-filament::loading-indicator class="h-4 w-4" />
                                Membaca data dan menyusun jawaban. Biasanya 10 sampai 40 detik.
                            </div>
                        @elseif ($message->status === \App\Models\AiMessage::FAILED)
                            <p class="pa-ai-error">Jawaban gagal dibuat{{ $message->error ? ': '.$message->error : '.' }}</p>
                            <div><x-filament::button color="gray" size="sm" wire:click="retry({{ $message->id }})">Tanya ulang</x-filament::button></div>
                        @else
                            <div class="pa-ai-a">{!! \App\Filament\Pages\AiAssistant::html($message->content) !!}</div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <form wire:submit="ask" class="pa-ai-form">
            <label for="pa-ai-question" class="pa-ai-who">Pertanyaan</label>
            <x-filament::input.wrapper :valid="! $errors->has('question')">
                <textarea id="pa-ai-question" wire:model="question" rows="3" maxlength="4000"
                    class="fi-input block w-full"
                    style="width: 100%; resize: vertical; min-height: 5rem; padding: .625rem .75rem"
                    placeholder="Misalnya: berapa penjualan bulan ini dan dari mana pembelinya?"
                    x-on:keydown.meta.enter="$wire.ask()" x-on:keydown.ctrl.enter="$wire.ask()"
                    @disabled($pending || ! $this->enabled())></textarea>
            </x-filament::input.wrapper>
            @error('question') <p class="pa-ai-error">{{ $message }}</p> @enderror
            <div class="pa-ai-row">
                <span class="pa-ai-muted">Ctrl/Cmd + Enter untuk mengirim.</span>
                <div style="display: flex; gap: .5rem">
                    @if ($messages->isNotEmpty())
                        <x-filament::button color="gray" wire:click="newConversation" :disabled="$pending">Percakapan baru</x-filament::button>
                    @endif
                    <x-filament::button type="submit" :disabled="$pending || ! $this->enabled()">Tanya</x-filament::button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
