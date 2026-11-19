<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Lms\AccessWhatsApp;
use App\Services\Lms\LoginLink;
use App\Support\WhatsAppNumber;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('student.name')
                    ->label('Pembeli')
                    ->searchable()
                    ->description(fn (Order $record) => $record->student->email),

                TextColumn::make('student.whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('course.title')
                    ->label('Kelas')
                    ->limit(32)
                    // A bundle names its add-on under the main class.
                    ->description(fn (Order $record) => $record->items
                        ->where('kind', OrderItem::ADDON)
                        ->map(fn (OrderItem $item) => '+ '.$item->course->title)
                        ->implode(', ') ?: null)
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Paid => 'Lunas',
                        OrderStatus::Pending => 'Menunggu bayar',
                        OrderStatus::Failed => 'Gagal',
                        OrderStatus::Expired => 'Kedaluwarsa',
                    })
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Paid => 'success',
                        OrderStatus::Pending => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('latestWhatsApp.status')
                    ->label('Akses WA')
                    ->badge()
                    ->placeholder('Belum')
                    ->formatStateUsing(fn ($state, Order $record) => $record->latestWhatsApp?->statusLabel())
                    ->color(fn ($state) => match ($state) {
                        'sent', 'opened' => 'success',
                        'queued', 'sending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Order $record) => $record->latestWhatsApp?->last_error)
                    ->description(fn (Order $record) => $record->latestWhatsApp
                        ? ($record->latestWhatsApp->mode === 'auto' ? 'otomatis' : 'manual').', '.$record->latestWhatsApp->updated_at->format('d M H:i')
                        : null),

                TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d M Y H:i')
                    // Paid by status check only means DOKU's notifications are not reaching us.
                    ->description(fn (Order $record) => match ($record->paid_via) {
                        Order::PAID_VIA_NOTIFICATION => 'notifikasi DOKU',
                        Order::PAID_VIA_STATUS_CHECK => 'cek status',
                        default => null,
                    })
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        OrderStatus::Paid->value => 'Lunas',
                        OrderStatus::Pending->value => 'Menunggu bayar',
                        OrderStatus::Failed->value => 'Gagal',
                        OrderStatus::Expired->value => 'Kedaluwarsa',
                    ])
                    ->default(OrderStatus::Paid->value),

                SelectFilter::make('course_id')
                    ->label('Kelas')
                    ->relationship('course', 'title'),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::sendViaKirimdev(),
                    self::openWaMe(),
                    self::resendEmail(),
                ])
                    ->label('Kirim akses')
                    ->icon('heroicon-o-paper-airplane')
                    ->button()
                    ->visible(fn (Order $record) => $record->status === OrderStatus::Paid),
            ]);
    }

    /** The buyer's number can be fixed before sending; a typo at checkout is the usual reason to resend. */
    private static function phoneFields(): array
    {
        return [
            TextInput::make('phone')
                ->label('Nomor WhatsApp')
                ->required()
                ->tel()
                ->rule(fn () => function (string $attribute, $value, $fail) {
                    if (WhatsAppNumber::normalize($value) === null) {
                        $fail('Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');
                    }
                }),
            Toggle::make('save_phone')
                ->label('Simpan nomor ini ke data pembeli')
                ->default(true),
        ];
    }

    private static function applyPhone(Order $order, array $data): string
    {
        $phone = WhatsAppNumber::normalize($data['phone']);

        if (($data['save_phone'] ?? false) && $phone !== $order->student->whatsapp) {
            $order->student->update(['whatsapp' => $phone]);
        }

        return $phone;
    }

    private static function sendViaKirimdev(): Action
    {
        return Action::make('sendWhatsApp')
            ->label('Kirim via WA (kirimdev)')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->modalHeading('Kirim akses kelas lewat WhatsApp')
            ->modalDescription('Pesan dikirim dari nomor WA Pitcar Academy memakai template yang sudah disetujui Meta. Tautan masuk yang baru ikut terkirim.')
            ->modalSubmitActionLabel('Kirim')
            ->fillForm(fn (Order $record) => ['phone' => $record->student->whatsapp, 'save_phone' => true])
            ->schema(self::phoneFields())
            ->action(function (Order $record, array $data, AccessWhatsApp $whatsapp) {
                $delivery = $whatsapp->sendManually($record, auth()->user(), self::applyPhone($record, $data));

                $notification = Notification::make();

                match ($delivery->status) {
                    'sent' => $notification->success()->title('Akses terkirim ke '.$delivery->phone),
                    'queued' => $notification->warning()->title('kirimdev sedang sibuk')->body('Pesan dicoba lagi otomatis dalam satu menit. '.$delivery->last_error),
                    default => $notification->danger()->title('Tidak terkirim')->body(($delivery->last_error ?? 'Alasan tidak diketahui').'. Pakai "Buka WhatsApp" untuk kirim manual.'),
                };

                $notification->persistent()->send();
            });
    }

    private static function openWaMe(): Action
    {
        return Action::make('openWaMe')
            ->label('Buka WhatsApp (manual)')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->modalHeading('Kirim akses dari WhatsApp sendiri')
            ->modalDescription('Pesan dan tautan masuk yang baru disiapkan. Setelah itu, buka WhatsApp dan tekan kirim. Cara ini tidak butuh template Meta.')
            ->modalSubmitActionLabel('Siapkan pesan')
            ->fillForm(fn (Order $record) => ['phone' => $record->student->whatsapp, 'save_phone' => true])
            ->schema(self::phoneFields())
            ->action(function (Order $record, array $data, AccessWhatsApp $whatsapp) {
                $url = $whatsapp->waMeLink($record, auth()->user(), self::applyPhone($record, $data));

                Notification::make()
                    ->success()
                    ->title('Pesan siap')
                    ->body('Tekan "Buka WhatsApp", periksa pesannya, lalu kirim.')
                    ->actions([
                        Action::make('open')
                            ->label('Buka WhatsApp')
                            ->button()
                            ->url($url, shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();
            });
    }

    private static function resendEmail(): Action
    {
        return Action::make('resendEmail')
            ->label('Kirim ulang email')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => 'Tautan masuk yang baru dikirim ke '.$record->student->email.'.')
            ->action(function (Order $record, LoginLink $links) {
                $links->send($record->student, $record->course);

                Notification::make()->success()->title('Email dikirim ke '.$record->student->email)->send();
            });
    }
}
