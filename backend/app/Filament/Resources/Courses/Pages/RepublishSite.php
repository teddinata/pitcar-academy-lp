<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Services\SiteRebuild;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** The "Terbitkan ulang situs" button, shared by the list and edit pages. */
trait RepublishSite
{
    protected function republishAction(): Action
    {
        return Action::make('republish')
            ->label('Terbitkan ulang situs')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn () => auth()->user()?->isAdmin() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Terbitkan ulang situs?')
            ->modalDescription('Landing page dibangun ulang dengan harga terbaru dari menu ini. Prosesnya beberapa menit, dan situs tetap bisa diakses selama itu. Checkout tidak terpengaruh, karena selalu memakai harga terbaru.')
            ->modalSubmitActionLabel('Terbitkan ulang')
            ->action(function (SiteRebuild $site) {
                $result = $site->dispatch(auth()->user()?->email);

                Notification::make()
                    ->title($result['ok'] ? 'Diterbitkan ulang' : 'Belum bisa diterbitkan ulang')
                    ->body($result['message'])
                    ->{$result['ok'] ? 'success' : 'warning'}()
                    ->persistent()
                    ->send();
            });
    }
}
