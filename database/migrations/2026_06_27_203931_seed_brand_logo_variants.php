<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'brand', 'name' => 'logo_dark_path', 'payload' => 'null'],
            ['group' => 'brand', 'name' => 'logo_light_path', 'payload' => 'null'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->insertOrIgnore([
                ...$row,
                'locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'brand')
            ->whereIn('name', ['logo_dark_path', 'logo_light_path'])
            ->delete();
    }
};
