<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health-goal badges on catalog items.
 *
 * Two additions, and the reasoning for each is worth keeping:
 *
 * 1. `health_goals.badge_color` holds a palette NAME, not a hex. The existing
 *    `color` column is a hex and is left alone — it is already on the public
 *    API surface (HealthGoalResource) and other prx-backend frontends may read
 *    it. A name is required because the palette-deletion guard can only find a
 *    colour's users if they name it; a hex would silently drift out of step
 *    with the theme the moment an operator retuned the palette.
 *
 *    There is deliberately NO badge text-colour column. The label is derived
 *    from `--palette-{name}-contrast`, the same way button labels are, so a
 *    badge cannot be authored unreadable. Adding a picker later is one column,
 *    one select and one guard key — see the handoff before doing it.
 *
 * 2. `health_goal_package` is an OVERRIDE, not the source of truth. A package
 *    shows the union of its products' goals by default, so tagging a product
 *    once updates every stack containing it; this table exists only so a stack
 *    marketed for a single goal can be pinned, and when it has rows they
 *    REPLACE the derived set rather than adding to it.
 *
 *    Display only. The recommendation resolver must never read it — packages
 *    are never mapped directly to goals for recommendation purposes
 *    (GoalRecommendationResolver), and that rule is not changed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_goals', function (Blueprint $table) {
            $table->string('badge_color')->nullable()->after('color');
        });

        Schema::create('health_goal_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['health_goal_id', 'package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_goal_package');

        Schema::table('health_goals', function (Blueprint $table) {
            $table->dropColumn('badge_color');
        });
    }
};
