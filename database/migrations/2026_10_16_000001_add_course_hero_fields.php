<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('hero_eyebrow', 80)->nullable()->after('short_description');
            $table->text('hero_headline')->nullable()->after('hero_eyebrow');
            $table->json('hero_highlights')->nullable()->after('hero_headline');
            $table->text('hero_paragraph')->nullable()->after('hero_highlights');
            $table->text('hero_photo')->nullable()->after('banner');
            $table->text('hero_video')->nullable()->after('preview');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'hero_eyebrow',
                'hero_headline',
                'hero_highlights',
                'hero_paragraph',
                'hero_photo',
                'hero_video',
            ]);
        });
    }
};
