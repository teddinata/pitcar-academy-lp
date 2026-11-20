<?php

namespace App\Filament\Resources\McpTokens\Pages;

use App\Filament\Resources\McpTokens\McpTokenResource;
use App\Models\McpToken;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListMcpTokens extends ListRecords
{
    protected static string $resource = McpTokenResource::class;

    public function getSubheading(): ?string
    {
        return 'Server MCP hanya membaca data: kelas, penjualan, iklan, pesanan, siswa, diskusi, dan masukan. Alamatnya '.url('/mcp').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Buat token')
                ->modalHeading('Buat token MCP')
                ->modalDescription('Beri nama sesuai tempat token dipakai, supaya mudah dicabut nanti.')
                ->modalSubmitActionLabel('Buat')
                ->schema([
                    TextInput::make('name')->label('Nama')->placeholder('Claude Code laptop kantor')->required()->maxLength(100),
                ])
                ->action(function (array $data) {
                    $this->replaceMountedAction('showToken', ['token' => McpToken::issue(auth()->user(), $data['name'])]);
                }),
        ];
    }

    /** Shown once, right after a token is made: the plain value is not stored. */
    public function showTokenAction(): Action
    {
        return Action::make('showToken')
            ->modalHeading('Token MCP baru')
            ->modalDescription('Salin sekarang. Token ini tidak akan ditampilkan lagi; kalau hilang, cabut lalu buat yang baru.')
            ->fillForm(fn (array $arguments) => [
                'token' => $arguments['token'] ?? '',
                'command' => 'claude mcp add --transport http pitcar-academy '.url('/mcp').' --header "Authorization: Bearer '.($arguments['token'] ?? '').'"',
            ])
            ->schema([
                TextInput::make('token')->label('Token')->readOnly()->copyable(copyMessage: 'Token disalin'),
                Textarea::make('command')->label('Perintah untuk Claude Code')->readOnly()->rows(3)
                    ->helperText('Jalankan di terminal komputer yang memakai Claude Code.'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Selesai');
    }
}
