<?php

namespace App\Services\Lms;

use App\Enums\UserRole;
use App\Filament\Resources\Discussions\DiscussionResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\StudentRequests\StudentRequestResource;
use App\Models\DiscussionPost;
use App\Models\Order;
use App\Models\StudentRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/**
 * The bell in the admin: new orders, payments, student messages, discussion
 * questions and reported posts, for the managers and admins who follow them
 * up. Never throws: a missed alert must not undo an order or a payment.
 */
class TeamAlerts
{
    public function newOrder(Order $order): void
    {
        $this->send(fn () => Notification::make()
            ->title('Pesanan baru')
            ->body($this->who($order).' memesan '.$order->purchaseTitle().', '.$this->rupiah($order->amount).'. Belum dibayar. ('.$order->number.')')
            ->icon('heroicon-o-shopping-cart')
            ->iconColor('warning')
            ->actions([$this->open('Lihat yang belum bayar', OrderResource::getUrl('index', ['tab' => 'unpaid'], panel: 'admin'))]));
    }

    public function paid(Order $order): void
    {
        $this->send(fn () => Notification::make()
            ->title('Pembayaran diterima')
            ->body($this->who($order).' membayar '.$order->purchaseTitle().', '.$this->rupiah($order->amount).'. ('.$order->number.')')
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions([$this->open('Lihat pesanan', OrderResource::getUrl('index', ['tab' => 'paid'], panel: 'admin'))]));
    }

    public function newRequest(StudentRequest $request): void
    {
        $this->send(fn () => Notification::make()
            ->title($request->kind->label().' dari siswa')
            ->body($request->student->name.($request->course ? ' tentang '.$request->course->title : '').': '.str($request->body)->limit(120))
            ->icon('heroicon-o-inbox-arrow-down')
            ->iconColor('info')
            ->actions([$this->open('Tanggapi', StudentRequestResource::getUrl('index', panel: 'admin'))]));
    }

    public function newThread(DiscussionPost $post): void
    {
        $this->send(fn () => Notification::make()
            ->title('Pertanyaan baru di diskusi')
            ->body($post->student->name.' di '.$post->course->title.': '.str($post->body)->limit(120))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('info')
            ->actions([$this->open('Buka diskusi', DiscussionResource::getUrl('view', ['record' => $post], panel: 'admin'))]));
    }

    public function reported(DiscussionPost $post): void
    {
        $thread = $post->isThread() ? $post : $post->thread;

        $this->send(fn () => Notification::make()
            ->title('Pesan dilaporkan siswa')
            ->body('Pesan '.$post->authorName().' di '.$post->course->title.': '.str($post->body)->limit(120))
            ->icon('heroicon-o-flag')
            ->iconColor('danger')
            ->actions([$this->open('Periksa', DiscussionResource::getUrl('view', ['record' => $thread], panel: 'admin'))]));
    }

    /** @param callable(): Notification $make */
    private function send(callable $make): void
    {
        try {
            $team = User::whereIn('role', [UserRole::Admin, UserRole::Manager])->where('is_active', true)->get();

            if ($team->isNotEmpty()) {
                $make()->sendToDatabase($team);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function open(string $label, string $url): Action
    {
        return Action::make('open')->label($label)->button()->url($url)->markAsRead();
    }

    private function who(Order $order): string
    {
        return $order->student->name;
    }

    private function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
