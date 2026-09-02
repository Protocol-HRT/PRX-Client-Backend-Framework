<?php

namespace App\Console\Commands\Quiz;

use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Kb\HealthGoal;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizQuestionOption;
use App\Models\Quiz\QuizStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import a quiz definition from a JSON file.
 *
 *     php artisan quiz:import database/seeders/data/default-quiz.json
 *     php artisan quiz:import database/seeders/data/default-quiz.json --dry-run
 *     php artisan quiz:import database/seeders/data/default-quiz.json --force
 *
 * The JSON structure:
 *   { name, slug, description, is_active, is_default,
 *     health_goals: [
 *       { name, slug, prompt, description, icon, show_in_quiz, position }
 *     ],
 *     steps: [
 *       { slug, name, heading, description, position, visible_when, questions: [
 *           { slug, kind, prompt, help, is_required, position, visible_when, config, options: [
 *               { value, label, description, icon, is_exclusive, price_source, position }
 *           ]}
 *       ]}
 *   ]}
 *
 * Re-runnable: keyed on quiz slug. A second run updates the existing quiz
 * unless --force creates a new one. Options are replaced wholesale on each
 * question (not merged), because the JSON is the source of truth.
 */
class ImportQuizCommand extends Command
{
    protected $signature = 'quiz:import
        {path? : Path to the JSON file (default: database/seeders/data/default-quiz.json)}
        {--dry-run : Report what would change without writing}
        {--force : Delete and recreate the quiz even if it already exists}';

    protected $description = 'Import a quiz definition from a JSON file into the database';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($path === '') {
            $path = database_path('seeders/data/default-quiz.json');
        }

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['name'], $data['steps'])) {
            $this->error('Invalid JSON: must have "name" and "steps" keys.');

            return self::FAILURE;
        }

        // Validate no empty steps
        foreach ($data['steps'] as $i => $step) {
            $questions = $step['questions'] ?? [];
            if (count($questions) === 0) {
                $this->error(sprintf(
                    'Step #%d ("%s") has no questions. Every step must have at least one question.',
                    $i,
                    $step['name'] ?? $step['slug'] ?? "step-{$i}"
                ));

                return self::FAILURE;
            }
        }

        $this->components->info(sprintf(
            'Importing quiz "%s" from %s%s',
            $data['name'],
            basename($path),
            $dryRun ? ' — DRY RUN' : ''
        ));

        try {
            $stats = $this->import($data, $dryRun, $force);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Health Goals', 'Quiz Created', 'Quiz Updated', 'Steps', 'Questions', 'Options'],
            [[$stats['health_goals'], $stats['created'], $stats['updated'], $stats['steps'], $stats['questions'], $stats['options']]]
        );

        if ($dryRun) {
            $this->components->warn('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, updated: int, steps: int, questions: int, options: int, health_goals: int}
     */
    private function import(array $data, bool $dryRun, bool $force): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'steps' => 0, 'questions' => 0, 'options' => 0, 'health_goals' => 0];

        // Seed health goals if provided
        if (isset($data['health_goals']) && is_array($data['health_goals'])) {
            $stats['health_goals'] = $this->importHealthGoals($data['health_goals'], $dryRun);
        }

        $existing = Quiz::withTrashed()->where('slug', $data['slug'] ?? str($data['name'])->slug()->value())->first();

        if ($existing && $force && ! $dryRun) {
            $existing->forceDelete();
            $existing = null;
        }

        if ($dryRun) {
            $stats[$existing ? 'updated' : 'created']++;
            $this->line(sprintf(
                '  Quiz: %s — would %s',
                $data['name'],
                $existing ? 'update' : 'create'
            ));

            if (isset($data['health_goals']) && is_array($data['health_goals'])) {
                $this->line(sprintf('  Health Goals: %d to seed', count($data['health_goals']));
            }

            $this->previewSteps($data['steps'], $stats);

            return $stats;
        }

        DB::transaction(function () use ($data, $existing, &$stats): void {
            $quiz = $existing
                ? $existing->fill($this->quizAttributes($data))
                : Quiz::create($this->quizAttributes($data));

            // If is_default, clear other defaults
            if (($data['is_default'] ?? false) && ! $existing) {
                Quiz::where('id', '!=', $quiz->id)->update(['is_default' => false]);
            }

            $stats[$existing ? 'updated' : 'created']++;

            // Replace all steps (cascade deletes questions and options)
            if ($existing) {
                $quiz->steps()->forceDelete();
            }

            foreach ($data['steps'] as $stepData) {
                $step = $quiz->steps()->create([
                    'slug' => $stepData['slug'],
                    'name' => $stepData['name'],
                    'heading' => $stepData['heading'] ?? null,
                    'description' => $stepData['description'] ?? null,
                    'position' => $stepData['position'] ?? 0,
                    'is_active' => $stepData['is_active'] ?? true,
                    'visible_when' => $stepData['visible_when'] ?? null,
                ]);

                $stats['steps']++;

                foreach ($stepData['questions'] ?? [] as $questionData) {
                    $question = $step->questions()->create([
                        'quiz_id' => $quiz->id,
                        'slug' => $questionData['slug'],
                        'kind' => $questionData['kind'],
                        'prompt' => $questionData['prompt'],
                        'help' => $questionData['help'] ?? null,
                        'is_required' => $questionData['is_required'] ?? true,
                        'position' => $questionData['position'] ?? 0,
                        'is_active' => $questionData['is_active'] ?? true,
                        'visible_when' => $questionData['visible_when'] ?? null,
                        'config' => $questionData['config'] ?? null,
                        'data_class' => $questionData['data_class'] ?? null,
                    ]);

                    $stats['questions']++;

                    // Only create options for kinds that use authored options
                    $kind = QuizQuestionKind::tryFrom($questionData['kind']);
                    if ($kind && $kind->usesAuthoredOptions() && isset($questionData['options'])) {
                        foreach ($questionData['options'] as $optionData) {
                            $question->options()->create([
                                'value' => $optionData['value'],
                                'label' => $optionData['label'],
                                'description' => $optionData['description'] ?? null,
                                'icon' => $optionData['icon'] ?? null,
                                'is_exclusive' => $optionData['is_exclusive'] ?? false,
                                'price_source' => $optionData['price_source'] ?? null,
                                'position' => $optionData['position'] ?? 0,
                                'is_active' => $optionData['is_active'] ?? true,
                            ]);

                            $stats['options']++;
                        }
                    }
                }
            }
        });

        $this->components->success(sprintf(
            'Quiz "%s" %s (slug: %s)',
            $data['name'],
            $existing ? 'updated' : 'created',
            $data['slug'] ?? str($data['name'])->slug()->value()
        ));

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    private function importHealthGoals(array $goals, bool $dryRun): int
    {
        $count = 0;

        foreach ($goals as $goalData) {
            $slug = $goalData['slug'] ?? str($goalData['name'])->slug()->value();
            $existing = HealthGoal::withTrashed()->where('slug', $slug)->first();

            if ($dryRun) {
                $this->line(sprintf(
                    '  Health Goal: %s — would %s',
                    $goalData['name'],
                    $existing ? 'update' : 'create'
                ));
                $count++;

                continue;
            }

            $attributes = [
                'name' => $goalData['name'],
                'slug' => $slug,
                'prompt' => $goalData['prompt'] ?? null,
                'description' => $goalData['description'] ?? null,
                'icon' => $goalData['icon'] ?? null,
                'color' => $goalData['color'] ?? null,
                'is_active' => $goalData['is_active'] ?? true,
                'show_in_quiz' => $goalData['show_in_quiz'] ?? true,
                'position' => $goalData['position'] ?? 0,
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                HealthGoal::create($attributes);
            }

            $count++;
        }

        if (! $dryRun && $count > 0) {
            $this->components->info("Imported {$count} health goals.");
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function quizAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? str($data['name'])->slug()->value(),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
        ];
    }

    /**
     * @param  array<int, mixed>  $steps
     * @param  array<string, int>  $stats
     */
    private function previewSteps(array $steps, array &$stats): void
    {
        foreach ($steps as $stepData) {
            $stats['steps']++;
            $questions = $stepData['questions'] ?? [];
            $stats['questions'] += count($questions);

            foreach ($questions as $q) {
                $kind = QuizQuestionKind::tryFrom($q['kind'] ?? '');
                if ($kind && $kind->usesAuthoredOptions()) {
                    $stats['options'] += count($q['options'] ?? []);
                }
            }

            $this->line(sprintf(
                '  Step: %s (%d questions)',
                $stepData['name'],
                count($questions)
            ));
        }
    }
}
