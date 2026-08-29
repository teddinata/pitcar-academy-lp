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
            Action::make('whatsapp')
                ->label('Buka WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn () => app(WhatsAppLinkBuilder::class)->build($this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => (bool) app(WhatsAppLinkBuilder::class)->build($this->getRecord())),
            EditAction::make(),
        ];
    }
}
