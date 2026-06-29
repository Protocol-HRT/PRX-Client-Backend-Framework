<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['group' => 'communication', 'name' => 'twilio_account_sid', 'payload' => 'null'],
            ['group' => 'communication', 'name' => 'twilio_auth_token', 'payload' => 'null'],
            ['group' => 'communication', 'name' => 'twilio_from_number', 'payload' => 'null'],
            ['group' => 'communication', 'name' => 'sms_enabled', 'payload' => 'false'],
            ['group' => 'communication', 'name' => 'sms_opt_in_message', 'payload' => 'null'],
            ['group' => 'communication', 'name' => 'voice_enabled', 'payload' => 'false'],
            ['group' => 'communication', 'name' => 'video_enabled', 'payload' => 'false'],
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
        DB::table('settings')->where('group', 'communication')->delete();
    }
};
