<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Integrations\Pages\CreateIntegrationInstance;
use App\Filament\Resources\Integrations\Pages\EditIntegrationInstance;
use App\Filament\Resources\Integrations\Pages\ListIntegrationInstances;
use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Models\Integrations\IntegrationInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The integration screens, actually rendered.
 *
 * WHY THIS EXISTS AS A TEST RATHER THAN A CLICK-THROUGH: every screen here is
 * built from closures that run at render time — the provider list, the
 * capability checkboxes, the per-driver credential fields, the per-action config
 * form. None of that is exercised by a unit test of the registry, and a closure
 * that throws produces a blank panel rather than a failing assertion anywhere.
 * This project has shipped an admin form nobody had rendered before; these are
 * cheap and they close that gap.
 */
class IntegrationAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        return $user;
    }

    public function test_the_list_screen_renders(): void
    {
        $this->actAsAdmin();

        Livewire::test(ListIntegrationInstances::class)->assertOk();
    }

    public function test_an_integration_can_be_created_with_only_its_required_fields(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateIntegrationInstance::class)
            ->fillForm(['name' => 'Site email', 'provider' => 'local_mail'])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = IntegrationInstance::query()->where('provider', 'local_mail')->first();

        $this->assertNotNull($instance);
        // Derived rather than typed, so an operator never has to invent one.
        $this->assertSame('site-email', $instance->slug);
    }

    public function test_the_edit_screen_renders_with_its_attestation_action(): void
    {
        $this->actAsAdmin();

        $instance = IntegrationInstance::create([
            'name' => 'Site email', 'provider' => 'local_mail', 'capabilities' => ['transactional_email'],
        ]);

        Livewire::test(EditIntegrationInstance::class, ['record' => $instance->getKey()])
            ->assertOk()
            ->assertActionExists('attestPhi')
            ->assertActionExists('test');
    }

    public function test_permitting_health_data_records_who_said_so(): void
    {
        // The attestation is only worth anything if it captures the person. This
        // drives the real admin action rather than the model method, because the
        // form is where the operator's identity is actually available.
        $user = $this->actAsAdmin();

        $instance = IntegrationInstance::create([
            'name' => 'Marketing', 'provider' => 'local_mail', 'capabilities' => ['crm'],
        ]);

        Livewire::test(EditIntegrationInstance::class, ['record' => $instance->getKey()])
            ->callAction('attestPhi', ['permitted' => true, 'note' => 'BAA signed 2026-08-01']);

        $instance->refresh();

        $this->assertTrue($instance->phi_permitted);
        $this->assertSame($user->id, $instance->attestations()->first()->attested_by_user_id);
        $this->assertSame('BAA signed 2026-08-01', $instance->attestations()->first()->note);
    }

    public function test_the_workflow_form_renders_the_integration_config_form(): void
    {
        // The per-action config schema is resolved by a closure inside a
        // repeater. If it throws, the whole workflow builder goes blank — which
        // would break the three actions that predate integrations too.
        $this->actAsAdmin();

        IntegrationInstance::create([
            'name' => 'Site email', 'provider' => 'local_mail', 'capabilities' => ['transactional_email'],
        ]);

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'Welcome',
                'slug' => 'welcome',
                'trigger_type' => 'model_created',
                'trigger_target' => 'lead',
                'actions' => [
                    [
                        'action_type' => 'send_email',
                        'config' => [
                            'integration' => 'site-email',
                            'to' => 'email',
                            'subject' => 'Welcome',
                            'body' => 'Hello.',
                        ],
                        'is_active' => true,
                        'halt_on_failure' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workflow_actions', ['action_type' => 'send_email']);
    }

    public function test_the_workflow_builder_still_renders_with_no_integrations_at_all(): void
    {
        // A fresh install has none, and every integration action is therefore
        // filtered out of the palette. The builder must still work for the three
        // actions that need no integration.
        $this->actAsAdmin();

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'Move it',
                'slug' => 'move-it',
                'trigger_type' => 'model_created',
                'trigger_target' => 'lead',
                'actions' => [
                    [
                        'action_type' => 'update_field',
                        'config' => ['field' => 'status', 'value' => 'nurture'],
                        'is_active' => true,
                        'halt_on_failure' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workflow_actions', ['action_type' => 'update_field']);
    }
}
