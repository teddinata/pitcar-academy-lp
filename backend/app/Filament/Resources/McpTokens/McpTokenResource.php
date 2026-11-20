<?php

namespace App\Filament\Resources\McpTokens;

use App\Filament\Resources\McpTokens\Pages\ListMcpTokens;
use App\Models\McpToken;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Tokens for the read-only MCP server at /mcp. A token reads sales and
 * student data, so only admins make or revoke them.
 */
class McpTokenResource extends Resource
{
    protected static ?string $model = McpToken::class;

    protected static ?string $slug = 'pengaturan/akses-mcp';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Akses MCP';

    protected static ?string $modelLabel = 'Token MCP';

    protected static ?string $pluralModelLabel = 'Akses MCP';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada token')
            ->emptyStateDescription('Buat token untuk menghubungkan Claude Code atau Claude Desktop ke data Pitcar Academy.')
            ->columns([
                TextColumn::make('name')->label('Nama')->weight('bold'),
                TextColumn::make('user.name')->label('Pemilik'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                TextColumn::make('last_used_at')->label('Terakhir dipakai')->since()->placeholder('Belum pernah'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Cabut')
                    ->modalHeading('Cabut token ini?')
                    ->modalDescription('Aplikasi yang memakai token ini langsung kehilangan akses.')
                    ->modalSubmitActionLabel('Cabut'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMcpTokens::route('/')];
    }
}
