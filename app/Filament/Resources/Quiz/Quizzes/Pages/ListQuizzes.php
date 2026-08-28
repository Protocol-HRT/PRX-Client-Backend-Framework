<?php

namespace App\Filament\Resources\Quiz\Quizzes\Pages;

use App\Filament\Resources\Quiz\Quizzes\QuizResource;
use Filament\Resources\Pages\ListRecords;

class ListQuizzes extends ListRecords
{
    protected static string $resource = QuizResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
