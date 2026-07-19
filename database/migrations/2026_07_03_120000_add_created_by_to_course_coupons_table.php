<?php



use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;



return new class extends Migration

{

    public function up(): void

    {

        if (!Schema::hasTable('course_coupons')) {

            return;

        }



        Schema::table('course_coupons', function (Blueprint $table) {

            if (!Schema::hasColumn('course_coupons', 'created_by')) {

                $table->foreignId('created_by')

                    ->nullable()

                    ->after('course_id')

                    ->constrained('users')

                    ->nullOnDelete();

            }

        });

    }



    public function down(): void

    {

        if (!Schema::hasTable('course_coupons') || !Schema::hasColumn('course_coupons', 'created_by')) {

            return;

        }



        Schema::table('course_coupons', function (Blueprint $table) {

            $table->dropConstrainedForeignId('created_by');

        });

    }

};


