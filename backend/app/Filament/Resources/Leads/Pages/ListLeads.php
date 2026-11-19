<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Support\LeadCsvExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (LeadCsvExporter $exporter) {
                    // Exports whatever the table currently shows, which already
                    // includes the consultant scoping from the resource query.
                    $query = $this->getFilteredSortedTableQuery();

                    Log::info('leads.exported', [
                        'user_id' => auth()->id(),
                        'rows' => (clone $query)->toBase()->getCountForPagination(),
                    ]);

                    return $exporter->stream($query, 'leads-'.now()->format('Ymd-His').'.csv');
                }),
        ];
    }
}
