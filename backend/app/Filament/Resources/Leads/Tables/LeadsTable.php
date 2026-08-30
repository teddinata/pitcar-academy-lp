<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\EducationConsultant;
use App\Models\Lead;
use App\Support\LeadOptions;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('lead_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->domicile),

                TextColumn::make('whatsapp_normalized')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->url(fn ($record) => 'https://wa.me/'.$record->whatsapp_normalized)
                    ->openUrlInNewTab(),

                TextColumn::make('qualification')
                    ->label('Kualifikasi')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'hot' => 'danger',
                        'qualified' => 'success',
                        'nurture' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn ($record) => 'skor '.$record->score),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'converted', 'registered' => 'success',
                        'lost', 'invalid' => 'danger',
                        'new' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('readiness')
                    ->label('Kesiapan')
                    ->formatStateUsing(fn (?string $state) => $state ? LeadOptions::label(LeadOptions::READINESS, $state) : '—')
                    ->toggleable(),

                TextColumn::make('program_interest')
                    ->label('Program')
                    ->formatStateUsing(fn (string $state) => LeadOptions::label(LeadOptions::PROGRAMS, $state))
                    ->toggleable(),

                TextColumn::make('consultant.name')
                    ->label('Consultant')
                    ->placeholder('Belum di-assign')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('follow_up_due_at')
                    ->label('SLA')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->color(fn ($record) => self::isOverdue($record) ? 'danger' : null)
                    ->description(fn ($record) => self::isOverdue($record) ? 'Terlewat' : null)
                    ->toggleable(),

                TextColumn::make('source_cta')
                    ->label('CTA')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('utm_source')
                    ->label('UTM source')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('utm_campaign')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('qualification')
                    ->label('Kualifikasi')
                    ->multiple()
                    ->options([
                        'hot' => 'Hot',
                        'qualified' => 'Qualified',
                        'nurture' => 'Nurture',
                        'low_intent' => 'Low intent',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(array_combine(LeadOptions::STATUSES, LeadOptions::STATUSES)),

                SelectFilter::make('program_interest')
                    ->label('Program')
                    ->multiple()
                    ->options(LeadOptions::PROGRAMS),

                SelectFilter::make('readiness')
                    ->label('Kesiapan')
                    ->multiple()
                    ->options(LeadOptions::READINESS),

                SelectFilter::make('assigned_consultant_id')
                    ->label('Consultant')
                    ->relationship('consultant', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('utm_source')
                    ->label('UTM source')
                    ->options(fn () => Lead::query()
                        ->whereNotNull('utm_source')
                        ->distinct()
                        ->pluck('utm_source', 'utm_source')
                        ->all()),

                SelectFilter::make('source_cta')
                    ->label('Source CTA')
                    ->options(fn () => Lead::query()
                        ->distinct()
                        ->pluck('source_cta', 'source_cta')
                        ->all()),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('Masuk dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari '.$data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai '.$data['until'];
                        }

                        return $indicators;
                    }),

                Filter::make('overdue')
                    ->label('SLA terlewat')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('follow_up_due_at')
                        ->where('follow_up_due_at', '<', now())
                        ->whereNotIn('status', ['converted', 'registered', 'lost', 'invalid'])),

                Filter::make('unassigned')
                    ->label('Belum di-assign')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('assigned_consultant_id')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->label('Assign ke consultant')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Select::make('assigned_consultant_id')
                                ->label('Education Consultant')
                                ->options(fn () => EducationConsultant::query()
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->all())
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->forceFill([
                                    'assigned_consultant_id' => $data['assigned_consultant_id'],
                                    'assignment_reason' => 'manual_assignment',
                                    'assigned_at' => now(),
                                    // Only nudge brand-new leads forward; an
                                    // in-progress lead keeps the status it has.
                                    'status' => $record->status === 'new' ? 'assigned' : $record->status,
                                ])->save();
                            }

                            Notification::make()
                                ->success()
                                ->title($records->count().' lead di-assign')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->seesAllLeads() ?? false),
                ]),
            ]);
    }

    private static function isOverdue($record): bool
    {
        return $record->follow_up_due_at
            && $record->follow_up_due_at->isPast()
            && ! in_array($record->status, ['converted', 'registered', 'lost', 'invalid'], true);
    }
}
