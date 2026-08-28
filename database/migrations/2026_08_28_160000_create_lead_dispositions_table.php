<?php

use App\Enums\LeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead dispositions — the operator's own vocabulary for where a lead sits.
 *
 * KEYED BY SLUG, NOT BY FOREIGN KEY, and that is the whole design. `leads.status`
 * already holds a string ('new', 'handed_off', ...) and every row in the install
 * already carries one. Introducing a `disposition_id` would mean a data
 * migration, a rewrite of the API resource, and a nullable FK that is null for
 * exactly as long as someone forgets to backfill it. Matching on slug means the
 * column keeps its current values, the API keeps emitting the same strings, and
 * an operator adding `quiz_complete` just inserts a row.
 *
 * The trade is referential integrity, which we buy back in the model: a
 * disposition that leads reference cannot be deleted, and its slug cannot be
 * renamed. That is the same guard the palette colours use, for the same reason —
 * the reference is by name, so a rename IS a removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_dispositions', function (Blueprint $table) {
            $table->id();

            // The value that lands in `leads.status`. Immutable once in use.
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();

            // A Filament colour name ('gray', 'success', ...), not a hex. The
            // admin picks from the framework's palette so badges stay coherent
            // with the rest of the panel.
            $table->string('color')->default('gray');

            // Exactly one row may be default; enforced in the model, not here,
            // because a partial unique index is not portable across the drivers
            // prx-backend ships against.
            $table->boolean('is_default')->default(false);

            // A slug the CODE depends on (see App\Enums\LeadStatus). System rows
            // are renameable and recolourable but never deletable, and their slug
            // is frozen — MarkLeadHandedOffAction writes 'handed_off' literally.
            $table->boolean('is_system')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Seed the four the code already writes. A new install therefore boots
        // with a working funnel rather than an empty select, and this install's
        // existing rows resolve immediately — every `leads.status` value in the
        // database is one of these four.
        $now = now();

        DB::table('lead_dispositions')->insert([
            [
                'slug' => LeadStatus::New_->value,
                'name' => 'New',
                'description' => 'Captured, not yet acted on.',
                'color' => 'gray',
                'is_default' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => LeadStatus::HandedOff->value,
                // Vendor-neutral on purpose. This backend ships to many installs
                // and a seeded default must not name one integration; the slug
                // is what code writes, and the label is the operator's to change
                // to whatever they actually hand off to.
                'name' => 'Handed off',
                'description' => 'Passed to the external intake or fulfilment provider.',
                'color' => 'warning',
                'is_default' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => LeadStatus::Completed->value,
                'name' => 'Completed',
                'description' => 'Checkout finished.',
                'color' => 'success',
                'is_default' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => LeadStatus::Abandoned->value,
                'name' => 'Abandoned',
                'description' => 'Went cold without converting.',
                'color' => 'danger',
                'is_default' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_dispositions');
    }
};
