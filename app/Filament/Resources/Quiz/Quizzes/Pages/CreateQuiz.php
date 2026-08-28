<?php

namespace App\Filament\Resources\Quiz\Quizzes\Pages;

use App\Filament\Resources\Quiz\Quizzes\QuizResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuiz extends CreateRecord
{
    protected static string $resource = QuizResource::class;
}
