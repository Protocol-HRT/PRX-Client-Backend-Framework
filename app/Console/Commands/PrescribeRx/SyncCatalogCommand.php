<?php

namespace App\Console\Commands\PrescribeRx;

use App\Actions\Catalog\SyncPrescribeRxCatalogAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('prescribe-rx:sync-catalog')]
#[Description('Import products, packages, and plans from the PRX sales-org catalog. New items land at Pending status for admin review.')]
class SyncCatalogCommand extends Command
{
    public function handle(SyncPrescribeRxCatalogAction $action): int
    {
        $this->line('');
        $this->info('Syncing PRX catalog → local catalog');
        $this->line(str_repeat('─', 60));
        $this->line('  New items land at Pending status — admin review required before publishing.');
        $this->line('  Pricing is always updated. Names/descriptions only overwritten on Pending items.');
        $this->line('  Images are NOT imported — upload brand-appropriate images in the admin.');
        $this->line('');

        try {
            $stats = $action->execute();
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sync complete');
        $this->line('');
        $this->table(
            ['Entity', 'New', 'Updated'],
            [
                ['Products', $stats['products']['new'], $stats['products']['updated']],
                ['Packages', $stats['packages']['new'], $stats['packages']['updated']],
                ['Plans', $stats['plans']['new'], $stats['plans']['updated']],
            ]
        );

        $total = $stats['products']['new'] + $stats['packages']['new'] + $stats['plans']['new'];

        if ($total > 0) {
            $this->line('');
            $this->warn("  {$total} new item(s) at Pending status — review and publish in /admin/catalog.");
        }

        return self::SUCCESS;
    }
}
