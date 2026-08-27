<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The age the visitor actually gave the quiz.
 *
 * `leads.date_of_birth` already exists and is what PRX wants at handoff, so
 * the obvious move is to store a birth date and derive age from it. That would
 * be fabricating data. The intake quiz asks for an AGE — one slider, no month,
 * no day — because a date of birth is a heavier thing to ask of someone who
 * has not yet decided to buy, and back-computing 1 January of the implied year
 * writes a birthday this person never gave us into a column that a pharmacy
 * later treats as identifying.
 *
 * So the two columns coexist and mean different things: `age` is what the
 * funnel captured, `date_of_birth` is what a completed clinical intake
 * captured. A lead can carry either, both, or neither. When both are present
 * `date_of_birth` is the better source and callers should prefer it —
 * Lead::effectiveAge() encodes that precedence in one place.
 *
 * Unsigned tinyint caps at 255, which is beyond any real answer and well
 * beyond the quiz's 18-100 range. The range is NOT enforced here: the column
 * records what was answered, and a validation rule that rejects it belongs at
 * the request boundary where it can return a message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('age')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('age');
        });
    }
};
