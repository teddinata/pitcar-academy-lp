<?php

namespace App\Filament\Resources\Discussions\Pages;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Filament\Resources\Discussions\ModerationActions;
use Filament\Resources\Pages\ViewRecord;

class ViewDiscussion extends ViewRecord
{
    protected static string $resource = DiscussionResource::class;

    public function getTitle(): string
    {
        return 'Diskusi '.$this->record->course->title;
    }

    protected function getHeaderActions(): array
    {
        $thread = fn () => $this->record->refresh();

        return [
            ModerationActions::reply($thread),
            ModerationActions::dismissReports($thread),
            ModerationActions::remove($thread)->label('Hapus pertanyaan')
                ->after(fn () => $this->redirect(DiscussionResource::getUrl('index'))),
        ];
    }
}
