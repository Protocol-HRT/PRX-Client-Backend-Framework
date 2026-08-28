<?php

namespace Tests\Feature\Quiz;

use App\Enums\Quiz\QuizQuestionKind;
use App\Filament\Resources\Quiz\Quizzes\Pages\CreateQuiz;
use App\Filament\Resources\Quiz\Quizzes\Pages\ListQuizzes;
use App\Filament\Resources\Quiz\Quizzes\RelationManagers\StepsRelationManager;
use App\Models\Quiz\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The quiz builder has to render and save three levels deep.
 *
 * Steps hold questions hold options, all via `relationship()` repeaters. That
 * is the part most likely to fail silently: a nested repeater that does not
 * persist looks identical to one an operator forgot to fill in.
 */
class QuizAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_the_list_and_create_screens_render(): void
    {
        Quiz::create(['name' => 'Intake', 'slug' => 'intake', 'is_active' => true]);

        Livewire::test(ListQuizzes::class)->assertSuccessful();
        Livewire::test(CreateQuiz::class)->assertSuccessful();
    }

    public function test_a_step_saves_with_its_questions_and_their_options(): void
    {
        $quiz = Quiz::create(['name' => 'Intake', 'slug' => 'intake', 'is_active' => true]);

        Livewire::test(StepsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => \App\Filament\Resources\Quiz\Quizzes\Pages\EditQuiz::class])
            ->callTableAction('create', data: [
                'slug' => 'experience',
                'name' => 'Experience',
                'heading' => 'Tried peptides before?',
                'description' => 'No wrong answer. Most people start from zero.',
                'position' => 3,
                'is_active' => true,
                'questions' => [[
                    'slug' => 'peptide_experience',
                    'kind' => QuizQuestionKind::SingleSelect->value,
                    'prompt' => 'Tried peptides before?',
                    'is_required' => true,
                    'is_active' => true,
                    'options' => [
                        ['value' => 'none', 'label' => 'This would be my first time', 'icon' => 'ti ti-seeding', 'is_exclusive' => false],
                        ['value' => 'experienced', 'label' => 'I use them already', 'is_exclusive' => false],
                    ],
                ]],
            ])
            ->assertHasNoTableActionErrors();

        $step = $quiz->steps()->firstWhere('slug', 'experience');

        $this->assertNotNull($step, 'the step did not save');
        $this->assertSame('No wrong answer. Most people start from zero.', $step->description);

        $question = $step->questions()->firstWhere('slug', 'peptide_experience');
        $this->assertNotNull($question, 'the nested question did not save');

        // quiz_id is denormalised on save — it is what makes the answer key
        // unique across the quiz rather than merely the step.
        $this->assertSame($quiz->id, $question->quiz_id);

        $this->assertSame(
            ['none', 'experienced'],
            $question->options()->orderBy('position')->pluck('value')->all(),
            'the twice-nested options did not save'
        );
    }

    public function test_marking_a_quiz_default_demotes_the_previous_one(): void
    {
        $first = Quiz::create(['name' => 'A', 'slug' => 'a', 'is_active' => true, 'is_default' => true]);

        Livewire::test(CreateQuiz::class)
            ->fillForm(['name' => 'B', 'slug' => 'b', 'is_active' => true, 'is_default' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse($first->fresh()->is_default);
    }
}
