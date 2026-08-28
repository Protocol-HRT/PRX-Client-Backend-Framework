<?php

namespace App\Filament\Resources\Quiz\Quizzes\Pages;

use App\Filament\Resources\Quiz\Quizzes\QuizResource;
use Filament\Resources\Pages\EditRecord;

class EditQuiz extends EditRecord
{
    protected static string $resource = QuizResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\DeleteAction::make()];
    }
}
