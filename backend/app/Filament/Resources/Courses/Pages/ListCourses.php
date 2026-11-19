<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourses extends ListRecords
{
    use RepublishSite;

    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->republishAction(),
            CreateAction::make()->label('Buat kelas online'),
        ];
    }
}
