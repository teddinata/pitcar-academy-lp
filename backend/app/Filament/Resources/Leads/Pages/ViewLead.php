<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Services\WhatsAppLinkBuilder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opens a chat *with the lead*, not the one the visitor sent us.
            // Reusing the visitor's link here pointed the consultant at
            // themselves, carrying a message written in the student's voice.
            Action::make('whatsapp')
                ->label('Hubungi lead')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn () => app(WhatsAppLinkBuilder::class)->buildFollowUp($this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => (bool) app(WhatsAppLinkBuilder::class)->buildFollowUp($this->getRecord())),
            EditAction::make(),
        ];
    }
}
