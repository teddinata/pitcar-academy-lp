<?php

namespace App\Filament\Resources\Discussions\Pages;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Models\DiscussionPost;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDiscussions extends ListRecords
{
    protected static string $resource = DiscussionResource::class;

    public function getSubheading(): ?string
    {
        return 'Pertanyaan dan pendapat siswa di halaman kelas. Pesan yang melanggar aturan dihapus dari kelas tapi tetap tercatat di sini.';
    }

    public function getTabs(): array
    {
        return [
            'unanswered' => Tab::make('Perlu dijawab')
                ->modifyQueryUsing(fn (Builder $query) => $query->visible()->whereDoesntHave('replies', fn ($r) => $r->whereNotNull('user_id')->whereNull('deleted_at')))
                ->badge(fn () => DiscussionPost::unanswered()->count() ?: null)
                ->badgeColor('warning'),
            // A thread is listed here when it or any reply in it has a report nobody has checked.
            'reported' => Tab::make('Dilaporkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn ($w) => $w
                    ->whereHas('reports', fn ($r) => $r->whereNull('resolved_at'))
                    ->orWhereHas('replies', fn ($r) => $r->whereNull('deleted_at')->whereHas('reports', fn ($x) => $x->whereNull('resolved_at')))))
                ->badge(fn () => DiscussionResource::reportedCount() ?: null)
                ->badgeColor('danger'),
            'all' => Tab::make('Semua')->modifyQueryUsing(fn (Builder $query) => $query->visible()),
            'removed' => Tab::make('Dihapus')->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('deleted_at')),
        ];
    }
}
