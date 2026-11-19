<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\EducationConsultant;
use App\Support\LeadOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tindak lanjut')
                ->description('Hanya bagian ini yang boleh diubah dari dashboard.')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(array_combine(LeadOptions::STATUSES, LeadOptions::STATUSES))
                        ->required()
                        ->live(),

                    Select::make('assigned_consultant_id')
                        ->label('Education Consultant')
                        ->options(fn () => EducationConsultant::query()
                            ->where('is_active', true)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->placeholder('Belum di-assign'),

                    DateTimePicker::make('follow_up_due_at')
                        ->label('Batas follow-up')
                        ->seconds(false),

                    TextInput::make('lost_reason')
                        ->label('Alasan lost')
                        ->maxLength(128)
                        // Asked for exactly when it applies, so the reason is
                        // actually recorded instead of left blank.
                        ->required(fn ($get) => in_array($get('status'), ['lost', 'invalid'], true))
                        ->visible(fn ($get) => in_array($get('status'), ['lost', 'invalid'], true)),
                ]),

            Section::make('Jawaban pengunjung')
                ->description('Apa yang diisi di landing page. Tidak diubah dari sini.')
                ->columns(2)
                ->schema([
                    Placeholder::make('name')->label('Nama')
                        ->content(fn ($record) => $record?->name),
                    Placeholder::make('whatsapp')->label('WhatsApp')
                        ->content(fn ($record) => $record?->whatsapp_normalized),
                    Placeholder::make('domicile')->label('Domisili')
                        ->content(fn ($record) => $record?->domicile),
                    Placeholder::make('program_interest')->label('Program diminati')
                        ->content(fn ($record) => $record ? LeadOptions::label(LeadOptions::PROGRAMS, $record->program_interest) : null),
                    Placeholder::make('activity')->label('Aktivitas (form lama)')
                        ->content(fn ($record) => $record?->activity
                            ? LeadOptions::label(LeadOptions::ACTIVITIES, $record->activity)
                            : '—')
                        ->visible(fn ($record) => filled($record?->activity)),
                    Placeholder::make('goal')->label('Tujuan')
                        ->content(fn ($record) => $record ? LeadOptions::label(LeadOptions::GOALS, $record->goal) : null),
                    Placeholder::make('readiness')->label('Kesiapan mengikuti program')
                        ->content(fn ($record) => $record?->readiness
                            ? LeadOptions::label(LeadOptions::READINESS, $record->readiness)
                            : '—'),
                    // Only ever set on leads captured before the short form.
                    Placeholder::make('timeline')->label('Rencana mulai (form lama)')
                        ->content(fn ($record) => $record?->timeline
                            ? LeadOptions::label(LeadOptions::TIMELINES, $record->timeline)
                            : '—')
                        ->visible(fn ($record) => filled($record?->timeline)),
                ]),

            Section::make('Skor & atribusi')
                ->description('Dihitung server. Read-only agar audit funnel tetap dapat dipercaya.')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Placeholder::make('score')->label('Skor')
                        ->content(fn ($record) => $record ? $record->score.' ('.$record->qualification.')' : null),
                    Placeholder::make('scoring_version')->label('Versi scoring')
                        ->content(fn ($record) => $record?->scoring_version),
                    Placeholder::make('scoring_reasons')->label('Alasan skor')
                        ->content(fn ($record) => collect($record?->scoring_reasons ?? [])
                            ->map(fn ($r) => $r['rule'].'='.$r['value'].' (+'.$r['points'].')')
                            ->implode(', ') ?: '—'),
                    Placeholder::make('source_cta')->label('Source CTA')
                        ->content(fn ($record) => $record?->source_cta),
                    Placeholder::make('utm')->label('UTM')
                        ->content(fn ($record) => collect([
                            $record?->utm_source, $record?->utm_medium, $record?->utm_campaign,
                        ])->filter()->implode(' / ') ?: '—'),
                    Placeholder::make('landing_page')->label('Landing page')
                        ->content(fn ($record) => $record?->landing_page ?: '—'),
                    Placeholder::make('submission_id')->label('Submission ID')
                        ->content(fn ($record) => $record?->submission_id),
                    Placeholder::make('submission_count')->label('Jumlah submit')
                        ->content(fn ($record) => $record?->submission_count),
                    Placeholder::make('consent_at')->label('Persetujuan data')
                        ->content(fn ($record) => $record?->consent_at?->format('d M Y H:i')),
                ]),
        ]);
    }
}
