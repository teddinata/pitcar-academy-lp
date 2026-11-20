<?php

namespace App\Filament\Resources\ScalevUnpaidOrders\Pages;

use App\Filament\Resources\ScalevUnpaidOrders\ScalevUnpaidOrderResource;
use App\Filament\Resources\ScalevUnpaidOrders\Tables\ScalevUnpaidOrdersTable;
use App\Mail\ScalevUnpaidMail;
use App\Models\ScalevUnpaidOrder;
use App\Services\Lms\ScalevUnpaidMailer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListScalevUnpaidOrders extends ListRecords
{
    protected static string $resource = ScalevUnpaidOrderResource::class;

    public function getSubheading(): ?string
    {
        return 'Pesanan di Scalev yang belum dibayar, '.ScalevUnpaidOrder::KEEP_DAYS.' hari terakhir. Follow up mengirim pembeli ke checkout baru dengan harga sekarang. Diperbarui tiap jam; yang sudah bayar atau sudah punya kelasnya hilang sendiri.';
    }

    protected function getHeaderActions(): array
    {
        $waiting = fn () => ScalevUnpaidOrder::toEmail()->count();

        return [
            Action::make('previewEmail')
                ->label('Lihat contoh email')
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->visible(fn () => ScalevUnpaidOrder::toFollowUp()->exists())
                ->modalHeading('Contoh email follow-up')
                ->modalDescription('Diisi dengan pesanan teratas di daftar. Setiap pembeli menerima nama, kelas, dan harga miliknya sendiri.')
                ->modalContent(function () {
                    $order = ScalevUnpaidOrder::toFollowUp()->with('course')->latest('ordered_at')->first();
                    $mail = new ScalevUnpaidMail($order);

                    return new HtmlString('<p style="margin-bottom:.5rem;font-weight:600">Subjek: '.e($mail->envelope()->subject).'</p>'
                        .'<iframe title="Contoh email" sandbox="" style="width:100%;height:28rem;border:1px solid rgba(127,127,127,.3);border-radius:.5rem;background:#fff" srcdoc="'.e($mail->render()).'"></iframe>');
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            Action::make('emailAll')
                ->label(fn () => 'Kirim email ke semua ('.$waiting().')')
                ->icon('heroicon-o-envelope')
                ->visible(fn () => $waiting() > 0)
                ->requiresConfirmation()
                ->modalHeading('Kirim email follow-up ke semua?')
                ->modalDescription(fn () => $waiting().' pembeli menerima satu email berisi link checkout baru dengan harga sekarang. Yang sudah pernah dikirimi email tidak dikirimi lagi, dan pesanan tanpa email tetap perlu di-follow up lewat WhatsApp.')
                ->modalSubmitActionLabel('Kirim')
                ->action(function () {
                    ScalevUnpaidOrdersTable::queuedNotice(app(ScalevUnpaidMailer::class)->queue(ScalevUnpaidOrder::toEmail()->get()));
                }),
        ];
    }
}
