<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Filament\Resources\Workflows\Pages\EditWorkflow;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Filament\Resources\Workflows\RelationManagers\RunsRelationManager;
use App\Models\Lead;
use App\Models\User;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The workflow builder mounts, saves, and shows its run log.
 *
 * Worth its own file because this form is unusually dynamic — two dependent
 * selects, three repeaters and several option lists read from the registry at
 * render time. None of that fails until Livewire actually mounts it.
 */
class WorkflowAdminTest extends TestCase
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

    public function test_the_list_renders(): void
    {
        Workflow::create([
            'name' => 'Welcome', 'slug' => 'welcome',
            'trigger_type' => 'event_fired', 'trigger_target' => 'lead.created',
        ]);

        Livewire::test(ListWorkflows::class)->assertOk()->assertSee('Welcome');
    }

    public function test_a_workflow_can_be_built_with_conditions_and_steps(): void
    {
        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'Move quiz completions',
                'slug' => 'move-quiz-completions',
                'trigger_type' => 'event_fired',
                'trigger_target' => 'quiz.completed',
                'conditions' => [
                    ['field' => 'email_consent', 'operator' => 'equals', 'value' => '1'],
                ],
                'actions' => [
                    [
                        'action_type' => 'update_field',
                        'name' => 'Move to quiz complete',
                        'config' => ['field' => 'status', 'value' => 'quiz_complete'],
                        'is_active' => true,
                        'halt_on_failure' => false,
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
                'stop_on_first_match' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = Workflow::query()->where('slug', 'move-quiz-completions')->firstOrFail();

        $this->assertSame('quiz.completed', $workflow->trigger_target);
        $this->assertSame('email_consent', $workflow->conditions[0]['field']);
        $this->assertSame('update_field', $workflow->actions->first()->action_type);
        $this->assertSame('quiz_complete', $workflow->actions->first()->config['value']);
    }

    public function test_the_edit_form_mounts_for_a_model_trigger_too(): void
    {
        // The other branch of the dependent select, where the target is a
        // subject rather than an event.
        $workflow = Workflow::create([
            'name' => 'On lead update', 'slug' => 'on-lead-update',
            'trigger_type' => 'model_updated', 'trigger_target' => 'lead',
            'conditions' => [['field' => '_original.status', 'operator' => 'equals', 'value' => 'new']],
        ]);

        Livewire::test(EditWorkflow::class, ['record' => $workflow->getKey()])
            ->assertOk()
            ->assertFormSet(['trigger_target' => 'lead']);
    }

    public function test_the_run_log_shows_a_skipped_run_and_its_reason(): void
    {
        $workflow = Workflow::create([
            'name' => 'Never', 'slug' => 'never',
            'trigger_type' => 'model_updated', 'trigger_target' => 'lead',
        ]);

        WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'subject_type' => (new Lead)->getMorphClass(),
            'subject_id' => 1,
            'trigger_type' => 'model_updated',
            'status' => WorkflowRun::STATUS_SKIPPED,
            'skip_reason' => 'status equals "quiz_complete" — actual: "new"',
        ]);

        Livewire::test(RunsRelationManager::class, [
            'ownerRecord' => $workflow,
            'pageClass' => EditWorkflow::class,
        ])
            ->assertOk()
            ->assertSee('quiz_complete');
    }
}
