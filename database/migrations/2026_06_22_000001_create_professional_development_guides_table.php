<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_development_guides', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('professional_development_guides')->insert([
            [
                'key' => 'resume',
                'title' => 'Resume Creation Guide',
                'content' => '<p>Learn how to craft a standout resume that highlights your skills and experience.</p>',
                'is_published' => true,
                'sort' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'interview',
                'title' => 'Job Interview Guide',
                'content' => '<p>Prepare for interviews with proven strategies, common questions, and best practices.</p>',
                'is_published' => true,
                'sort' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'bid',
                'title' => 'Bid Proposal Writing',
                'content' => '<p>Master the art of writing winning bid proposals for construction and estimating projects.</p>',
                'is_published' => true,
                'sort' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_development_guides');
    }
};
