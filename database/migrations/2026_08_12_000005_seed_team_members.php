<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_members')) {
            return;
        }

        if (DB::table('team_members')->exists()) {
            return;
        }

        $members = [
            [
                'name' => 'Maria Santos',
                'role' => 'Lead Training Specialist',
                'photo' => '/assets/images/ssu-about/about-team-1.png',
                'sort_order' => 1,
            ],
            [
                'name' => 'James Mitchell',
                'role' => 'Program Director',
                'photo' => '/assets/images/ssu-about/about-team-2.png',
                'sort_order' => 2,
            ],
            [
                'name' => 'Elena Park',
                'role' => 'Curriculum Designer',
                'photo' => '/assets/images/ssu-about/about-team-3.png',
                'sort_order' => 3,
            ],
        ];

        foreach ($members as $member) {
            DB::table('team_members')->insert([
                ...$member,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('team_members')) {
            DB::table('team_members')->truncate();
        }
    }
};
