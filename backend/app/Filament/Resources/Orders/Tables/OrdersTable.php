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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
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
                    // On the unpaid tab the number is in the follow-up form, where it can also be fixed.
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'unpaid')
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

                // Access and payment columns mean nothing for an unpaid order, and push the follow-up button off screen.
                TextColumn::make('latestWhatsApp.status')
                    ->label('Akses WA')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'unpaid')
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
                        ? ($record->latestWhatsApp->mode === 'auto' ? 'otomatis' : 'manual').', '.$record->latestWhatsApp->updated_at->timezone(config('app.display_timezone'))->format('d M H:i')
                        : null),

                TextColumn::make('created_at')
                    ->label('Dipesan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('followed_up_at')
                    ->label('Follow up')
                    ->dateTime('d M H:i')
                    ->description(fn (Order $record) => $record->followedUpBy?->name)
                    ->placeholder('Belum')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'paid'),

                TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) !== 'unpaid')
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
                    ]),

                SelectFilter::make('course_id')
                    ->label('Kelas')
                    ->relationship('course', 'title'),
            ])
            // On the follow-up list the button leads the row, so it never scrolls off a narrow screen.
            ->recordActionsPosition(fn ($livewire) => ($livewire->activeTab ?? null) === 'unpaid' ? RecordActionsPosition::BeforeColumns : null)
            ->recordActions([
                self::followUp(),
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

    /**
     * A WhatsApp message to someone who ordered and did not pay. A payment page
     * that is still open goes in the message; otherwise the class's checkout,
     * so they can order again. The admin sends it from their own WhatsApp.
     */
    private static function followUp(): Action
    {
        return Action::make('followUp')
            ->label('Follow up')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->button()
            ->color(fn (Order $record) => $record->followed_up_at ? 'gray' : 'warning')
            ->visible(fn (Order $record) => $record->status !== OrderStatus::Paid)
            ->modalHeading(fn (Order $record) => 'Follow up '.$record->student->name)
            ->modalDescription(fn (Order $record) => $record->followed_up_at
                ? 'Terakhir di-follow up '.$record->followed_up_at->timezone(config('app.display_timezone'))->format('d M Y H:i').($record->followedUpBy ? ' oleh '.$record->followedUpBy->name : '').'.'
                : 'Periksa pesannya. Setelah disimpan, WhatsApp terbuka dengan pesan ini, tinggal tekan kirim.')
            ->modalSubmitActionLabel('Siapkan pesan')
            ->fillForm(fn (Order $record) => [
                'phone' => $record->student->whatsapp,
                'save_phone' => true,
                'message' => self::followUpMessage($record),
            ])
            ->schema([
                ...self::phoneFields(),
                Textarea::make('message')
                    ->label('Pesan')
                    ->required()
                    ->rows(7)
                    ->maxLength(1500),
            ])
            ->action(function (Order $record, array $data) {
                $phone = self::applyPhone($record, $data);
                $record->update(['followed_up_at' => now(), 'followed_up_by' => auth()->id()]);

                Notification::make()
                    ->success()
                    ->title('Pesan follow up siap')
                    ->body('Tekan "Buka WhatsApp", lalu kirim. Pesanan ini sudah ditandai sudah di-follow up.')
                    ->actions([
                        Action::make('open')
                            ->label('Buka WhatsApp')
                            ->button()
                            ->url('https://wa.me/'.$phone.'?text='.rawurlencode($data['message']), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();
            });
    }

    private static function followUpMessage(Order $order): string
    {
        $first = strtok(trim($order->student->name), ' ') ?: $order->student->name;
        $title = $order->purchaseTitle();
        $open = $order->status === OrderStatus::Pending && $order->payment_url && $order->expires_at?->isFuture();

        if ($open) {
            $until = $order->expires_at->timezone(config('app.display_timezone'))->format('d M H:i');

            return "Halo {$first}, ini dari Pitcar Academy. Pesanan {$title} ({$order->number}) belum dibayar. "
                ."Kalau masih berminat, pembayarannya bisa diselesaikan di sini sebelum {$until} WIB:\n{$order->payment_url}\n\n"
                .'Ada kendala saat membayar? Balas pesan ini saja, kami bantu.';
        }

        $checkout = config('lms.frontend_url').'/checkout/?kelas='.$order->course->slug;

        return "Halo {$first}, ini dari Pitcar Academy. Kamu sempat memesan {$title}, tapi pembayarannya belum selesai. "
            ."Kalau masih berminat, kelasnya bisa dipesan lagi di sini:\n{$checkout}\n\n"
            .'Ada pertanyaan soal isi kelasnya? Balas pesan ini saja.';
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
