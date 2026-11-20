<?php

namespace App\Filament\Resources\StudentRequests\Pages;

use App\Filament\Resources\StudentRequests\StudentRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentRequests extends ListRecords
{
    protected static string $resource = StudentRequestResource::class;
}
