<?php

use App\Models\Course\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   /**
    * Run the migrations.
    */
   public function up(): void
   {
      Schema::table('payment_histories', function (Blueprint $table) {
         $table->string('purchase_type')->nullable();
         $table->unsignedBigInteger('purchase_id')->nullable();
         $table->json('meta')->nullable();
      });

      // Backfill existing records to the new polymorphic structure
      if (class_exists(Course::class)) {
         DB::table('payment_histories')
            ->whereNotNull('course_id')
            ->update([
               'purchase_type' => Course::class,
               'purchase_id' => DB::raw('course_id'),
            ]);
      }

      Schema::table('payment_histories', function (Blueprint $table) {
         $table->dropForeign(['course_id']);
         $table->dropColumn('course_id');
      });
   }

   /**
    * Reverse the migrations.
    */
   public function down(): void
   {
      Schema::table('payment_histories', function (Blueprint $table) {
         $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
      });

      if (class_exists(Course::class)) {
         DB::table('payment_histories')
            ->where('purchase_type', Course::class)
            ->update([
               'course_id' => DB::raw('purchase_id'),
            ]);
      }

      Schema::table('payment_histories', function (Blueprint $table) {
         $table->dropIndex('payment_histories_purchasable_index');
         $table->dropColumn(['purchase_type', 'purchase_id', 'meta']);
      });
   }
};
