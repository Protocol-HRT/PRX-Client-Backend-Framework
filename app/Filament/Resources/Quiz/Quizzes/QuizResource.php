<?php

namespace App\Filament\Resources\Quiz\Quizzes;

use App\Filament\Resources\Quiz\Quizzes\Pages\CreateQuiz;
use App\Filament\Resources\Quiz\Quizzes\Pages\EditQuiz;
use App\Filament\Resources\Quiz\Quizzes\Pages\ListQuizzes;
use App\Filament\Resources\Quiz\Quizzes\RelationManagers\StepsRelationManager;
use App\Filament\Resources\Quiz\Quizzes\Schemas\QuizForm;
use App\Filament\Resources\Quiz\Quizzes\Tables\QuizzesTable;
use App\Models\Quiz\Quiz;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The intake quiz, authored rather than coded.
 *
 * Structure is edited here; the questions themselves live under each step, so
 * an operator reads the quiz in the order a visitor meets it.
 */
class QuizResource extends Resource
{
    protected static ?string $model = Quiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Intake quiz';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return QuizForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizzesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizzes::route('/'),
            'create' => CreateQuiz::route('/create'),
            'edit' => EditQuiz::route('/{record}/edit'),
        ];
    }
}
