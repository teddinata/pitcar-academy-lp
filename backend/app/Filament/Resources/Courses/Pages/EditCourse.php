<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditCourse extends EditRecord
{
    use RepublishSite;

    protected static string $resource = CourseResource::class;

    /** @var array{price: int, strike_price: ?int} */
    private array $pricesBefore = [];

    protected function getHeaderActions(): array
    {
        return [$this->republishAction()];
    }

    protected function beforeSave(): void
    {
        /** @var Course $course */
        $course = $this->getRecord();
        $this->pricesBefore = ['price' => $course->price, 'strike_price' => $course->strike_price];
    }

    protected function afterSave(): void
    {
        /** @var Course $course */
        $course = $this->getRecord()->refresh();
        $after = ['price' => $course->price, 'strike_price' => $course->strike_price];

        if ($after === $this->pricesBefore) {
            return;
        }

        // Who changed what buyers are charged, and from what.
        Log::info('course.price_changed', [
            'course' => $course->slug,
            'before' => $this->pricesBefore,
            'after' => $after,
            'by' => auth()->user()?->email,
        ]);

        Notification::make()
            ->title('Harga tersimpan')
            ->body('Checkout sudah memakai harga baru. Landing page masih menampilkan harga lama sampai situs diterbitkan ulang lewat tombol "Terbitkan ulang situs".')
            ->warning()
            ->persistent()
            ->send();
    }
}
