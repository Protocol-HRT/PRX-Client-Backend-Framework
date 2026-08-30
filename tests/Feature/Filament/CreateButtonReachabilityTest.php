<?php

namespace Tests\Feature\Filament;

use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Every resource that can be created has a button that creates it.
 *
 * ─── The bug this exists to prevent, which shipped twice ───────────────
 *
 * `WorkflowResource` and `LeadDispositionResource` both registered a `create`
 * route, both had a working `CreateWorkflow` / `CreateLeadDisposition` page, and
 * neither list page rendered a Create button. The whole automation engine was
 * unreachable through the admin: an operator saw an empty table with no action
 * on it and concluded, correctly, that the feature did not exist. Only somebody
 * who typed `/admin/workflows/create` into the address bar could use it.
 *
 * ─── Why the existing tests were no help ───────────────────────────────
 *
 * They mounted `CreateLeadDisposition` DIRECTLY — `Livewire::test(CreateX::class)`
 * — which is the one route that skips the missing button entirely. The page
 * worked, the form worked, the record saved, and the test was green the whole
 * time the feature was unreachable. A test that enters through the back door
 * cannot discover the front door is bricked up.
 *
 * So this asserts REACHABILITY rather than behaviour, and does it for every
 * resource at once rather than per feature, because the next resource to forget
 * the button has not been written yet.
 *
 * A resource with no `create` route is not a failure: leads, orders, encounters
 * and subscribers are created by the system — by a quiz submission, a checkout,
 * a webhook — and a Create button on any of them would be wrong.
 */
class CreateButtonReachabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_creatable_resource_exposes_a_create_action(): void
    {
        $missing = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $pages = $resource::getPages();

            if (! isset($pages['create']) || ! isset($pages['index'])) {
                continue;
            }

            if (! $this->exposesCreateAction($this->pageClass($pages['index']))) {
                $missing[] = class_basename($resource);
            }
        }

        $this->assertSame([], $missing, sprintf(
            'These resources register a create page that nothing links to, so it can only be '
            .'reached by typing the URL: %s. Add getHeaderActions(): [CreateAction::make()] to '
            .'the list page.',
            implode(', ', $missing),
        ));
    }

    /** @return class-string */
    private function pageClass(PageRegistration $registration): string
    {
        return $registration->getPage();
    }

    /**
     * Whether a list page offers a create action in its header.
     *
     * Reflection rather than mounting each page through Livewire: this walks
     * every resource in the panel, and mounting three dozen pages to read one
     * array each would turn a guard into something slow enough to be skipped.
     *
     * @param  class-string  $page
     */
    private function exposesCreateAction(string $page): bool
    {
        if (! method_exists($page, 'getHeaderActions')) {
            return false;
        }

        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        foreach ($method->invoke(new $page) as $action) {
            if ($action instanceof CreateAction) {
                return true;
            }
        }

        return false;
    }
}
