<?php

namespace App\Filament\Support;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Renders a resource's relation managers as VERTICAL tabs in the page
 * content, replacing the default horizontal below-the-fold strip that users
 * miss. Drop into any Edit/View page's content() override:
 *
 *     public function content(Schema $schema): Schema
 *     {
 *         return $schema->components([
 *             $this->getFormContentComponent(),
 *             RelationTabs::make($this),
 *         ]);
 *     }
 *
 * Managers keep their stock behavior: labels come from getTitle(),
 * visibility from canViewForRecord(), and each manager loads lazily when
 * its tab first becomes visible. Only class-string registrations in
 * getRelations() are supported (this codebase's convention) — grouped or
 * configured registrations would need unwrapping here first.
 */
class RelationTabs
{
    public static function make(EditRecord|ViewRecord $page): Tabs
    {
        $record = $page->getRecord();
        $pageClass = $page::class;

        $tabs = [];

        foreach ($page::getResource()::getRelations() as $manager) {
            if (! $manager::canViewForRecord($record, $pageClass)) {
                continue;
            }

            $tabs[] = Tab::make($manager::getTitle($record, $pageClass))
                ->schema([
                    Livewire::make($manager, [
                        'ownerRecord' => $record,
                        'pageClass' => $pageClass,
                    ])
                        ->key('relation-tab-'.$manager)
                        ->lazy(),
                ]);
        }

        return Tabs::make('Related')
            ->tabs($tabs)
            ->vertical()
            ->persistTabInQueryString('relation')
            ->visible($tabs !== []);
    }
}
